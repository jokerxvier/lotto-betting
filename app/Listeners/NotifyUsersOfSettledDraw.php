<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\DrawSettled;
use App\Models\Bet;
use App\Models\User;
use App\Services\SettingsService;
use App\Services\Telegram\MarkdownV2;
use App\Services\Telegram\TelegramBotClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Queued listener: after a draw settles, DM every winning user via the bot.
 *
 * One message per user (NOT per leg) — a user with 5 winning bets on the
 * same draw gets ONE message with the summed payout. Losers and refunds
 * are silent in V1 (avoids spam). Two kill-switches guard the channel:
 *  - per-user `users.telegram_notifications_enabled` (default true)
 *  - global `SettingsService::get('telegram.push_enabled')` (default true)
 *  - bot token unset → no-op
 *
 * Queued so settlement returns instantly. Retries 3× with backoff so a
 * Telegram blip doesn't drop the message, but excess failures eventually
 * dead-letter (Wins are still in /tickets — the DM is a convenience).
 */
final class NotifyUsersOfSettledDraw implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 90, 300];

    public function __construct(
        private readonly TelegramBotClient $bot,
        private readonly SettingsService $settings,
    ) {}

    public function handle(DrawSettled $event): void
    {
        if ($this->settings->get('telegram.push_enabled', true) !== true) {
            Log::channel('audit')->info('telegram.send.skipped', [
                'reason' => 'globally_disabled',
                'draw_id' => $event->draw->id,
            ]);

            return;
        }

        if ((string) config('services.telegram.bot_token') === '') {
            Log::channel('audit')->info('telegram.send.skipped', [
                'reason' => 'no_bot_token',
                'draw_id' => $event->draw->id,
            ]);

            return;
        }

        $event->draw->loadMissing('game');

        /** @var iterable<Bet> $wonBets */
        $wonBets = Bet::query()
            ->where('draw_id', $event->draw->id)
            ->where('status', 'won')
            ->with(['user', 'legs'])
            ->get();

        // Group winning bets by user. Pre-summing per user means we
        // outbound one DM per user regardless of how many bets they had.
        /** @var array<int, array{user: User, bet_count: int, payout_cents: int}> $byUser */
        $byUser = [];
        foreach ($wonBets as $bet) {
            $user = $bet->user;
            if ($user === null) {
                continue;
            }
            $uid = $user->id;
            $byUser[$uid] ??= [
                'user' => $user,
                'bet_count' => 0,
                'payout_cents' => 0,
            ];
            $byUser[$uid]['bet_count']++;
            $payout = 0;
            foreach ($bet->legs as $leg) {
                if ($leg->payout !== null) {
                    $payout += (int) round(((float) $leg->payout) * 100);
                }
            }
            $byUser[$uid]['payout_cents'] += $payout;
        }

        foreach ($byUser as $bucket) {
            $user = $bucket['user'];

            if ($user->telegram_id === null) {
                continue;
            }
            if ($user->telegram_notifications_enabled !== true) {
                continue;
            }
            if ($bucket['payout_cents'] <= 0) {
                continue; // defensive — `won` with zero payout shouldn't happen
            }

            $text = $this->renderMessage(
                gameName: $event->draw->game->name,
                drawAt: $event->draw->draw_at->toDateTimeString(),
                drawTimeLabel: $event->draw->draw_at->format('g:i A'),
                betCount: $bucket['bet_count'],
                payoutDecimal: number_format($bucket['payout_cents'] / 100, 2, '.', ','),
            );

            $this->bot->sendMessage(
                chatId: $user->telegram_id,
                text: $text,
                inlineKeyboard: $this->openLottoKeyboard(),
            );
        }
    }

    private function renderMessage(
        string $gameName,
        string $drawAt,
        string $drawTimeLabel,
        int $betCount,
        string $payoutDecimal,
    ): string {
        $game = MarkdownV2::escape($gameName);
        $slot = MarkdownV2::escape($drawTimeLabel);
        $payout = MarkdownV2::escape($payoutDecimal);
        $betLine = $betCount === 1
            ? 'You had 1 winning bet on this draw\\.'
            : 'You had '.MarkdownV2::escape((string) $betCount).' winning bets on this draw\\.';

        return "🎉 *You won ₱{$payout}* in {$game} {$slot}\\!\n"
            .$betLine
            ."\nTap below to view your tickets\\.";
    }

    /**
     * @return array<int, array<int, array<string, mixed>>>|null
     */
    private function openLottoKeyboard(): ?array
    {
        $username = (string) config('services.telegram.bot_username');
        if ($username === '') {
            return null;
        }

        return [[
            [
                'text' => '📱 Open Lotto PH',
                'url' => "https://t.me/{$username}",
            ],
        ]];
    }
}
