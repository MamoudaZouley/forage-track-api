<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\WellController;
use App\Http\Controllers\Api\SupervisionController;
use App\Http\Controllers\Api\AlertController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\Api\MaintenanceController;

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
    Route::get('/supervisions/supervisor-stats', [SupervisionController::class, 'supervisorStats']);
    Route::get('/supervisions/kpi', [SupervisionController::class, 'kpiSupervisors']);
    Route::get('/wells/{well}', [WellController::class, 'show']);
    Route::get('/wells/{well}/supervisions', [SupervisionController::class, 'byWell']);

    // Supervisions
    Route::get('/maintenances/export', [MaintenanceController::class, 'export']);
    Route::get('/maintenances/export-data', [MaintenanceController::class, 'exportData']);
    Route::get('/supervisions', [SupervisionController::class, 'index']);
    Route::get('/supervisions/{supervision}', [SupervisionController::class, 'show']);
    Route::get('/wells/{well}/maintenances', [MaintenanceController::class, 'byWell']);
    Route::get('/alerts/wells-status', [AlertController::class, 'wellsStatus']);
    
    // Alertes
    Route::get('/alerts', [AlertController::class, 'index']);
    Route::get('/alerts/{alert}', [AlertController::class, 'show']);
    Route::patch('/alerts/{alert}/resolve', [AlertController::class, 'resolve']);

    // Utilisateurs (admin seulement)
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::put('/users/{user}', [UserController::class, 'update']);
    Route::delete('/users/{user}', [UserController::class, 'destroy']);


    Route::post('/sync', [SyncController::class, 'sync']);
    Route::get('/sync/status', [SyncController::class, 'status']);
    Route::get('/maintenances/technician-stats', [MaintenanceController::class, 'technicianStats']);
    Route::get('/maintenances/stats', [MaintenanceController::class, 'stats']);

    // Maintenances
    Route::get('/maintenances', [MaintenanceController::class, 'index']);
    Route::get('/maintenances/{maintenance}', [MaintenanceController::class, 'show']);
   
   
});