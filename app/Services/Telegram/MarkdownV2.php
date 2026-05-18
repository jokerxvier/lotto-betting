<?php

declare(strict_types=1);

namespace App\Services\Telegram;

/**
 * Escapes the 18 reserved characters defined by Telegram Bot API
 * MarkdownV2 (`_ * [ ] ( ) ~ ` > # + - = | { } . !`). See
 * https://core.telegram.org/bots/api#markdownv2-style.
 *
 * Use this on every dynamic value that you splice into a MarkdownV2
 * message — currency, names, game labels — to prevent the upstream
 * parser from breaking on a stray `.` or `!`. Static template chrome
 * (the `*` for bold etc.) is already-correct MarkdownV2 and must NOT
 * be escaped.
 */
final class MarkdownV2
{
    private const RESERVED = ['_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'];

    public static function escape(string $value): string
    {
        return str_replace(
            self::RESERVED,
            array_map(fn (string $c): string => '\\'.$c, self::RESERVED),
            $value,
        );
    }
}
