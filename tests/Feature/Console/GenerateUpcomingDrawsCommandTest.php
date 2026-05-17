<?php

declare(strict_types=1);

use App\Models\Draw;
use App\Models\Game;
use Database\Seeders\GameSeeder;

beforeEach(function (): void {
    $this->seed(GameSeeder::class);
});

it('seeds 7 days × default slots × active games on a fresh DB', function () {
    $this->artisan('draws:generate-upcoming', ['--days' => 7])->assertSuccessful();

    $activeGames = Game::query()->where('active', true)->count();
    /** @var list<string> $slots */
    $slots = (array) config('lotto.draw_schedule.default');

    expect(Draw::query()->count())->toBe($activeGames * 7 * count($slots));
});

it('is idempotent: a second run creates 0 new draws', function () {
    $this->artisan('draws:generate-upcoming', ['--days' => 7])->assertSuccessful();
    $before = Draw::query()->count();

    $this->artisan('draws:generate-upcoming', ['--days' => 7])->assertSuccessful();

    expect(Draw::query()->count())->toBe($before);
});

it('skips inactive games', function () {
    Game::query()->update(['active' => false]);
    Game::query()->where('code', '2d')->update(['active' => true]);

    $this->artisan('draws:generate-upcoming', ['--days' => 3])->assertSuccessful();

    /** @var list<string> $slots */
    $slots = (array) config('lotto.draw_schedule.default');
    expect(Draw::query()->count())->toBe(3 * count($slots));
});

it('honors the --days flag', function () {
    $this->artisan('draws:generate-upcoming', ['--days' => 2])->assertSuccessful();

    $activeGames = Game::query()->where('active', true)->count();
    /** @var list<string> $slots */
    $slots = (array) config('lotto.draw_schedule.default');

    expect(Draw::query()->count())->toBe($activeGames * 2 * count($slots));
});

it('writes scheduled status + cutoff_at = draw_at - cutoff_minutes', function () {
    $cutoff = (int) config('lotto.cutoff_minutes');
    $this->artisan('draws:generate-upcoming', ['--days' => 1])->assertSuccessful();

    foreach (Draw::query()->cursor() as $draw) {
        expect($draw->status)->toBe('scheduled')
            ->and($draw->draw_at->diffInMinutes($draw->cutoff_at, true))->toBe((float) $cutoff);
    }
});

it('fails cleanly if the default schedule is empty', function () {
    config()->set('lotto.draw_schedule', ['default' => []]);

    $this->artisan('draws:generate-upcoming')->assertFailed();
    expect(Draw::query()->count())->toBe(0);
});
