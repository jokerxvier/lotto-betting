<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Admin settings update. Two toggles:
 *  - scraper.suggestions_enabled — low risk; flipped freely
 *  - scraper.auto_publish_enabled — high risk; requires the admin to
 *    re-confirm via `confirm_auto_publish` in the same payload (the
 *    React UI gates this behind an AlertDialog that asks the admin to
 *    type "AUTO-PUBLISH").
 */
final class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && (bool) $this->user()->is_admin === true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'suggestions_enabled' => ['required', 'boolean'],
            'auto_publish_enabled' => ['required', 'boolean'],
            'confirm_auto_publish' => [
                Rule::requiredIf(fn (): bool => (bool) $this->boolean('auto_publish_enabled')),
                'accepted_if:auto_publish_enabled,true',
            ],
        ];
    }
}
