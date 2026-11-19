<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('eye')->name('eye.')->group(function () {
    Route::get('/issues', [\App\Http\Controllers\Eye\IssueController::class, 'index'])->name('issues.index');
    Route::get('/issues/{issue}', [\App\Http\Controllers\Eye\IssueController::class, 'show'])->name('issues.show');
    Route::patch('/issues/{issue}', [\App\Http\Controllers\Eye\IssueController::class, 'update'])->name('issues.update');
    Route::delete('/issues/{issue}', [\App\Http\Controllers\Eye\IssueController::class, 'destroy'])->name('issues.destroy');
    Route::post('/issues/{issue}/copy-curl', [\App\Http\Controllers\Eye\IssueController::class, 'copyCurl'])->name('issues.copy-curl');
    Route::get('/performance', [\App\Http\Controllers\Eye\PerformanceController::class, 'index'])->name('performance.index');
});
