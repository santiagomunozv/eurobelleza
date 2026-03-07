<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\SiesaGeneralConfigurationController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth'])->name('dashboard');

Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');

    // Configuración general de SIESA
    Route::get('/siesa/configuration', [SiesaGeneralConfigurationController::class, 'edit'])
        ->name('siesa.configuration.edit');
    Route::put('/siesa/configuration', [SiesaGeneralConfigurationController::class, 'update'])
        ->name('siesa.configuration.update');
});

require __DIR__ . '/auth.php';
