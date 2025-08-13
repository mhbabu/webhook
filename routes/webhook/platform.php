<?php

use App\Http\Controllers\Webhook\PlatformController;
use Illuminate\Support\Facades\Route;

Route::get('platforms', [PlatformController::class, 'getPlatforms']);
