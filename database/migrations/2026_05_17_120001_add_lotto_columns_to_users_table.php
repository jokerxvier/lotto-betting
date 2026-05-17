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
            $table->unsignedBigInteger('telegram_id')->nullable()->unique()->after('id');
            $table->string('username', 32)->nullable()->unique()->after('telegram_id');
            $table->string('pin_hash')->nullable()->after('password');
            $table->string('status', 16)->default('active')->after('pin_hash');
            $table->string('wallet_code', 16)->unique()->after('status');
            $table->timestamp('locked_until')->nullable()->after('wallet_code');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['telegram_id']);
            $table->dropUnique(['username']);
            $table->dropUnique(['wallet_code']);
            $table->dropColumn([
                'telegram_id',
                'username',
                'pin_hash',
                'status',
                'wallet_code',
                'locked_until',
            ]);
        });
    }
};
