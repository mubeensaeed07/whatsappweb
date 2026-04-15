<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/whatsapp/qr-view', function () {
    return view('whatsapp.qr-view');
});
