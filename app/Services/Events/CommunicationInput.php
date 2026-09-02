<?php

namespace App\Services\Events;

use App\Models\EventCommunication;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CommunicationInput
{
    public function slot(array $input): array
    {
        return Validator::make($input, ['type' => ['required', Rule::in(EventCommunication::TYPES)], 'language' => ['required', Rule::in(EventCommunication::LANGUAGES)]])->validate();
    }

    public function revision(?EventCommunication $communication, int $expected): void
    {
        if (($communication?->revision_number ?? 0) !== $expected) {
            throw ValidationException::withMessages(['revision_number' => __('communications.conflict')]);
        }
    }
}
