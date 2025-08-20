<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\Category\StoreUserCategoryRequest;
use App\Http\Requests\User\Category\UpdateUserCategoryRequest;
use App\Http\Resources\User\UserCategoryResource;
use App\Models\UserCategory;

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

    public function show($categoryId)
    {
        $category = UserCategory::find($categoryId);
        if (empty($category))
            return jsonResponse('User category not found', false);

        return jsonResponse('User category retrieved successfully', true, new UserCategoryResource($category));
    }

    public function update(UpdateUserCategoryRequest $request, $categoryId)
    {
        $category = UserCategory::find($categoryId);
        if (empty($category))
            return jsonResponse('User category not found', false);
        $category->update($request->validated());
        return jsonResponse('User category updated successfully', true, new UserCategoryResource($category));
    }

    public function destroy($categoryId)
    {
        $category = UserCategory::find($categoryId);
        if (empty($category)) {
            return jsonResponse('User category not found', false);
        }
        $category->delete();
        return jsonResponse('User category deleted successfully', true);
    }
}
