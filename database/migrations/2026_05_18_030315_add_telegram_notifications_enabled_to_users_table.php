<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Per-user opt-out for Telegram push notifications (winner DMs
            // after a draw settles). Default true: existing users are
            // auto-opted-in; they can flip it off via tinker or (later)
            // a self-serve toggle on /settings.
            $table->boolean('telegram_notifications_enabled')
                ->default(true)
                ->after('telegram_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('telegram_notifications_enabled');
        });
    }
};
