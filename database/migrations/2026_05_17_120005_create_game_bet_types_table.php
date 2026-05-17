<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_bet_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('game_id')->constrained()->restrictOnDelete();
            $table->string('code', 32);
            $table->string('label', 64);
            $table->decimal('base_bet_amount', 10, 2);
            $table->decimal('base_payout_amount', 14, 2);
            $table->string('payout_strategy', 32);
            $table->decimal('min_bet', 10, 2)->default(10);
            $table->decimal('max_bet', 14, 2)->default(10000);
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['game_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_bet_types');
    }
};
