<?php

namespace App\Http\Controllers\Api\conversation;

use App\Http\Controllers\Controller;
use App\Models\ConversationType;
use Illuminate\Http\Request;

class ConversationTypeController extends Controller
{
    public function index()
    {
        return jsonResponse(
            'Conversation types fetched',
            true,
            ConversationType::orderBy('name')->get()
        );
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:conversation_types,name',
        ]);

        ConversationType::create($request->only('name'));

        return jsonResponse('Conversation type created', true);
    }

    public function update(Request $request, $id)
    {
        $type = ConversationType::findOrFail($id);

        $request->validate([
            'name' => "required|unique:conversation_types,name,$id",
        ]);

        $type->update($request->only('name'));

        return jsonResponse('Conversation type updated', true);
    }

    public function destroy($id)
    {
        ConversationType::findOrFail($id)->delete();

        return jsonResponse('Conversation type deleted', true);
    }
}
