<?php

use App\Http\Controllers\LandingController;
use App\Http\Controllers\MemberCardPdfController;
use App\Http\Controllers\MemberPhotoController;
use App\Http\Controllers\PaymentReceiptController;
use Illuminate\Support\Facades\Route;

Route::get('/', [LandingController::class, 'index']);

Route::get('/media/member-photos/{member}', MemberPhotoController::class)
    ->middleware('auth')
    ->name('member.photo');

Route::get('/pembayaran/{payment}/kuitansi', PaymentReceiptController::class)
    ->middleware('auth')
    ->name('payment.receipt');

Route::get('/kartu/{member}/pdf', MemberCardPdfController::class)
    ->middleware('auth')
    ->name('member.card.pdf');
