import { Form, Head, usePage } from '@inertiajs/react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type Props = {
    idempotency_key: string;
};

export default function AdminWalletTopUp({ idempotency_key }: Props) {
    const { props } = usePage<{ flash?: { status?: string } }>();
    const status = props.flash?.status;

    return (
        <>
            <Head title="Admin · Top-up wallet" />
            <div className="space-y-6 p-4 md:p-6">
                <Heading
                    title="Top-up a wallet"
                    description="Credit a user's wallet by their 8-character wallet code."
                />

                <Card>
                    <CardHeader>
                        <CardTitle>New top-up</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Form
                            action="/admin/wallets/top-up"
                            method="post"
                            resetOnSuccess={['amount', 'note']}
                            className="flex flex-col gap-4"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <input
                                        type="hidden"
                                        name="idempotency_key"
                                        value={idempotency_key}
                                    />
                                    <div className="grid gap-2">
                                        <Label htmlFor="wallet_code">
                                            Wallet code
                                        </Label>
                                        <Input
                                            id="wallet_code"
                                            name="wallet_code"
                                            autoComplete="off"
                                            autoCapitalize="characters"
                                            spellCheck={false}
                                            maxLength={8}
                                            placeholder="8RD6ZQZ2"
                                            className="font-mono uppercase"
                                            required
                                        />
                                        <InputError
                                            message={errors.wallet_code}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="amount">
                                            Amount (₱)
                                        </Label>
                                        <Input
                                            id="amount"
                                            name="amount"
                                            inputMode="decimal"
                                            placeholder="500.00"
                                            required
                                        />
                                        <p className="text-xs text-muted-foreground">
                                            Decimal string. Two-decimal
                                            precision required, e.g.{' '}
                                            <code>500.00</code>.
                                        </p>
                                        <InputError message={errors.amount} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="note">
                                            Note (optional)
                                        </Label>
                                        <Input
                                            id="note"
                                            name="note"
                                            maxLength={255}
                                            placeholder="Manual GCash deposit ref X"
                                        />
                                        <InputError message={errors.note} />
                                    </div>

                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        className="mt-2 w-fit"
                                    >
                                        {processing && <Spinner />}
                                        Credit wallet
                                    </Button>

                                    {status && (
                                        <p className="text-sm font-medium text-success">
                                            {status}
                                        </p>
                                    )}
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
