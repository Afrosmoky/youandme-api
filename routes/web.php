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
