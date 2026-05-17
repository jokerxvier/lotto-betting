import { Head, router } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useState } from 'react';
import PinOtpInput from '@/components/auth/pin-otp-input';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { USERNAME_REGEX } from '@/lib/auth';

type Step = 'username' | 'pin';

type ServerErrors = {
    username?: string;
    password?: string;
};

export default function Login() {
    const [step, setStep] = useState<Step>('username');
    const [username, setUsername] = useState('');
    const [pin, setPin] = useState('');
    const [usernameError, setUsernameError] = useState<string | null>(null);
    const [errors, setErrors] = useState<ServerErrors>({});
    const [processing, setProcessing] = useState(false);

    const cleanUsername = () => username.trim().toLowerCase();

    const handleContinue = () => {
        const clean = cleanUsername();

        if (!USERNAME_REGEX.test(clean)) {
            setUsernameError(
                '3–32 characters. Lowercase letters, digits, or underscore.',
            );

            return;
        }

        setUsername(clean);
        setUsernameError(null);
        setErrors({});
        setStep('pin');
    };

    const submit = (pinValue: string) => {
        if (pinValue.length !== 6 || processing) {
            return;
        }

        setProcessing(true);
        router.post(
            '/login',
            { username: cleanUsername(), password: pinValue },
            {
                onError: (e) => {
                    const next = e as ServerErrors;
                    setErrors(next);
                    setPin('');
                    setProcessing(false);

                    // Server-side username rejection (reserved name, taken in
                    // a race) is fixable on Step 1 — bounce back so it's
                    // visible next to the username field.
                    if (next.username) {
                        setUsernameError(next.username);
                        setStep('username');
                    }
                },
                onFinish: () => setProcessing(false),
            },
        );
    };

    if (step === 'username') {
        return (
            <>
                <Head title="Log in" />
                <div className="flex flex-col gap-6">
                    <div className="grid gap-2">
                        <Label htmlFor="username">Username</Label>
                        <Input
                            id="username"
                            name="username"
                            value={username}
                            onChange={(e) => {
                                setUsername(e.target.value);
                                setUsernameError(null);
                            }}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter') {
                                    e.preventDefault();
                                    handleContinue();
                                }
                            }}
                            autoComplete="username"
                            autoCapitalize="none"
                            autoCorrect="off"
                            spellCheck={false}
                            autoFocus
                            placeholder="juandelacruz"
                            data-test="login-username"
                        />
                        <InputError message={usernameError ?? undefined} />
                    </div>
                    <Button
                        type="button"
                        onClick={handleContinue}
                        className="w-full"
                        data-test="login-continue"
                    >
                        Continue
                    </Button>
                    <p className="text-center text-xs text-muted-foreground">
                        New username? We&apos;ll create the account on the next
                        step.
                    </p>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title="Log in" />
            <div className="flex flex-col gap-6">
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={() => {
                        setStep('username');
                        setPin('');
                        setErrors({});
                    }}
                    className="-mt-2 w-fit self-start text-muted-foreground"
                >
                    <ArrowLeft className="mr-1 size-4" />
                    {cleanUsername()}
                </Button>
                <div className="grid gap-2">
                    <Label htmlFor="pin" className="text-center">
                        Enter your 6-digit PIN
                    </Label>
                    <div className="flex justify-center">
                        <PinOtpInput
                            id="pin"
                            value={pin}
                            onChange={(value) => {
                                setPin(value);
                                setErrors({});

                                if (value.length === 6) {
                                    submit(value);
                                }
                            }}
                            autoFocus
                            disabled={processing}
                            data-test="login-pin"
                        />
                    </div>
                    {processing && (
                        <div className="flex justify-center text-muted-foreground">
                            <Spinner />
                        </div>
                    )}
                    <InputError
                        className="text-center"
                        message={errors.password}
                    />
                </div>
            </div>
        </>
    );
}

Login.layout = {
    title: 'Log in to Lotto PH',
    description: 'Enter your username — we’ll ask for a PIN next.',
};
