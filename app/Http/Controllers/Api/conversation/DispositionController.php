<?php

namespace App\Http\Controllers\Api\conversation;

use App\Http\Controllers\Controller;
use App\Models\Disposition;
use Illuminate\Http\Request;

class DispositionController extends Controller
{
    public function index()
    {
        return jsonResponse(
            'Categories fetched',
            true,
            Disposition::withCount('subCategories')->get()
        );
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:conversation_categories,name',
        ]);

        Disposition::create($request->only('name'));

        return jsonResponse('Category created', true);
    }
}
