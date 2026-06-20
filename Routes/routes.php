<?php

use App\Modules\BasicMedia\Http\Controllers\BasicMediaController;
use Illuminate\Support\Facades\Route;

Route::prefix('basic-media')->as('basic-media.')->group(function () {
    Route::get('/',        [BasicMediaController::class, 'index'])->name('index');
    Route::post('/upload', [BasicMediaController::class, 'upload'])->name('upload');
    Route::delete('/',     [BasicMediaController::class, 'destroy'])->name('destroy');
});
