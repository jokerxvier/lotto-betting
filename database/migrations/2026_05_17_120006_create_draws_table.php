<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('draws', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('game_id')->constrained()->restrictOnDelete();
            $table->timestamp('draw_at');
            $table->timestamp('cutoff_at');
            $table->string('status', 16)->default('scheduled');
            $table->timestamps();

            $table->unique(['game_id', 'draw_at']);
            $table->index(['status', 'cutoff_at']);
            $table->index(['game_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('draws');
    }
};
