<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class AdminTopUpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'wallet_code' => ['required', 'string', 'size:8', 'exists:users,wallet_code'],
            'amount' => ['required', 'string', 'regex:/^\d{1,6}\.\d{2}$/'],
            'note' => ['nullable', 'string', 'max:255'],
            'idempotency_key' => ['required', 'string', 'min:8', 'max:128'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'wallet_code.size' => 'Wallet code must be 8 characters.',
            'wallet_code.exists' => 'No user with that wallet code.',
            'amount.regex' => 'Amount must be a decimal string like "100.00".',
        ];
    }
}
