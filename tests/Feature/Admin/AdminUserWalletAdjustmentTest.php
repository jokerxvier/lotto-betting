<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('forbids non-admins from crediting any wallet', function (): void {
    $attacker = User::factory()->withWallet()->create();
    $target = User::factory()->withWallet('100.00')->create();

    $this->actingAs($attacker)
        ->post("/admin/users/{$target->id}/credit", [
            'amount' => '50.00',
            'idempotency_key' => 'attacker-key-1234',
        ])
        ->assertForbidden();

    expect($target->wallet->fresh()->balance)->toEqual('100.00');
});

it('forbids non-admins from debiting any wallet', function (): void {
    $attacker = User::factory()->withWallet()->create();
    $target = User::factory()->withWallet('100.00')->create();

    $this->actingAs($attacker)
        ->post("/admin/users/{$target->id}/debit", [
            'amount' => '50.00',
            'idempotency_key' => 'attacker-key-1234',
        ])
        ->assertForbidden();

    expect($target->wallet->fresh()->balance)->toEqual('100.00');
});

it('credits a wallet and records the actor + note', function (): void {
    $admin = User::factory()->admin()->withWallet()->create();
    $target = User::factory()->withWallet('100.00')->create();

    $this->actingAs($admin)
        ->from("/admin/users/{$target->id}")
        ->post("/admin/users/{$target->id}/credit", [
            'amount' => '250.00',
            'note' => 'refund for INC-101',
            'idempotency_key' => 'credit-key-00000001',
        ])
        ->assertRedirect("/admin/users/{$target->id}");

    expect($target->wallet->fresh()->balance)->toEqual('350.00');

    $tx = WalletTransaction::query()->latest('id')->first();
    expect($tx->type)->toBe('admin_credit')
        ->and($tx->amount)->toEqual('250.00')
        ->and($tx->actor_user_id)->toBe($admin->id)
        ->and($tx->note)->toBe('refund for INC-101');
});

it('debits a wallet and records the actor + note', function (): void {
    $admin = User::factory()->admin()->withWallet()->create();
    $target = User::factory()->withWallet('500.00')->create();

    $this->actingAs($admin)
        ->from("/admin/users/{$target->id}")
        ->post("/admin/users/{$target->id}/debit", [
            'amount' => '120.00',
            'note' => 'reverse INC-101',
            'idempotency_key' => 'debit-key-00000001',
        ])
        ->assertRedirect("/admin/users/{$target->id}");

    expect($target->wallet->fresh()->balance)->toEqual('380.00');

    $tx = WalletTransaction::query()->latest('id')->first();
    expect($tx->type)->toBe('admin_debit')
        ->and($tx->amount)->toEqual('-120.00')
        ->and($tx->actor_user_id)->toBe($admin->id)
        ->and($tx->note)->toBe('reverse INC-101');
});

it('returns 422 on an overdraft debit and leaves the balance untouched', function (): void {
    $admin = User::factory()->admin()->withWallet()->create();
    $target = User::factory()->withWallet('10.00')->create();

    $this->actingAs($admin)
        ->from("/admin/users/{$target->id}")
        ->post("/admin/users/{$target->id}/debit", [
            'amount' => '20.00',
            'idempotency_key' => 'debit-overdraft-12345',
        ])
        ->assertRedirect("/admin/users/{$target->id}")
        ->assertSessionHasErrors('amount');

    expect($target->wallet->fresh()->balance)->toEqual('10.00');
    expect(WalletTransaction::query()->count())->toBe(0);
});

it('is idempotent across credit retries with the same key', function (): void {
    $admin = User::factory()->admin()->withWallet()->create();
    $target = User::factory()->withWallet('100.00')->create();

    foreach (range(1, 3) as $_) {
        $this->actingAs($admin)
            ->from("/admin/users/{$target->id}")
            ->post("/admin/users/{$target->id}/credit", [
                'amount' => '50.00',
                'idempotency_key' => 'retry-credit-00000001',
            ])
            ->assertRedirect();
    }

    expect($target->wallet->fresh()->balance)->toEqual('150.00');
    expect(WalletTransaction::query()->count())->toBe(1);
});

it('blocks an admin from adjusting their own wallet', function (): void {
    $admin = User::factory()->admin()->withWallet('100.00')->create();

    $this->actingAs($admin)
        ->from("/admin/users/{$admin->id}")
        ->post("/admin/users/{$admin->id}/credit", [
            'amount' => '50.00',
            'idempotency_key' => 'self-credit-00000001',
        ])
        ->assertRedirect("/admin/users/{$admin->id}")
        ->assertSessionHasErrors('amount');

    expect($admin->wallet->fresh()->balance)->toEqual('100.00');
});

it('rejects a malformed amount', function (string $bad): void {
    $admin = User::factory()->admin()->withWallet()->create();
    $target = User::factory()->withWallet('100.00')->create();

    $this->actingAs($admin)
        ->from("/admin/users/{$target->id}")
        ->post("/admin/users/{$target->id}/credit", [
            'amount' => $bad,
            'idempotency_key' => 'badamount-0000001',
        ])
        ->assertSessionHasErrors('amount');
})->with(['100', '100.5', '-100.00', 'abc']);

it('rejects a missing idempotency_key', function (): void {
    $admin = User::factory()->admin()->withWallet()->create();
    $target = User::factory()->withWallet('100.00')->create();

    $this->actingAs($admin)
        ->from("/admin/users/{$target->id}")
        ->post("/admin/users/{$target->id}/credit", [
            'amount' => '50.00',
        ])
        ->assertSessionHasErrors('idempotency_key');
});
