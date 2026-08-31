<?php

namespace App\Livewire;

use App\Enums\Locale;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Foundation extends Component
{
    public string $locale = 'en';

    public function mount(): void
    {
        $this->locale = app()->getLocale();
    }

    public function saveLocale(): void
    {
        // This public preference can change only the caller's session, never a user record.
        $key = 'locale-preference:'.hash('sha256', session()->getId());

        if (RateLimiter::tooManyAttempts($key, 20)) {
            $this->addError('locale', __('foundation.language_throttled'));

            return;
        }

        RateLimiter::hit($key, 60);

        $this->validate([
            'locale' => ['required', 'string', Rule::enum(Locale::class)],
        ]);

        session()->put('locale', $this->locale);
        session()->flash('locale_updated', true);

        // A full navigation updates the document language, direction and all UI together.
        $this->redirectRoute('foundation', navigate: false);
    }

    public function render(): View
    {
        return view('livewire.foundation')->layout('components.layouts.app');
    }
}
