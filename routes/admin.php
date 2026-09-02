<?php

use App\Http\Controllers\Admin\EventController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'no-scanner', 'role:admin'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::resource('events', EventController::class)->except('destroy');
});
