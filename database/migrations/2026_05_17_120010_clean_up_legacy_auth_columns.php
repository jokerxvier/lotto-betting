<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['email']);
            $table->dropColumn([
                'email',
                'email_verified_at',
                'password',
                'remember_token',
                'two_factor_secret',
                'two_factor_recovery_codes',
                'two_factor_confirmed_at',
            ]);
            $table->index('locked_until');
        });

        Schema::dropIfExists('password_reset_tokens');
    }

    public function down(): void
    {
        if (DB::table('users')->exists()) {
            throw new RuntimeException(
                'Refusing to reverse legacy-auth cleanup: users table is non-empty.'
            );
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['locked_until']);
            $table->string('email')->after('name')->unique();
            $table->timestamp('email_verified_at')->after('email')->nullable();
            $table->string('password')->after('email_verified_at');
            $table->rememberToken()->after('password');
            $table->text('two_factor_secret')->after('password')->nullable();
            $table->text('two_factor_recovery_codes')->after('two_factor_secret')->nullable();
            $table->timestamp('two_factor_confirmed_at')->after('two_factor_recovery_codes')->nullable();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }
};
