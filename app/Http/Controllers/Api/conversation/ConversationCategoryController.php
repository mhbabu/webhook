<?php

namespace App\Http\Controllers\Api\conversation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Conversation\StoreCategoryRequest;
use App\Http\Requests\Conversation\UpdateCategoryRequest;
use App\Models\ConversationCategory;
use Illuminate\Http\JsonResponse;

class ConversationCategoryController extends Controller
{
    /**
     * Display a listing of disposition categories with subcategories.
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(): JsonResponse
    {
        $categories = ConversationCategory::where('is_active', true)
            ->with(['subCategories' => function ($query) {
                $query->where('is_active', true)
                    ->orderBy('name')
                    ->select('id', 'conversation_category_id', 'name');
            }])
            ->orderBy('name')
            ->get(['id', 'name']);

        $data = $categories->map(function ($category) {
            return [
                'id' => $category->id,
                'name' => $category->name,
                'subcategories' => $category->subCategories->map(function ($sub) {
                    return [
                        'id' => $sub->id,
                        'name' => $sub->name
                    ];
                })
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Store a newly created category.
     * 
     * @param  \App\Http\Requests\Conversation\StoreCategoryRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = ConversationCategory::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Category created successfully',
            'data' => [
                'id' => $category->id,
                'name' => $category->name
            ]
        ], 201);
    }

    /**
     * Display the specified category.
     * 
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id): JsonResponse
    {
        $category = ConversationCategory::with(['subCategories' => function ($query) {
                $query->where('is_active', true)
                    ->orderBy('name')
                    ->select('id', 'conversation_category_id', 'name');
            }])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $category->id,
                'name' => $category->name,
                'is_active' => $category->is_active,
                'subcategories' => $category->subCategories->map(function ($sub) {
                    return [
                        'id' => $sub->id,
                        'name' => $sub->name
                    ];
                })
            ]
        ]);
    }

    /**
     * Update the specified category.
     * 
     * @param  \App\Http\Requests\Conversation\UpdateCategoryRequest  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateCategoryRequest $request, $id): JsonResponse
    {
        $category = ConversationCategory::findOrFail($id);
        $category->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Category updated successfully',
            'data' => [
                'id' => $category->id,
                'name' => $category->name
            ]
        ]);
    }

    /**
     * Remove the specified category.
     * 
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        $category = ConversationCategory::findOrFail($id);
        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Category deleted successfully'
        ]);
    }
}
