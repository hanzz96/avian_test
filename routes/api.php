<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MachineController;
use App\Http\Controllers\ProductionOrderController;
use App\Http\Controllers\ProductionResultController;
use Illuminate\Support\Facades\Route;

Route::get('/dashboard', [DashboardController::class, 'index']);
Route::get('/dashboard/machine/{id}', [DashboardController::class, 'machine']);
Route::get('/production-orders', [ProductionOrderController::class, 'index']);
Route::post('/production-results', [ProductionResultController::class, 'store']);
Route::get('/machines/{id}/work-orders', [MachineController::class, 'workOrders']);
