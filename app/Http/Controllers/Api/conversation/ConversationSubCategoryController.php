<?php

namespace App\Http\Controllers\Api\conversation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Conversation\StoreSubCategoryRequest;
use App\Http\Requests\Conversation\UpdateSubCategoryRequest;
use App\Models\ConversationSubCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationSubCategoryController extends Controller
{
    /**
     * Display a listing of subcategories (all or filtered by category).
     * 
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $query = ConversationSubCategory::where('is_active', true)
            ->with('category:id,name')
            ->orderBy('name');

        // Optional filter by categoryId
        if ($request->has('categoryId')) {
            $request->validate([
                'categoryId' => 'exists:conversation_categories,id'
            ]);
            $query->where('conversation_category_id', $request->categoryId);
        }

        $subcategories = $query->get(['id', 'conversation_category_id', 'name']);

        $data = $subcategories->map(function ($sub) {
            return [
                'id' => $sub->id,
                'name' => $sub->name,
                'categoryId' => $sub->conversation_category_id
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Store a newly created subcategory.
     * 
     * @param  \App\Http\Requests\Conversation\StoreSubCategoryRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreSubCategoryRequest $request): JsonResponse
    {
        $subcategory = ConversationSubCategory::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Subcategory created successfully',
            'data' => [
                'id' => $subcategory->id,
                'name' => $subcategory->name,
                'categoryId' => $subcategory->conversation_category_id
            ]
        ], 201);
    }

    /**
     * Display the specified subcategory.
     * 
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id): JsonResponse
    {
        $subcategory = ConversationSubCategory::with('category:id,name')
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $subcategory->id,
                'name' => $subcategory->name,
                'categoryId' => $subcategory->conversation_category_id,
                'category_name' => $subcategory->category->name,
                'is_active' => $subcategory->is_active
            ]
        ]);
    }

    /**
     * Update the specified subcategory.
     * 
     * @param  \App\Http\Requests\Conversation\UpdateSubCategoryRequest  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateSubCategoryRequest $request, $id): JsonResponse
    {
        $subcategory = ConversationSubCategory::findOrFail($id);
        $subcategory->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Subcategory updated successfully',
            'data' => [
                'id' => $subcategory->id,
                'name' => $subcategory->name,
                'categoryId' => $subcategory->conversation_category_id
            ]
        ]);
    }

    /**
     * Remove the specified subcategory.
     * 
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        $subcategory = ConversationSubCategory::findOrFail($id);
        $subcategory->delete();

        return response()->json([
            'success' => true,
            'message' => 'Subcategory deleted successfully'
        ]);
    }
}
