import { Head, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useState } from 'react';
import PinOtpInput from '@/components/auth/pin-otp-input';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { USERNAME_REGEX } from '@/lib/auth';

type Props = {
    first_name: string | null;
    has_telegram: boolean;
};

type Step = 'username' | 'pin' | 'confirm';

export default function SetupPin({ first_name, has_telegram }: Props) {
    const [step, setStep] = useState<Step>('username');

    const form = useForm({
        username: '',
        pin: '',
        pin_confirmation: '',
    });

    const handleUsernameContinue = () => {
        const clean = form.data.username.trim().toLowerCase();

        if (!USERNAME_REGEX.test(clean)) {
            form.setError(
                'username',
                '3–32 characters. Lowercase letters, digits, or underscore.',
            );

            return;
        }

        form.setData('username', clean);
        form.clearErrors('username');
        setStep('pin');
    };

    const handlePinChange = (value: string) => {
        form.setData('pin', value);

        if (value.length === 6) {
            setStep('confirm');
        }
    };

    const handleConfirmChange = (value: string) => {
        form.setData('pin_confirmation', value);

        if (value.length === 6) {
            form.post('/auth/setup-pin', {
                onError: () => {
                    form.setData('pin', '');
                    form.setData('pin_confirmation', '');
                    setStep('pin');
                },
            });
        }
    };

    const stepBack = () => {
        if (step === 'confirm') {
            form.setData('pin_confirmation', '');
            setStep('pin');
        } else if (step === 'pin') {
            form.setData('pin', '');
            setStep('username');
        }
    };

    return (
        <>
            <Head title="Set up your PIN" />
            <div className="flex flex-col gap-6">
                {step !== 'username' && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={stepBack}
                        className="-mt-2 w-fit self-start text-muted-foreground"
                    >
                        <ArrowLeft className="mr-1 size-4" />
                        Back
                    </Button>
                )}

                {step === 'username' && (
                    <>
                        {has_telegram && first_name && (
                            <p className="text-sm text-muted-foreground">
                                Welcome,{' '}
                                <span className="font-medium text-foreground">
                                    {first_name}
                                </span>
                                . Finish setting up your account.
                            </p>
                        )}
                        <div className="grid gap-2">
                            <Label htmlFor="username">Username</Label>
                            <Input
                                id="username"
                                name="username"
                                value={form.data.username}
                                onChange={(e) => {
                                    form.setData('username', e.target.value);
                                    form.clearErrors('username');
                                }}
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter') {
                                        e.preventDefault();
                                        handleUsernameContinue();
                                    }
                                }}
                                autoComplete="username"
                                autoCapitalize="none"
                                autoCorrect="off"
                                spellCheck={false}
                                autoFocus
                                placeholder="juandelacruz"
                            />
                            <InputError message={form.errors.username} />
                        </div>
                        <Button
                            type="button"
                            onClick={handleUsernameContinue}
                            className="w-full"
                        >
                            Continue
                        </Button>
                    </>
                )}

                {step === 'pin' && (
                    <div className="grid gap-2">
                        <Label className="text-center">
                            Choose a 6-digit PIN
                        </Label>
                        <div className="flex justify-center">
                            <PinOtpInput
                                value={form.data.pin}
                                onChange={handlePinChange}
                                autoFocus
                            />
                        </div>
                        <p className="text-center text-xs text-muted-foreground">
                            Avoid repeating (1111) or sequences (123456).
                        </p>
                        <InputError
                            className="text-center"
                            message={form.errors.pin}
                        />
                    </div>
                )}

                {step === 'confirm' && (
                    <div className="grid gap-2">
                        <Label className="text-center">Confirm your PIN</Label>
                        <div className="flex justify-center">
                            <PinOtpInput
                                value={form.data.pin_confirmation}
                                onChange={handleConfirmChange}
                                autoFocus
                                disabled={form.processing}
                            />
                        </div>
                        {form.processing && (
                            <div className="flex justify-center text-muted-foreground">
                                <Spinner />
                            </div>
                        )}
                        <InputError
                            className="text-center"
                            message={form.errors.pin}
                        />
                    </div>
                )}
            </div>
        </>
    );
}

SetupPin.layout = {
    title: 'Finish setting up',
    description: 'Pick a username and a PIN to protect your account.',
};
