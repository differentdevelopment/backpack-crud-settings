<?php

use Pxpm\BpSettings\app\Http\Controllers\Admin\BpSettingsCrudController;
use Illuminate\Support\Facades\Route;

Route::group([
    'prefix'     => config('backpack.base.route_prefix', 'admin'),
    'middleware' => ['web', config('backpack.base.middleware_key', 'admin')],
], function () { 
    Route::post(config('bpsettings.settings_route_prefix', 'bp-settings').'/save', [BpSettingsCrudController::class, 'save']);
    Route::get(config('bpsettings.settings_route_prefix', 'bp-settings').'/{namespace?}', [BpSettingsCrudController::class, 'index']);
});
