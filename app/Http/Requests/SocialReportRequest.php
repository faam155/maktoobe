<?php

namespace App\Http\Requests;

use App\Actions\Social\SaveSocialReport;
use Illuminate\Foundation\Http\FormRequest;

class SocialReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('linkedin.export') ?? false;
    }

    public function rules(): array
    {
        return SaveSocialReport::rules();
    }
}
