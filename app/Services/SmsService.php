<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class SmsService 
{
    public function send($phone, $message)
    {
        // Implement actual SMS sending logic
        Log::info("SMS to $phone: $message");
    }
}
