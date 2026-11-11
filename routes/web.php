<?php

use App\Http\Controllers\MemberPhotoController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/media/member-photos/{member}', MemberPhotoController::class)
    ->middleware('auth')
    ->name('member.photo');
