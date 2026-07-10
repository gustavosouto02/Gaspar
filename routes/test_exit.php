<?php
use Illuminate\Support\Facades\Route;

Route::get('/test-exit', function () {
    header('Location: /instalar');
    exit;
});
