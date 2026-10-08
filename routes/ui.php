<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Phattarachai\OpsLaravel\Http\Controllers\IncidentsController;
use Phattarachai\OpsLaravel\Http\Controllers\JobsController;
use Phattarachai\OpsLaravel\Http\Controllers\OutgoingController;
use Phattarachai\OpsLaravel\Http\Controllers\OverviewController;
use Phattarachai\OpsLaravel\Http\Controllers\RefreshController;
use Phattarachai\OpsLaravel\Http\Controllers\RequestsController;
use Phattarachai\OpsLaravel\Http\Controllers\RulesController;
use Phattarachai\OpsLaravel\Http\Controllers\SchedulesController;
use Phattarachai\OpsLaravel\Http\Controllers\SettingsController;
use Phattarachai\OpsLaravel\Http\Controllers\SystemController;

Route::get('/', [OverviewController::class, 'index'])->name('overview');
Route::post('cache/refresh', RefreshController::class)->name('refresh');

Route::get('requests', [RequestsController::class, 'index'])->name('requests');
Route::get('requests/{token}', [RequestsController::class, 'show'])->name('requests.show');

Route::get('outgoing', [OutgoingController::class, 'index'])->name('outgoing');
Route::get('outgoing/{token}', [OutgoingController::class, 'show'])->name('outgoing.show');

Route::get('jobs', [JobsController::class, 'index'])->name('jobs');
Route::get('jobs/{token}', [JobsController::class, 'show'])->name('jobs.show');

Route::get('schedules', [SchedulesController::class, 'index'])->name('schedules');
Route::get('schedules/{token}', [SchedulesController::class, 'show'])->name('schedules.show');

Route::get('system', SystemController::class)->name('system');

Route::get('incidents', [IncidentsController::class, 'index'])->name('incidents');
Route::get('incidents/{token}', [IncidentsController::class, 'show'])->name('incidents.show')->whereNumber('token');
Route::post('incidents/{incident}/resolve', [IncidentsController::class, 'resolve'])->name('incidents.resolve')->whereNumber('incident');

Route::get('rules', [RulesController::class, 'index'])->name('rules');
Route::post('rules', [RulesController::class, 'store'])->name('rules.store');
Route::put('rules/{rule}', [RulesController::class, 'update'])->name('rules.update')->whereNumber('rule');
Route::delete('rules/{rule}', [RulesController::class, 'destroy'])->name('rules.destroy')->whereNumber('rule');
Route::post('rules/defaults', [RulesController::class, 'seedDefaults'])->name('rules.defaults');

Route::get('settings', [SettingsController::class, 'index'])->name('settings');
Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
Route::post('settings/test/{channel}', [SettingsController::class, 'test'])->name('settings.test');
