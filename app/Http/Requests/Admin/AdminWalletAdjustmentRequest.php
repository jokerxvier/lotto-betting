<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

final class AdminWalletAdjustmentRequest extends FormRequest
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
            'amount.regex' => 'Amount must be a decimal string like "100.00".',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $target = $this->route('user');
            $actor = $this->user();

            if ($target instanceof User && $actor !== null && $target->id === $actor->id) {
                $validator->errors()->add('amount', 'Admins cannot adjust their own wallet.');
            }
        });
    }
}
