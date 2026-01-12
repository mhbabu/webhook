<?php

namespace App\Http\Controllers\Api\conversation;

use App\Http\Controllers\Controller;
use App\Models\ConversationCategory;
use Illuminate\Http\Request;

class ConversationCategoryController extends Controller
{
    public function index()
    {
        return jsonResponse(
            'Categories fetched',
            true,
            ConversationCategory::withCount('subCategories')->get()
        );
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:conversation_categories,name',
        ]);

        ConversationCategory::create($request->only('name'));

        return jsonResponse('Category created', true);
    }
}
