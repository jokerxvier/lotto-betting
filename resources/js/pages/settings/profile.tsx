import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { edit } from '@/routes/profile';

type Props = {
    profile: {
        username: string | null;
        wallet_code: string | null;
        has_telegram: boolean;
    };
};

export default function Profile({ profile }: Props) {
    return (
        <>
            <Head title="Profile settings" />

            <h1 className="sr-only">Profile settings</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Account"
                    description="Your username and wallet reference."
                />

                <dl className="grid gap-4 text-sm">
                    <div className="grid gap-1">
                        <dt className="text-muted-foreground">Username</dt>
                        <dd className="font-medium">
                            {profile.username ?? '— not set —'}
                        </dd>
                    </div>
                    <div className="grid gap-1">
                        <dt className="text-muted-foreground">Wallet code</dt>
                        <dd className="font-mono font-medium tracking-wider">
                            {profile.wallet_code}
                        </dd>
                    </div>
                    <div className="grid gap-1">
                        <dt className="text-muted-foreground">
                            Telegram linked
                        </dt>
                        <dd className="font-medium">
                            {profile.has_telegram ? 'Yes' : 'No'}
                        </dd>
                    </div>
                </dl>
            </div>
        </>
    );
}

Profile.layout = {
    breadcrumbs: [
        {
            title: 'Profile settings',
            href: edit(),
        },
    ],
};
