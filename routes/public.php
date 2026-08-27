<?php

use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\EventController;
use Illuminate\Support\Facades\Route;

Route::get('events/{event:slug}', [EventController::class, 'show'])->name('events.show');
Route::post('events/{event:slug}/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
Route::get('checkout/{order:confirmation_token}/confirmation', [CheckoutController::class, 'confirmation'])->name('checkout.confirmation');
Route::get('checkout/{order:confirmation_token}/ticket-pdf', [CheckoutController::class, 'ticketPdf'])->name('checkout.ticket-pdf');
