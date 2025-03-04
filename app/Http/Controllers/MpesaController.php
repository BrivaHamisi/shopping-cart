<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use GuzzleHttp\Client;
use Carbon\Carbon;
use App\Models\Transaction;
use App\Models\Order;
use App\Services\SmsService;

class MpesaController extends Controller
{
    private $consumerKey;
    private $consumerSecret;
    private $passkey;
    private $businessShortCode;
    private $callbackUrl;
    private $smsService;

    public function __construct(SmsService $smsService)
    {
        $this->consumerKey = env('MPESA_CONSUMER_KEY');
        $this->consumerSecret = env('MPESA_CONSUMER_SECRET');
        $this->passkey = env('MPESA_PASSKEY');
        $this->businessShortCode = env('MPESA_BUSINESS_SHORTCODE');
        $this->callbackUrl = env('MPESA_CALLBACK_URL');
        $this->smsService = $smsService;
    }

    private function generateAccessToken()
    {
        $url = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
        $client = new Client();

        try {
            $response = $client->request('GET', $url, [
                'auth' => [
                    $this->consumerKey,
                    $this->consumerSecret
                ]
            ]);

            $body = json_decode($response->getBody());
            return $body->access_token;
        } catch (\Exception $e) {
            Log::error('M-Pesa Access Token Generation Failed: ' . $e->getMessage());
            return null;
        }
    }

    private function validatePhoneNumber($phone)
    {
        $phone = preg_replace('/\D/', '', $phone);

        if (preg_match('/^(?:254|\+254|0)?(7(?:(?:[0-9][0-9])|(?:0[0-9])|(?:1[0-9]))\d{6})$/', $phone)) {
            return '254' . substr($phone, -9);
        }

        return null;
    }

    public function initiateStkPush(Request $request)
    {
        $request->validate([
            'phone_number' => 'required|string'
        ]);

        $phoneNumber = $this->validatePhoneNumber($request->input('phone_number'));

        if (!$phoneNumber) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid Kenyan phone number'
            ], 400);
        }

        $cart = session()->get('cart', []);
        $total = array_reduce($cart, function($carry, $item) {
            return $carry + ($item['price'] * $item['quantity']);
        }, 0);

        if ($total <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid cart total'
            ], 400);
        }

        $timestamp = Carbon::now()->format('YmdHis');
        $password = base64_encode($this->businessShortCode . $this->passkey . $timestamp);

        $payload = [
            'BusinessShortCode' => $this->businessShortCode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => round($total),
            'PartyA' => $phoneNumber,
            'PartyB' => $this->businessShortCode,
            'PhoneNumber' => $phoneNumber,
            'CallBackURL' => $this->callbackUrl,
            'AccountReference' => 'Book Store Purchase',
            'TransactionDesc' => 'Book Purchase'
        ];

        $accessToken = $this->generateAccessToken();
        if (!$accessToken) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate access token'
            ], 500);
        }

        $client = new Client();
        try {
            $response = $client->request('POST', 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json'
                ],
                'json' => $payload
            ]);

            $responseBody = json_decode($response->getBody());

            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $transaction = Transaction::create([
                'user_id' => $user->id,
                'amount' => $total,
                'phone_number' => $phoneNumber,
                'status' => 'pending',
                'checkout_request_id' => $responseBody->CheckoutRequestID ?? null
            ]);

            session()->put('pending_transaction_id', $transaction->id);

            return response()->json([
                'success' => true,
                'message' => 'STK Push sent successfully',
                'transaction_id' => $transaction->id
            ]);

        } catch (\Exception $e) {
            Log::error('M-Pesa STK Push Failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'STK Push failed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function stkPushCallback(Request $request)
    {
        Log::info('M-Pesa STK Push Callback Raw Data: ' . json_encode($request->all()));

        try {
            $callbackData = $request->input('Body.stkCallback');
            $resultCode = $callbackData['ResultCode'];
            $resultDesc = $callbackData['ResultDesc'];
            $checkoutRequestID = $callbackData['CheckoutRequestID'];

            $transaction = Transaction::where('checkout_request_id', $checkoutRequestID)->first();

            if (!$transaction) {
                Log::error('No matching transaction found for CheckoutRequestID: ' . $checkoutRequestID);
                return response()->json(['status' => 'error', 'message' => 'Transaction not found'], 404);
            }

            if ($resultCode == 0) {
                $callbackMetadata = $callbackData['CallbackMetadata']['Item'];
                $transactionDetails = $this->extractTransactionMetadata($callbackMetadata);

                $transaction->update([
                    'status' => 'completed',
                    'mpesa_receipt_number' => $transactionDetails['mpesa_receipt_number'],
                    'amount' => $transactionDetails['amount'],
                    'phone_number' => $transactionDetails['phone_number'],
                    'transaction_date' => now()
                ]);

                $order = $this->processSuccessfulOrder($transaction);
                $this->sendCustomerConfirmation($transaction, $order);

                Log::info('M-Pesa Transaction Processed Successfully: ' . $checkoutRequestID);
            } else {
                $transaction->update([
                    'status' => 'failed',
                    'failure_reason' => $resultDesc
                ]);

                Log::warning('M-Pesa Transaction Failed: ' . $resultDesc);
            }

            return response()->json(['status' => 'success']);

        } catch (\Exception $e) {
            Log::error('M-Pesa Callback Processing Error: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    private function extractTransactionMetadata($callbackMetadata)
    {
        $transactionDetails = [
            'mpesa_receipt_number' => null,
            'amount' => null,
            'phone_number' => null
        ];

        foreach ($callbackMetadata as $item) {
            switch ($item['Name']) {
                case 'Amount':
                    $transactionDetails['amount'] = $item['Value'];
                    break;
                case 'MpesaReceiptNumber':
                    $transactionDetails['mpesa_receipt_number'] = $item['Value'];
                    break;
                case 'PhoneNumber':
                    $transactionDetails['phone_number'] = $item['Value'];
                    break;
            }
        }

        return $transactionDetails;
    }

    private function processSuccessfulOrder(Transaction $transaction)
    {
        DB::beginTransaction();

        try {
            $cart = session()->get('cart', []);

            $order = Order::create([
                'user_id' => $transaction->user_id,
                'transaction_id' => $transaction->id,
                'total_amount' => $transaction->amount,
                'status' => 'paid'
            ]);

            foreach ($cart as $item) {
                $order->orderItems()->create([
                    'book_id' => $item['id'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price']
                ]);
            }

            session()->forget('cart');

            DB::commit();

            return $order;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Order Processing Error: ' . $e->getMessage());
            throw $e;
        }
    }

    private function sendCustomerConfirmation(Transaction $transaction, Order $order)
    {
        $this->smsService->send(
            $transaction->phone_number, 
            "Your order {$order->id} is confirmed. Total: KES {$transaction->amount}"
        );

        // You can add email confirmation here if needed
    }

    public function checkTransactionStatus($checkoutRequestID)
    {
        $accessToken = $this->generateAccessToken();
        if (!$accessToken) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate access token'
            ], 500);
        }

        $payload = [
            'BusinessShortCode' => $this->businessShortCode,
            'Password' => base64_encode($this->businessShortCode . $this->passkey . date('YmdHis')),
            'Timestamp' => date('YmdHis'),
            'CheckoutRequestID' => $checkoutRequestID
        ];

        $client = new Client();
        try {
            $response = $client->request('POST', 'https://sandbox.safaricom.co.ke/mpesa/stkpushquery/v1/query', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json'
                ],
                'json' => $payload
            ]);

            $responseBody = json_decode($response->getBody());

            return response()->json([
                'success' => true,
                'status' => $responseBody->ResultCode == 0 ? 'completed' : 'failed',
                'message' => $responseBody->ResultDesc
            ]);

        } catch (\Exception $e) {
            Log::error('Transaction Status Check Failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to check transaction status'
            ], 500);
        }
    }
}
