<?php

use Illuminate\Support\Facades\Route;


Route::get('/', function () {
    return view('welcome');
});


Route::get('/horizon', function () {
    return redirect('/horizon/dashboard');
});

// require __DIR__ . '/auth.php';
