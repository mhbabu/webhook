<?php

namespace App\Http\Controllers\Api\Webhook;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\StorePlatformRequest;
use App\Http\Requests\Platform\UpdatePlatformRequest;
use App\Http\Resources\Webhook\PlatformResource;
use App\Models\Platform;

class PlatformController extends Controller
{
    public function index()
    {
        $platforms = Platform::get();
        return jsonResponse('Platform retrieved successfully', true, PlatformResource::collection($platforms));
    }

    public function store(StorePlatformRequest $request)
    {
        $platform = Platform::create($request->validated());
        return jsonResponse('Platform created successfully', true, new PlatformResource($platform));
    }

    public function show(Platform $platform)
    {
        if (!$platform) {
            return jsonResponse('Platform not found', false);
        }

        return jsonResponse('Platform retrieved successfully', true, new PlatformResource($platform));
    }

    public function update(UpdatePlatformRequest $request, Platform $platform)
    {
        if (!$platform) {
            return jsonResponse('Platform not found', false);
        }

        $platform->update($request->validated());
        return jsonResponse('Platform updated successfully', true, new PlatformResource($platform));
    }

    public function destroy(Platform $platform)
    {
        if (!$platform) {
            return jsonResponse('Platform not found', false);
        }
        $platform->delete();
        return jsonResponse('Platform deleted successfully', true);
    }
}
