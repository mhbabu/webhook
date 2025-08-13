<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Http\Resources\Webhook\PlatformResource;
use App\Models\Platform;
use Illuminate\Http\Request;

class PlatformController extends Controller
{
    public function getPlatforms()
    {

        $platforms       = Platform::where('status', 1)->get();  // Fetch all active platforms
        $activePlatforms = PlatformResource::collection($platforms);
        return jsonResponse('Platforms fetched successfully', 200, $activePlatforms);
    }
}
