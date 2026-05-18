import { Form, Head, Link } from '@inertiajs/react';
import { Lock, ShieldCheck } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

export default function AdminLogin() {
    return (
        <>
            <Head title="Admin sign-in" />
            <div className="flex flex-col gap-6">
                <header className="flex flex-col items-center gap-2 text-center">
                    <div className="flex size-12 items-center justify-center rounded-2xl bg-primary text-primary-foreground shadow-sm">
                        <ShieldCheck className="size-6" />
                    </div>
                    <h1 className="text-xl font-bold tracking-tight">
                        Admin sign-in
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Restricted area. Operator credentials only.
                    </p>
                </header>

                <Form
                    action="/admin/login"
                    method="post"
                    className="flex flex-col gap-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="space-y-1">
                                <Label htmlFor="admin-username">
                                    Username
                                </Label>
                                <Input
                                    id="admin-username"
                                    name="username"
                                    type="text"
                                    autoComplete="username"
                                    placeholder="admin"
                                    autoFocus
                                />
                                <InputError message={errors.username} />
                            </div>

                            <div className="space-y-1">
                                <Label htmlFor="admin-password">
                                    Password
                                </Label>
                                <div className="relative">
                                    <Lock className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        id="admin-password"
                                        name="password"
                                        type="password"
                                        autoComplete="current-password"
                                        placeholder="••••••••••••"
                                        className="pl-9"
                                    />
                                </div>
                                <InputError message={errors.password} />
                            </div>

                            <Button
                                type="submit"
                                size="lg"
                                disabled={processing}
                                className="w-full font-semibold tracking-wide uppercase"
                            >
                                {processing && (
                                    <Spinner className="mr-2" />
                                )}
                                Sign in
                            </Button>
                        </>
                    )}
                </Form>

                <p className="text-center text-xs text-muted-foreground">
                    Player?{' '}
                    <Link
                        href="/login"
                        className="font-semibold text-primary hover:underline"
                    >
                        Sign in here →
                    </Link>
                </p>
            </div>
        </>
    );
}
