<?php

use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CalendarPdfController;
use App\Http\Controllers\CalendarSourceController;
use App\Http\Controllers\ManualEventController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [CalendarController::class, 'index'])->name('dashboard');
    Route::get('calendars', [CalendarController::class, 'index'])->name('calendars.index');
    Route::get('calendars/create', [CalendarController::class, 'create'])->name('calendars.create');
    Route::post('calendars', [CalendarController::class, 'store'])->name('calendars.store');
    Route::get('calendars/{calendar}/edit', [CalendarController::class, 'edit'])->name('calendars.edit');
    Route::put('calendars/{calendar}', [CalendarController::class, 'update'])->name('calendars.update');
    Route::post('calendars/{calendar}/sources', [CalendarSourceController::class, 'store'])->name('calendar-sources.store');
    Route::put('calendars/{calendar}/sources/{source}', [CalendarSourceController::class, 'update'])->name('calendar-sources.update');
    Route::post('calendars/{calendar}/sources/{source}/refresh', [CalendarSourceController::class, 'refresh'])->name('calendar-sources.refresh');
    Route::delete('calendars/{calendar}/sources/{source}', [CalendarSourceController::class, 'destroy'])->name('calendar-sources.destroy');
    Route::post('calendars/{calendar}/events', [ManualEventController::class, 'store'])->name('manual-events.store');
    Route::put('calendars/{calendar}/events/{event}', [ManualEventController::class, 'update'])->name('manual-events.update');
    Route::delete('calendars/{calendar}/events/{event}', [ManualEventController::class, 'destroy'])->name('manual-events.destroy');
});

Route::get('c/{calendar}', [CalendarController::class, 'show'])->name('calendars.show');
Route::get('c/{calendar}/calendar.pdf', CalendarPdfController::class)->name('calendars.pdf');

require __DIR__.'/settings.php';
