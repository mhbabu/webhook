<?php

namespace App\Http\Controllers\Api\conversation;

use App\Http\Controllers\Controller;
use App\Models\SubDisposition;
use Illuminate\Http\Request;

class SubDispositionController extends Controller
{
    public function subCategoryIndex(Request $request)
    {
        $request->validate([
            'category_id' => 'required|exists:conversation_categories,id',
        ]);

        return jsonResponse(
            'Sub-categories fetched',
            true,
            SubDisposition::where(
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

        SubDisposition::create(
            $request->only('conversation_category_id', 'name')
        );

        return jsonResponse('Sub-category created', true);
    }

    public function subCategoryDestroy($id)
    {
        SubDisposition::findOrFail($id)->delete();

        return jsonResponse('Sub-category deleted', true);
    }
}
