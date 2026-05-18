<?php

declare(strict_types=1);

use App\Services\Telegram\MarkdownV2;

it('escapes every reserved character', function () {
    expect(MarkdownV2::escape('_'))->toBe('\\_')
        ->and(MarkdownV2::escape('*'))->toBe('\\*')
        ->and(MarkdownV2::escape('['))->toBe('\\[')
        ->and(MarkdownV2::escape(']'))->toBe('\\]')
        ->and(MarkdownV2::escape('('))->toBe('\\(')
        ->and(MarkdownV2::escape(')'))->toBe('\\)')
        ->and(MarkdownV2::escape('~'))->toBe('\\~')
        ->and(MarkdownV2::escape('`'))->toBe('\\`')
        ->and(MarkdownV2::escape('>'))->toBe('\\>')
        ->and(MarkdownV2::escape('#'))->toBe('\\#')
        ->and(MarkdownV2::escape('+'))->toBe('\\+')
        ->and(MarkdownV2::escape('-'))->toBe('\\-')
        ->and(MarkdownV2::escape('='))->toBe('\\=')
        ->and(MarkdownV2::escape('|'))->toBe('\\|')
        ->and(MarkdownV2::escape('{'))->toBe('\\{')
        ->and(MarkdownV2::escape('}'))->toBe('\\}')
        ->and(MarkdownV2::escape('.'))->toBe('\\.')
        ->and(MarkdownV2::escape('!'))->toBe('\\!');
});

it('escapes mixed strings', function () {
    expect(MarkdownV2::escape('₱5,500.00'))->toBe('₱5,500\\.00')
        ->and(MarkdownV2::escape('You won! 🎉'))->toBe('You won\\! 🎉')
        ->and(MarkdownV2::escape('EZ2 5:00 PM'))->toBe('EZ2 5:00 PM');
});

it('leaves safe text alone', function () {
    expect(MarkdownV2::escape(''))->toBe('')
        ->and(MarkdownV2::escape('hello world'))->toBe('hello world')
        ->and(MarkdownV2::escape('abc 123'))->toBe('abc 123');
});
