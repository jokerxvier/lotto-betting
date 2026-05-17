<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Draw;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validates the admin publishing form. The Draw is route-model-bound, so
 * the picks must satisfy the bound draw's game (count + range). Refuses
 * re-publish of a draw that already has a DrawResult.
 */
final class PublishDrawResultRequest extends FormRequest
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
            'numbers' => ['required', 'array'],
            'numbers.*' => ['required', 'integer'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            /** @var Draw|null $draw */
            $draw = $this->route('draw');
            if (! $draw instanceof Draw) {
                return;
            }

            $draw->loadMissing('game', 'result');
            $game = $draw->game;

            if ($draw->result !== null) {
                $v->errors()->add('numbers', 'This draw is already settled.');

                return;
            }

            /** @var array<int, mixed> $nums */
            $nums = (array) $this->input('numbers', []);

            if (count($nums) !== $game->picks_count) {
                $v->errors()->add(
                    'numbers',
                    "Expected {$game->picks_count} number(s).",
                );

                return;
            }

            foreach ($nums as $i => $n) {
                // The `integer` rule above ensures $n is integer-shaped
                // (int or numeric string). Coerce here before the range
                // check so HTML form submissions (always strings) work.
                if (! is_numeric($n)) {
                    continue; // top-level rule already added an error
                }
                $intVal = (int) $n;
                if ($intVal < $game->number_min || $intVal > $game->number_max) {
                    $v->errors()->add(
                        "numbers.$i",
                        "Pick out of range [{$game->number_min}, {$game->number_max}].",
                    );
                }
            }
        });
    }
}
