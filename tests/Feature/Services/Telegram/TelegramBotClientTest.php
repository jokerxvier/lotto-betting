<?php

declare(strict_types=1);

use App\Services\Telegram\TelegramBotClient;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config()->set('services.telegram.bot_token', 'TEST-BOT-TOKEN');
    $this->client = new TelegramBotClient;
});

it('POSTs the expected payload and returns true on Telegram OK', function () {
    Http::fake([
        'api.telegram.org/*' => Http::response([
            'ok' => true,
            'result' => ['message_id' => 42, 'chat' => ['id' => 12345]],
        ]),
    ]);

    $ok = $this->client->sendMessage(
        chatId: 12345,
        text: '🎉 *You won*',
        inlineKeyboard: [[['text' => 'Open', 'web_app' => ['url' => 'https://t.me/x/play']]]],
    );

    expect($ok)->toBeTrue();

    Http::assertSent(function ($req) {
        return str_contains($req->url(), '/bot')
            && str_contains($req->url(), '/sendMessage')
            && $req['chat_id'] === 12345
            && $req['text'] === '🎉 *You won*'
            && $req['parse_mode'] === 'MarkdownV2'
            && str_contains((string) $req['reply_markup'], 'inline_keyboard')
            && str_contains((string) $req['reply_markup'], 'web_app');
    });
});

it('omits reply_markup when no keyboard supplied', function () {
    Http::fake([
        'api.telegram.org/*' => Http::response([
            'ok' => true,
            'result' => ['message_id' => 1, 'chat' => ['id' => 99]],
        ]),
    ]);

    expect($this->client->sendMessage(99, 'hi'))->toBeTrue();

    Http::assertSent(fn ($req): bool => ! isset($req['reply_markup']));
});

it('returns false on Telegram 400 and logs failure', function () {
    Http::fake([
        'api.telegram.org/*' => Http::response('Bad', 400),
    ]);

    expect($this->client->sendMessage(99, 'hi'))->toBeFalse();
});

it('returns false on Telegram ok:false (e.g. user blocked the bot)', function () {
    Http::fake([
        'api.telegram.org/*' => Http::response([
            'ok' => false,
            'error_code' => 403,
            'description' => 'Forbidden: bot was blocked by the user',
        ]),
    ]);

    expect($this->client->sendMessage(99, 'hi'))->toBeFalse();
});

it('returns false and makes zero HTTP calls when the bot token is empty', function () {
    config()->set('services.telegram.bot_token', '');
    Http::fake();

    expect($this->client->sendMessage(99, 'hi'))->toBeFalse();

    Http::assertNotSent(fn ($req): bool => str_contains($req->url(), 'api.telegram.org'));
});
