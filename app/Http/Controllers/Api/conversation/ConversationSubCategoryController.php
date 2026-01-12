<?php

namespace App\Http\Controllers\Api\conversation;

use App\Http\Controllers\Controller;
use App\Models\ConversationSubCategory;
use Illuminate\Http\Request;

class ConversationSubCategoryController extends Controller
{
    public function subCategoryIndex(Request $request)
    {
        $request->validate([
            'category_id' => 'required|exists:conversation_categories,id',
        ]);

        return jsonResponse(
            'Sub-categories fetched',
            true,
            ConversationSubCategory::where(
                'conversation_category_id',
                $request->category_id
            )->get()
        );
    }

    public function subCategoryStore(Request $request)
    {
        $request->validate([
            'conversation_category_id' => 'required|exists:conversation_categories,id',
            'name' => 'required',
        ]);

        ConversationSubCategory::create(
            $request->only('conversation_category_id', 'name')
        );

        return jsonResponse('Sub-category created', true);
    }

    public function subCategoryDestroy($id)
    {
        ConversationSubCategory::findOrFail($id)->delete();

        return jsonResponse('Sub-category deleted', true);
    }
}
