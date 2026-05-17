<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Auth\Username;
use App\Rules\ComplexPin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class SetupPinRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ($user->username === null || $user->pin_hash === null);
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('username')) {
            $this->merge([
                'username' => Str::lower(trim((string) $this->input('username'))),
            ]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'username' => [
                'required', 'string', 'lowercase',
                'regex:'.Username::REGEX,
                Rule::notIn(Username::RESERVED),
                Rule::unique('users', 'username')->ignore($this->user()?->id),
            ],
            'pin' => ['required', 'string', 'confirmed', new ComplexPin(min: 6, max: 6)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'username.regex' => 'Username must be 3-32 characters: lowercase letters, digits, or underscore.',
            'username.not_in' => 'That username is reserved. Please choose another.',
            'username.unique' => 'That username is taken.',
            'pin.confirmed' => 'PIN and confirmation do not match.',
        ];
    }
}
