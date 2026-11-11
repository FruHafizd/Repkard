<?php

use App\Http\Controllers\LandingController;
use App\Http\Controllers\MemberCardPdfController;
use App\Http\Controllers\MemberPhotoController;
use App\Http\Controllers\PaymentReceiptController;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Support\Facades\Route;

Route::get('/', [LandingController::class, 'index']);

Route::middleware(['auth', EnsureUserIsActive::class])->group(function (): void {
    Route::get('/media/member-photos/{member}', MemberPhotoController::class)
        ->name('member.photo');

    Route::get('/pembayaran/{payment}/kuitansi', PaymentReceiptController::class)
        ->name('payment.receipt');

    Route::get('/kartu/{member}/pdf', MemberCardPdfController::class)
        ->name('member.card.pdf');
});
