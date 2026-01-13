<?php

namespace App\Http\Controllers\Api\conversation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Conversation\StoreConversationTypeRequest;
use App\Http\Requests\Conversation\UpdateConversationTypeRequest;
use App\Models\ConversationType;
use Illuminate\Http\JsonResponse;

class ConversationTypeController extends Controller
{
    /**
     * Display a listing of interaction types.
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(): JsonResponse
    {
        $types = ConversationType::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json([
            'success' => true,
            'data' => $types
        ]);
    }

    /**
     * Store a newly created interaction type.
     * 
     * @param  \App\Http\Requests\Conversation\StoreConversationTypeRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreConversationTypeRequest $request): JsonResponse
    {
        $type = ConversationType::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Interaction type created successfully',
            'data' => [
                'id' => $type->id,
                'name' => $type->name
            ]
        ], 201);
    }

    /**
     * Display the specified interaction type.
     * 
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id): JsonResponse
    {
        $type = ConversationType::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $type->id,
                'name' => $type->name,
                'is_active' => $type->is_active
            ]
        ]);
    }

    /**
     * Update the specified interaction type.
     * 
     * @param  \App\Http\Requests\Conversation\UpdateConversationTypeRequest  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateConversationTypeRequest $request, $id): JsonResponse
    {
        $type = ConversationType::findOrFail($id);
        $type->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Interaction type updated successfully',
            'data' => [
                'id' => $type->id,
                'name' => $type->name
            ]
        ]);
    }

    /**
     * Remove the specified interaction type.
     * 
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        $type = ConversationType::findOrFail($id);
        $type->delete();

        return response()->json([
            'success' => true,
            'message' => 'Interaction type deleted successfully'
        ]);
    }
}
