<?php

use App\Http\Controllers\BookController;
use App\Http\Controllers\MpesaController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::get('/home', [BookController::class, 'index']);
Route::get('/shopping-cart', [BookController::class, 'bookCart'])->name('shopping.cart');
Route::get('/book/{id}', [BookController::class, 'addBooktoCart'])->name('addbook.to.cart');
Route::patch('/update-shopping-cart', [BookController::class, 'updateCart'])->name('update.shopping.cart');
Route::delete('/delete-cart-product', [BookController::class, 'deleteProduct'])->name('delete.cart.product');

// Define the named routes for M-Pesa
Route::middleware('auth')->group(function () {
    Route::post('/mpesa/stk-push', [MpesaController::class, 'initiateStkPush'])->name('mpesa.stk-push');
    Route::post('/mpesa/callback', [MpesaController::class, 'stkPushCallback'])->name('mpesa.callback');
    Route::get('/mpesa/status/{checkoutRequestID}', [MpesaController::class, 'checkTransactionStatus'])->name('mpesa.status');
});

require __DIR__.'/auth.php';
