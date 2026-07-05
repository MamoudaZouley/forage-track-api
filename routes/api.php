<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\WellController;
use App\Http\Controllers\Api\SupervisionController;
use App\Http\Controllers\Api\AlertController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

// Routes publiques
Route::post('/login', [AuthController::class, 'login']);

// Routes protégées
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Puits
    Route::get('/wells', [WellController::class, 'index']);
    Route::get('/wells/{well}', [WellController::class, 'show']);
    Route::get('/wells/{well}/supervisions', [SupervisionController::class, 'byWell']);

    // Supervisions
    Route::get('/supervisions', [SupervisionController::class, 'index']);
    Route::get('/supervisions/{supervision}', [SupervisionController::class, 'show']);

    // Alertes
    Route::get('/alerts', [AlertController::class, 'index']);
    Route::get('/alerts/{alert}', [AlertController::class, 'show']);
    Route::patch('/alerts/{alert}/resolve', [AlertController::class, 'resolve']);

    // Utilisateurs (admin seulement)
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::put('/users/{user}', [UserController::class, 'update']);
    Route::delete('/users/{user}', [UserController::class, 'destroy']);
});