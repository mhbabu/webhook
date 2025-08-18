<?php

namespace App\Http\Controllers\Api\Webhook;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\Platform\StorePlatformRequest;
use App\Http\Resources\Webhook\PlatformResource;
use App\Models\Platform;
use Illuminate\Http\Request;

class PlatformController extends Controller
{
    public function index()
    {
        $platforms = Platform::get();
        return jsonResponse('User platforms retrieved successfully', true, PlatformResource::collection($platforms));
    }

    public function show(Platform $platform)
    {
        if (!$platform) {
            return jsonResponse('User platform not found', false);
        }

        return jsonResponse('User platform retrieved successfully', true, new PlatformResource($platform));
    }

    public function store(StorePlatformRequest $request)
    {
        $platform = Platform::create($request->validated());
        return jsonResponse('User platform created successfully', true, new PlatformResource($platform));
    }

    public function update(Request $request, Platform $platform)
    {
        if (!$platform) {
            return jsonResponse('User platform not found', false);
        }

        $platform->update($request->validated());
        return jsonResponse('User platform updated successfully', true, new PlatformResource($platform));
    }

    public function destroy(Platform $platform)
    {
        if (!$platform) {
            return jsonResponse('User platform not found', false);
        }
        $platform->delete();
        return jsonResponse('User platform deleted successfully', true);
    }
}
