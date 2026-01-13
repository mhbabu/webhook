<?php

use App\Http\Controllers\Api\Conversation\ConversationCategoryController;
use App\Http\Controllers\Api\Conversation\ConversationSubCategoryController;
use App\Http\Controllers\Api\Conversation\ConversationTypeController;
use Illuminate\Support\Facades\Route;

// Disposition System APIs - Full CRUD Operations

// Interaction Types (Conversation Types)
Route::prefix('/interaction-types')->group(function () {
    Route::get('/', [ConversationTypeController::class, 'index']);
    Route::post('/', [ConversationTypeController::class, 'store']);
    Route::get('/{id}', [ConversationTypeController::class, 'show']);
    Route::put('/{id}', [ConversationTypeController::class, 'update']);
    Route::delete('/{id}', [ConversationTypeController::class, 'destroy']);
});

// Disposition Categories
Route::prefix('/disposition-categories')->group(function () {
    Route::get('/', [ConversationCategoryController::class, 'index']);
    Route::post('/', [ConversationCategoryController::class, 'store']);
    Route::get('/{id}', [ConversationCategoryController::class, 'show']);
    Route::put('/{id}', [ConversationCategoryController::class, 'update']);
    Route::delete('/{id}', [ConversationCategoryController::class, 'destroy']);
});

// Disposition Subcategories
Route::prefix('/disposition-subcategories')->group(function () {
    Route::get('/', [ConversationSubCategoryController::class, 'index']);
    Route::post('/', [ConversationSubCategoryController::class, 'store']);
    Route::get('/{id}', [ConversationSubCategoryController::class, 'show']);
    Route::put('/{id}', [ConversationSubCategoryController::class, 'update']);
    Route::delete('/{id}', [ConversationSubCategoryController::class, 'destroy']);
});
