<?php

use Illuminate\Support\Facades\Route;

Route::prefix('eye')->name('eye.')->group(function () {
    Route::post('/ingest', [\App\Http\Controllers\Eye\IngestionController::class, 'store'])->name('ingest');
});
