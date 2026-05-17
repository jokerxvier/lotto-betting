<?php

declare(strict_types=1);

namespace App\Http\Requests\Bet;

use App\Models\Draw;
use App\Models\GameBetType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class PlaceCartRequest extends FormRequest
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
            'legs' => ['required', 'array', 'min:1', 'max:25'],
            'legs.*.leg_token' => ['required', 'uuid'],
            'legs.*.draw_id' => ['required', 'integer', Rule::exists('draws', 'id')],
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
            $tokens = [];
            foreach ((array) $this->input('legs', []) as $i => $leg) {
                $token = $leg['leg_token'] ?? null;
                if (is_string($token)) {
                    if (isset($tokens[$token])) {
                        $v->errors()->add("legs.$i.leg_token", 'Duplicate leg in cart.');
                    }
                    $tokens[$token] = true;
                }
            }

            foreach ((array) $this->input('legs', []) as $i => $leg) {
                $draw = Draw::query()->find($leg['draw_id'] ?? null);
                if ($draw === null) {
                    continue; // exists rule will already have failed
                }

                if ($draw->status !== 'scheduled' || $draw->cutoff_at->lessThanOrEqualTo(now())) {
                    $v->errors()->add("legs.$i.draw_id", 'Draw is closed.');

                    continue;
                }

                $type = GameBetType::query()
                    ->with('game')
                    ->find($leg['game_bet_type_id'] ?? null);
                if ($type === null) {
                    continue;
                }

                if ($type->game_id !== $draw->game_id) {
                    $v->errors()->add(
                        "legs.$i.game_bet_type_id",
                        'Bet type does not match the draw.',
                    );
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
