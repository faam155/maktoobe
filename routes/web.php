<?php

use App\Livewire\Foundation;
use Illuminate\Support\Facades\Route;

Route::get('/', Foundation::class)->name('foundation');

require __DIR__.'/auth.php';
require __DIR__.'/admin.php';

// Unimplemented business modules remain unavailable.
Route::fallback(fn () => abort(404));
