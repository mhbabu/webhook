<?php

namespace App\Http\Controllers\Api\Conversation;

use App\Http\Controllers\Controller;
use App\Models\InteractionType;
use Illuminate\Http\Request;

class InteractionTypeController extends Controller
{
    public function index()
    {
        $types = InteractionType::orderBy('name')->get();

        return jsonResponse(
            'Interaction types fetched successfully',
            true,
            InteractionTypeResource::collection($types)
            // $types
        );
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:conversation_types,name',
        ]);

        InteractionType::create($request->only('name'));

        return jsonResponse('Conversation type created', true);
    }

    public function update(Request $request, $id)
    {
        $type = InteractionType::findOrFail($id);

        $request->validate([
            'name' => "required|unique:conversation_types,name,$id",
        ]);

        $type->update($request->only('name'));

        return jsonResponse('Conversation type updated', true);
    }

    public function destroy($id)
    {
        InteractionType::findOrFail($id)->delete();

        return jsonResponse('Conversation type deleted', true);
    }
}
