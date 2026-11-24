<?php

use Illuminate\Support\Facades\Route;
use StevenBrown85\TwillResponseCache\Http\Controllers\ClearCacheController;

Route::post('response-cache/clear', ClearCacheController::class)
    ->name('responsecache.clear');
