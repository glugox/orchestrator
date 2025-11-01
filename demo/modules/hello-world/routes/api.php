<?php

use Illuminate\Support\Facades\Route;

Route::get('hello-world', function () {
    return response()->json([
        'message' => 'Hello from the Orchestrator hello world module!',
        'timestamp' => now()->toISOString(),
    ]);
})->name('index');
