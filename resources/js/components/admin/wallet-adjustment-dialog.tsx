import { useForm } from '@inertiajs/react';
import { ArrowDownCircle, ArrowUpCircle, Loader2 } from 'lucide-react';
import { useEffect, useState } from 'react';
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

type Mode = 'credit' | 'debit';

type Props = {
    mode: Mode;
    user: { id: number; username: string | null; wallet_code: string };
    actorId: number;
    trigger: ReactNode;
};

/**
 * Generates a per-request idempotency key. Prefixing the actor id avoids
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
 * Normalize human-typed peso amounts ("100", "100.5", "100.50  ", "₱100")
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

export function WalletAdjustmentDialog({
    mode,
    user,
    actorId,
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
    const Icon = mode === 'credit' ? ArrowUpCircle : ArrowDownCircle;

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
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Icon
                            className={
                                mode === 'credit'
                                    ? 'size-5 text-success'
                                    : 'size-5 text-destructive'
                            }
                        />
                        {verb} wallet
                    </DialogTitle>
                    <DialogDescription>
                        {mode === 'credit'
                            ? `Credit ${user.username ?? user.wallet_code}'s wallet. Writes a ledger row tagged with you as actor.`
                            : `Debit ${user.username ?? user.wallet_code}'s wallet. Fails if balance is below the amount.`}
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="flex flex-col gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="amount">Amount (₱)</Label>
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
                            placeholder="500.00"
                            autoFocus
                            required
                        />
                        <p className="text-xs text-muted-foreground">
                            Accepts <code>100</code>, <code>100.5</code>, or{' '}
                            <code>100.50</code>.
                        </p>
                        <InputError message={form.errors.amount} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="note">Note (optional)</Label>
                        <Input
                            id="note"
                            name="note"
                            value={form.data.note}
                            onChange={(event) =>
                                form.setData('note', event.currentTarget.value)
                            }
                            maxLength={255}
                            placeholder={
                                mode === 'credit'
                                    ? 'GCash ref 12345'
                                    : 'Reverse erroneous top-up'
                            }
                        />
                        <InputError message={form.errors.note} />
                    </div>

                    <DialogFooter className="gap-2 sm:gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setOpen(false)}
                            disabled={form.processing}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            disabled={form.processing}
                            variant={
                                mode === 'credit' ? 'default' : 'destructive'
                            }
                        >
                            {form.processing && (
                                <Loader2 className="size-4 animate-spin" />
                            )}
                            {verb}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
