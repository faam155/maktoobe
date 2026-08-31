<?php

use App\Livewire\Foundation;
use Illuminate\Support\Facades\Route;

Route::get('/', Foundation::class)->name('foundation');

// Future feature routes remain unavailable, including direct requests to /app and /admin.
Route::fallback(fn () => abort(404));
