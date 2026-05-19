import { useForm } from '@inertiajs/react';
import { ArrowDownRight, ArrowUpRight, Loader2 } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import type { ReactNode } from 'react';
import { toast } from 'sonner';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { formatPeso } from '@/lib/money';
import { cn } from '@/lib/utils';

type Mode = 'credit' | 'debit';

type Props = {
    mode: Mode;
    user: { id: number; username: string | null; wallet_code: string };
    actorId: number;
    /** Current wallet balance as a decimal string ("1234.50"). */
    currentBalance: string;
    trigger: ReactNode;
};

const QUICK_AMOUNTS: Array<{ label: string; value: string }> = [
    { label: '₱50', value: '50.00' },
    { label: '₱100', value: '100.00' },
    { label: '₱500', value: '500.00' },
    { label: '₱1,000', value: '1000.00' },
    { label: '₱5,000', value: '5000.00' },
];

/**
 * Generates a per-attempt idempotency key. Prefixing the actor id avoids
 * cross-admin replay collisions even though the DB unique is per-wallet.
 */
function makeIdempotencyKey(actorId: number): string {
    const uuid =
        typeof crypto !== 'undefined' && 'randomUUID' in crypto
            ? crypto.randomUUID()
            : `${Date.now()}-${Math.random().toString(36).slice(2)}`;

    return `${actorId}:${uuid}`;
}

/**
 * Normalize human-typed peso amounts ("100", "100.5", "100.50 ", "₱100")
 * into the strict two-decimal string the API expects ("100.00", "100.50").
 * Returns null if the input can't be coerced into a positive 1-6 digit value.
 */
function normalizeAmount(raw: string): string | null {
    const cleaned = raw.replace(/[₱,\s]/g, '');

    if (!/^\d{1,6}(\.\d{0,2})?$/.test(cleaned)) {
        return null;
    }

    const [whole, fraction = ''] = cleaned.split('.');

    return `${whole}.${fraction.padEnd(2, '0')}`;
}

/**
 * Best-effort projected balance (display only; server is authoritative).
 * Returns null when amount can't be normalized or the math would be
 * meaningful — e.g. an overdraft on a debit.
 */
function projectBalance(
    current: string,
    rawAmount: string,
    mode: Mode,
): { next: string; overdraft: boolean } | null {
    const normalized = normalizeAmount(rawAmount);

    if (normalized === null) {
        return null;
    }

    const cur = Number.parseFloat(current);
    const amt = Number.parseFloat(normalized);

    if (Number.isNaN(cur) || Number.isNaN(amt)) {
        return null;
    }

    const next = mode === 'credit' ? cur + amt : cur - amt;

    return {
        next: next.toFixed(2),
        overdraft: mode === 'debit' && next < 0,
    };
}

export function WalletAdjustmentDialog({
    mode,
    user,
    actorId,
    currentBalance,
    trigger,
}: Props) {
    const [open, setOpen] = useState(false);

    const form = useForm<{
        amount: string;
        note: string;
        idempotency_key: string;
    }>({
        amount: '',
        note: '',
        idempotency_key: makeIdempotencyKey(actorId),
    });

    useEffect(() => {
        if (open) {
            form.setData('idempotency_key', makeIdempotencyKey(actorId));
            form.clearErrors();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, actorId]);

    const action = mode === 'credit' ? 'credit' : 'debit';
    const verb = mode === 'credit' ? 'Credit' : 'Debit';
    const Icon = mode === 'credit' ? ArrowUpRight : ArrowDownRight;

    const projection = useMemo(
        () => projectBalance(currentBalance, form.data.amount, mode),
        [currentBalance, form.data.amount, mode],
    );

    const submit = (event: React.FormEvent): void => {
        event.preventDefault();

        const normalized = normalizeAmount(form.data.amount);

        if (normalized === null) {
            form.setError(
                'amount',
                'Enter an amount like 100 or 100.50 (max 6 digits before the decimal).',
            );

            return;
        }

        form.transform((data) => ({ ...data, amount: normalized }));

        form.post(`/admin/users/${user.id}/${action}`, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(
                    `${verb}ed ₱${normalized} ${
                        mode === 'credit' ? 'to' : 'from'
                    } ${user.username ?? user.wallet_code}.`,
                );
                form.reset('amount', 'note');
                form.setData('idempotency_key', makeIdempotencyKey(actorId));
                setOpen(false);
            },
            onError: () => {
                form.setData('idempotency_key', makeIdempotencyKey(actorId));
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent className="overflow-hidden p-0 sm:max-w-md">
                {/* Single-token accent rule signals intent without competing
                    for the user's attention. */}
                <div
                    aria-hidden
                    className={cn(
                        'h-0.5 w-full',
                        mode === 'credit' ? 'bg-success' : 'bg-destructive',
                    )}
                />

                <div className="space-y-5 p-6">
                    <DialogHeader className="space-y-1">
                        <div className="flex items-center gap-2">
                            <span
                                className={cn(
                                    'inline-flex size-7 items-center justify-center rounded-full',
                                    mode === 'credit'
                                        ? 'bg-success/12 text-success'
                                        : 'bg-destructive/12 text-destructive',
                                )}
                            >
                                <Icon className="size-4" />
                            </span>
                            <DialogTitle className="text-lg font-semibold tracking-tight">
                                {verb} wallet
                            </DialogTitle>
                        </div>
                        <DialogDescription className="pl-9 text-xs text-muted-foreground">
                            {mode === 'credit'
                                ? `Adds funds to ${user.username ?? user.wallet_code}. Writes a ledger row tagged with you as the acting admin.`
                                : `Removes funds from ${user.username ?? user.wallet_code}. Fails if the balance is below the amount.`}
                        </DialogDescription>
                    </DialogHeader>

                    {/* Live projected balance — the operator sees the
                        consequence of the action before committing. */}
                    <div className="rounded-lg border bg-muted/30 px-4 py-3">
                        <div className="flex items-baseline justify-between gap-3">
                            <span className="text-[0.65rem] font-bold tracking-[0.12em] text-muted-foreground uppercase">
                                Balance
                            </span>
                            {projection !== null && (
                                <span
                                    className={cn(
                                        'text-[0.65rem] font-bold tracking-[0.12em] uppercase',
                                        projection.overdraft
                                            ? 'text-destructive'
                                            : 'text-muted-foreground',
                                    )}
                                >
                                    {projection.overdraft
                                        ? 'Overdraft'
                                        : 'After'}
                                </span>
                            )}
                        </div>
                        <div className="mt-1 flex items-baseline justify-between gap-3">
                            <span className="font-mono text-lg text-foreground/80 tabular-nums">
                                {formatPeso(currentBalance)}
                            </span>
                            {projection !== null ? (
                                <span
                                    className={cn(
                                        'font-mono text-lg font-semibold tabular-nums',
                                        projection.overdraft
                                            ? 'text-destructive'
                                            : mode === 'credit'
                                              ? 'text-success'
                                              : 'text-foreground',
                                    )}
                                >
                                    {formatPeso(projection.next)}
                                </span>
                            ) : (
                                <span className="font-mono text-lg text-muted-foreground/60 tabular-nums">
                                    —
                                </span>
                            )}
                        </div>
                    </div>

                    <form onSubmit={submit} className="space-y-4">
                        <div className="grid gap-2">
                            <div className="flex items-baseline justify-between">
                                <Label
                                    htmlFor="amount"
                                    className="text-[0.65rem] font-bold tracking-[0.12em] text-muted-foreground uppercase"
                                >
                                    Amount
                                </Label>
                                <span className="text-[0.65rem] tracking-wider text-muted-foreground/70 uppercase">
                                    PHP
                                </span>
                            </div>
                            <div className="relative">
                                <span
                                    aria-hidden
                                    className="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-base text-muted-foreground/70"
                                >
                                    ₱
                                </span>
                                <Input
                                    id="amount"
                                    name="amount"
                                    value={form.data.amount}
                                    onChange={(event) =>
                                        form.setData(
                                            'amount',
                                            event.currentTarget.value,
                                        )
                                    }
                                    onBlur={(event) => {
                                        const normalized = normalizeAmount(
                                            event.currentTarget.value,
                                        );

                                        if (normalized !== null) {
                                            form.setData('amount', normalized);
                                        }
                                    }}
                                    inputMode="decimal"
                                    placeholder="0.00"
                                    autoFocus
                                    required
                                    className="h-12 pl-7 font-mono text-xl tracking-tight tabular-nums"
                                />
                            </div>
                            <div className="flex flex-wrap gap-1.5">
                                {QUICK_AMOUNTS.map((chip) => (
                                    <Button
                                        key={chip.value}
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        className="h-7 rounded-full px-3 text-xs font-medium"
                                        onClick={() =>
                                            form.setData('amount', chip.value)
                                        }
                                    >
                                        {chip.label}
                                    </Button>
                                ))}
                            </div>
                            <InputError message={form.errors.amount} />
                        </div>

                        <div className="grid gap-2">
                            <div className="flex items-baseline justify-between">
                                <Label
                                    htmlFor="note"
                                    className="text-[0.65rem] font-bold tracking-[0.12em] text-muted-foreground uppercase"
                                >
                                    Note
                                </Label>
                                <span className="text-[0.65rem] tracking-wider text-muted-foreground/70 uppercase">
                                    Optional · {form.data.note.length}/255
                                </span>
                            </div>
                            <Textarea
                                id="note"
                                name="note"
                                value={form.data.note}
                                onChange={(event) =>
                                    form.setData(
                                        'note',
                                        event.currentTarget.value,
                                    )
                                }
                                maxLength={255}
                                rows={2}
                                placeholder={
                                    mode === 'credit'
                                        ? 'e.g. GCash deposit ref 12345'
                                        : 'e.g. Reversal of erroneous top-up'
                                }
                                className="resize-none"
                            />
                            <InputError message={form.errors.note} />
                        </div>

                        <DialogFooter className="flex-row items-center justify-between gap-2 sm:justify-between">
                            <span className="hidden text-[0.65rem] tracking-wider text-muted-foreground/70 uppercase sm:inline">
                                <kbd className="rounded border bg-muted px-1 py-0.5 font-mono text-[0.6rem] text-foreground">
                                    Esc
                                </kbd>{' '}
                                cancel ·{' '}
                                <kbd className="rounded border bg-muted px-1 py-0.5 font-mono text-[0.6rem] text-foreground">
                                    Enter
                                </kbd>{' '}
                                confirm
                            </span>
                            <div className="flex gap-2">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={() => setOpen(false)}
                                    disabled={form.processing}
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                    variant={
                                        mode === 'credit'
                                            ? 'default'
                                            : 'destructive'
                                    }
                                    className="min-w-24"
                                >
                                    {form.processing && (
                                        <Loader2 className="size-4 animate-spin" />
                                    )}
                                    {verb}
                                </Button>
                            </div>
                        </DialogFooter>
                    </form>
                </div>
            </DialogContent>
        </Dialog>
    );
}
