<?php
use App\Http\Controllers\MovimientoController;
use Illuminate\Support\Facades\Route;

Route::post('/movimientos/import', [MovimientoController::class, 'import']);
Route::get('/movimientos/meses', [MovimientoController::class, 'meses']);
Route::get('/movimientos/resumen', [MovimientoController::class, 'resumen']);
Route::get('/movimientos', [MovimientoController::class, 'index']);
Route::post('/movimientos', [MovimientoController::class, 'store']);
Route::patch('/movimientos/{id}/categoria', [MovimientoController::class, 'updateCategoria']);
Route::get('/categoria-reglas', [MovimientoController::class, 'reglas']);
Route::post('/categoria-reglas', [MovimientoController::class, 'storeRegla']);
Route::delete('/categoria-reglas/{id}', [MovimientoController::class, 'destroyRegla']);
