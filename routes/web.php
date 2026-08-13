<?php

use Illuminate\Support\Facades\Route;

// Redirección temporal a playground mientras no hay módulos reales
Route::get('/', function () {
    return redirect()->route('playground');
});

// Habilitar playground en desarrollo o testing
if (app()->environment('local', 'testing', 'development') || app()->runningUnitTests()) {
    require __DIR__.'/modules/playground.php';
}
