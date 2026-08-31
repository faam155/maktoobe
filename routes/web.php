<?php

use App\Livewire\Foundation;
use Illuminate\Support\Facades\Route;

Route::get('/', Foundation::class)->name('foundation');

require __DIR__.'/auth.php';

// Administration and business modules remain unavailable.
Route::fallback(fn () => abort(404));
