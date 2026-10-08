<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::view('/privacy', 'privacy');
Route::view('/child-safety', 'child-safety');
// Account deletion page: Google Play's Data safety form links here, and it is
// the way to ask for deletion without access to the app.
Route::view('/delete-account', 'delete-account');
// Support page: the App Store Support URL points here, and App Review checks
// that it resolves and offers a real way to reach us.
Route::view('/kontakt', 'kontakt');
