<?php

/*
|--------------------------------------------------------------------------
| Astimpay Routes
|--------------------------------------------------------------------------
|
| Here is where you can register admin routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
 */

//Astimpay Start
Route::controller(App\Http\Controllers\Payment\AstimpayController::class)->group(function () {
    Route::any('/astimpay/success', 'success')->name('astimpay.success');
    Route::any('/astimpay/cancel', 'cancel')->name('astimpay.cancel');
    Route::any('/astimpay/ipn', 'ipn')->name('astimpay.ipn');
});
//Astimpay end
