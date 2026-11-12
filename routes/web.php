<?php

use App\Http\Controllers\ExpenseProofController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\MemberCardPdfController;
use App\Http\Controllers\MemberPhotoController;
use App\Http\Controllers\PaymentReceiptController;
use App\Http\Controllers\SaleReceiptController;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Support\Facades\Route;

Route::get('/', [LandingController::class, 'index']);

Route::middleware(['auth', EnsureUserIsActive::class])->group(function (): void {
    Route::get('/media/member-photos/{member}', MemberPhotoController::class)
        ->name('member.photo');

    Route::get('/media/bukti-pengeluaran/{expense}', ExpenseProofController::class)
        ->name('expense.proof');

    Route::get('/pembayaran/{payment}/kuitansi', PaymentReceiptController::class)
        ->name('payment.receipt');

    Route::get('/kasir/struk/{sale}', SaleReceiptController::class)
        ->middleware('throttle:60,1')
        ->name('kasir.receipt');

    Route::get('/kartu/{member}/pdf', MemberCardPdfController::class)
        ->name('member.card.pdf');
});
