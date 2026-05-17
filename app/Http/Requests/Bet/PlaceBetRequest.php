<?php

declare(strict_types=1);

namespace App\Http\Requests\Bet;

use App\Models\Draw;
use App\Models\GameBetType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class PlaceBetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'draw_id' => ['required', 'integer', Rule::exists('draws', 'id')],
            'idempotency_key' => ['required', 'uuid'],
            'legs' => ['required', 'array', 'min:1', 'max:1'],
            'legs.*.game_bet_type_id' => [
                'required',
                'integer',
                Rule::exists('game_bet_types', 'id')->where('active', true),
            ],
            'legs.*.numbers' => ['required', 'array'],
            'legs.*.numbers.*' => ['required', 'integer'],
            'legs.*.amount' => ['required', 'string', 'regex:/^\d{1,12}\.\d{2}$/'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $draw = Draw::query()->find($this->input('draw_id'));
            if ($draw === null) {
                return; // the `exists` rule will already have failed
            }

            if ($draw->status !== 'scheduled' || $draw->cutoff_at->lessThanOrEqualTo(now())) {
                $v->errors()->add('draw_id', 'Draw is closed.');
            }

            foreach ((array) $this->input('legs', []) as $i => $leg) {
                $type = GameBetType::query()
                    ->with('game')
                    ->find($leg['game_bet_type_id'] ?? null);
                if ($type === null) {
                    continue;
                }

                if ($type->game_id !== $draw->game_id) {
                    $v->errors()->add("legs.$i.game_bet_type_id", 'Bet type does not match the draw.');
                }

                $game = $type->game;
                $nums = $leg['numbers'] ?? [];
                if (count($nums) !== $game->picks_count) {
                    $v->errors()->add(
                        "legs.$i.numbers",
                        "Expected {$game->picks_count} picks.",
                    );
                }
                foreach ($nums as $n) {
                    if (! is_int($n) || $n < $game->number_min || $n > $game->number_max) {
                        $v->errors()->add(
                            "legs.$i.numbers",
                            "Pick out of range [{$game->number_min}, {$game->number_max}].",
                        );
                    }
                }

                $amount = (string) ($leg['amount'] ?? '0.00');
                if (
                    bccomp($amount, (string) $type->min_bet, 2) < 0
                    || bccomp($amount, (string) $type->max_bet, 2) > 0
                ) {
                    $v->errors()->add(
                        "legs.$i.amount",
                        "Amount must be between ₱{$type->min_bet} and ₱{$type->max_bet}.",
                    );
                }
            }
        });
    }
}
