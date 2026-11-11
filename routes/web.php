<?php

use App\Http\Controllers\LandingController;
use App\Http\Controllers\MemberPhotoController;
use Illuminate\Support\Facades\Route;

Route::get('/', [LandingController::class, 'index']);

Route::get('/media/member-photos/{member}', MemberPhotoController::class)
    ->middleware('auth')
    ->name('member.photo');
