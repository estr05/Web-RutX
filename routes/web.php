<?php

use Illuminate\Support\Facades\Route;

// Redirección temporal a playground mientras no hay módulos reales
Route::get('/', function () {
    return redirect()->route('playground');
});

// Habilitar playground en desarrollo
if (app()->environment('local', 'testing', 'development')) {
    require __DIR__.'/modules/playground.php';
}
