<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bet_legs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('game_bet_type_id')->constrained()->restrictOnDelete();
            $table->json('numbers');
            $table->decimal('amount', 10, 2);
            $table->decimal('potential_payout', 14, 2);
            $table->decimal('payout', 14, 2)->nullable();
            $table->timestamps();

            $table->index('bet_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bet_legs');
    }
};
