<?php
// routes/api.php

use App\Http\Controllers\UserController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

// ========== PUBLIC ROUTES ==========

// CSRF Cookie (requis pour Sanctum stateful)
Route::get('/sanctum/csrf-cookie', function () {
    return response()->json(['message' => 'CSRF cookie set']);
});

// Auth routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// ========== PROTECTED ROUTES ==========

Route::middleware(['auth:sanctum'])->group(function () {
    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'user']); // ✅ /me au lieu de /user
    
    // Users (CRUD protégé)
    Route::get('/users', [UserController::class, 'index']); // Liste (admin only)
    Route::get('/users/{id}', [UserController::class, 'show']); // Voir un user
    Route::patch('/users/{id}', [UserController::class, 'update']); // Modifier
    Route::delete('/users/{id}', [UserController::class, 'destroy']); // Supprimer (admin only)
});