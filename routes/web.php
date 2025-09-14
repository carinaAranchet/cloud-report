<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ReporteController;


Route::get('/', function () {
    return view('welcome');
});


Route::get('/reporte', [ReporteController::class, 'index'])->name('reporte.index');
Route::get('/reporte/filters', [ReporteController::class, 'filters'])->name('reporte.filters');
Route::get('/reporte/data', [ReporteController::class, 'data'])->name('reporte.data');