<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table): void {
            $table->foreignId('actor_user_id')
                ->nullable()
                ->after('idempotency_key')
                ->constrained('users')
                ->nullOnDelete();
            $table->string('note', 255)->nullable()->after('actor_user_id');

            $table->index('actor_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table): void {
            $table->dropForeign(['actor_user_id']);
            $table->dropIndex(['actor_user_id']);
            $table->dropColumn(['actor_user_id', 'note']);
        });
    }
};
