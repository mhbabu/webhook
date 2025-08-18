<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\Category\StoreUserCategoryRequest;
use App\Http\Requests\User\Category\UpdateUserCategoryRequest;
use App\Http\Resources\UserCategoryResource;
use App\Models\UserCategory;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Http\Request;

class UserCategoryController extends Controller
{
    public function index()
    {
        $categories = UserCategory::get();
        return jsonResponse('User categories retrieved successfully', true, UserCategoryResource::collection($categories));
    }

    public function store(StoreUserCategoryRequest $request)
    {
        $category = UserCategory::create($request->validated());
        return jsonResponse('User category created successfully', true, new UserCategoryResource($category));
    }

    public function show(UserCategory $category)
    {
        if (empty($category))
            return jsonResponse('User category not found', false);

        return jsonResponse('User category retrieved successfully', true, new UserCategoryResource($category));
    }

    public function update(UpdateUserCategoryRequest $request, UserCategory $category)
    {
        $category->update($request->validated());
        if (empty($category))
            return jsonResponse('User category not found', false);

        return jsonResponse('User category updated successfully', true, new UserCategoryResource($category));
    }

    public function destroy(UserCategory $category)
    {
        if (!empty($category)) {
            return jsonResponse('Cannot delete category with associated users', false, null, 400);
        }
        $category->delete();
        return jsonResponse('User category deleted successfully', true);
    }
}
