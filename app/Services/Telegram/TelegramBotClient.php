<?php

declare(strict_types=1);

namespace App\Services\Telegram;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Thin wrapper around Telegram's Bot API `sendMessage` endpoint.
 *
 * Hard guarantees:
 *  - Never throws. Every failure path returns `false` + writes an
 *    `audit` log line with `chat_id`, `reason`, `status`. The bot
 *    token itself is never logged.
 *  - No-op (returns false) when `config('services.telegram.bot_token')`
 *    is empty so we can't accidentally POST without a token.
 *  - 8s HTTP timeout — Telegram's API is usually < 1s; longer means
 *    something's wrong and we shouldn't pin a queue worker.
 *
 * Driver-pattern: the listener decides WHAT to send; this service only
 * knows HOW to send. Tests for both halves use `Http::fake`.
 */
final class TelegramBotClient
{
    /**
     * @param  array<int, array<int, array<string, mixed>>>|null  $inlineKeyboard
     *                                                                             e.g. [[{text, web_app: {url}}]]
     */
    public function sendMessage(
        int $chatId,
        string $text,
        ?array $inlineKeyboard = null,
        string $parseMode = 'MarkdownV2',
    ): bool {
        $token = (string) config('services.telegram.bot_token');
        if ($token === '') {
            Log::channel('audit')->info('telegram.send.skipped', [
                'reason' => 'no_bot_token',
                'chat_id' => $chatId,
            ]);

            return false;
        }

        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => $parseMode,
            'disable_web_page_preview' => true,
        ];

        if ($inlineKeyboard !== null) {
            $payload['reply_markup'] = json_encode(
                ['inline_keyboard' => $inlineKeyboard],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            );
        }

        try {
            $response = Http::timeout(8)
                ->acceptJson()
                ->asJson()
                ->post(
                    "https://api.telegram.org/bot{$token}/sendMessage",
                    $payload,
                );
        } catch (Throwable $e) {
            Log::channel('audit')->info('telegram.send.failure', [
                'reason' => 'http_error',
                'message' => $e->getMessage(),
                'chat_id' => $chatId,
            ]);

            return false;
        }

        if ($response->failed()) {
            Log::channel('audit')->info('telegram.send.failure', [
                'reason' => 'upstream_status',
                'status' => $response->status(),
                'chat_id' => $chatId,
            ]);

            return false;
        }

        $json = $response->json();
        if (! is_array($json) || ($json['ok'] ?? false) !== true) {
            Log::channel('audit')->info('telegram.send.failure', [
                'reason' => 'upstream_not_ok',
                'description' => $json['description'] ?? null,
                'chat_id' => $chatId,
            ]);

            return false;
        }

        Log::channel('audit')->info('telegram.send.success', [
            'chat_id' => $chatId,
            'message_id' => $json['result']['message_id'] ?? null,
        ]);

        return true;
    }
}
