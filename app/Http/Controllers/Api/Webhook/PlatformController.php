<?php

namespace App\Http\Controllers\Api\Webhook;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\StorePlatformRequest;
use App\Http\Requests\Platform\UpdatePlatformRequest;
use App\Http\Resources\Webhook\PlatformResource;
use App\Models\Platform;
use Illuminate\Http\Request;

class PlatformController extends Controller
{
    public function index(Request $request)
    {
        $data       = $request->all();
        $pagination = !isset($data['pagination']) || $data['pagination'] === 'true' ? true : false;
        $page       = $data['page'] ?? 1;
        $perPage    = $data['per_page'] ?? 10;
        $searchText = $data['search'] ?? null;
        $searchBy   = $data['search_by'] ?? 'name';
        $sortBy     = $data['sort_by'] ?? 'id';
        $sortOrder  = $data['sort_order'] ?? 'asc';

        $query = Platform::query();

        if ($searchText && $searchBy) {
            $query->where($searchBy, 'like', "%{$searchText}%");
        }

        $query->orderBy($sortBy, $sortOrder);

        if ($pagination) {
            $platforms = $query->paginate($perPage, ['*'], 'page', $page);
            return jsonResponseWithPagination('Platform list retrieved successfully', true, PlatformResource::collection($platforms)->response()->getData(true));
        }

        return jsonResponse('Platform list retrieved successfully', true, PlatformResource::collection($query->get()));
    }

    public function store(StorePlatformRequest $request)
    {
        $platform = Platform::create($request->validated());
        return jsonResponse('Platform created successfully', true, new PlatformResource($platform));
    }

    public function show($platformId)
    {
        $platform = Platform::find($platformId);
        if (!$platform) {
            return jsonResponse('Platform not found', false);
        }

        return jsonResponse('Platform retrieved successfully', true, new PlatformResource($platform));
    }

    public function update(UpdatePlatformRequest $request, $platformId)
    {
        $platform = Platform::find($platformId);
        if (!$platform) {
            return jsonResponse('Platform not found', false);
        }

        $platform->update($request->validated());
        return jsonResponse('Platform updated successfully', true, new PlatformResource($platform));
    }

    public function destroy($platformId)
    {
        $platform = Platform::find($platformId);
        if (!$platform) {
            return jsonResponse('Platform not found', false);
        }
        $platform->delete();
        return jsonResponse('Platform deleted successfully', true);
    }
}