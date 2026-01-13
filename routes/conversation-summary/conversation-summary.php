<?php

use App\Http\Controllers\Api\Conversation\ConversationCategoryController;
use App\Http\Controllers\Api\Conversation\ConversationSubCategoryController;
use App\Http\Controllers\Api\Conversation\ConversationTypeController;
use Illuminate\Support\Facades\Route;

Route::prefix('/conversations')->group(function () {

    // conversation types
    Route::get('/types', [ConversationTypeController::class, 'index']);
    Route::post('/types', [ConversationTypeController::class, 'store']);
    Route::put('/types/{id}', [ConversationTypeController::class, 'update']);
    Route::delete('/types/{id}', [ConversationTypeController::class, 'destroy']);

    // categories
    Route::get('/categories', [ConversationCategoryController::class, 'index']);
    Route::post('/categories', [ConversationCategoryController::class, 'store']);
    Route::put('/categories/{id}', [ConversationCategoryController::class, 'update']);
    Route::delete('/categories/{id}', [ConversationCategoryController::class, 'destroy']);

    // sub-categories
    Route::get('/category/sub-categories', [ConversationSubCategoryController::class, 'subCategoryIndex']);
    Route::post('/category/sub-categories', [ConversationSubCategoryController::class, 'subCategoryStore']);
    Route::put('/category/sub-categories/{id}', [ConversationSubCategoryController::class, 'subCategoryUpdate']);
    Route::delete('/category/sub-categories/{id}', [ConversationSubCategoryController::class, 'subCategoryDestroy']);
});
