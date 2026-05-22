import { Head, router, usePage } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, Send, Sparkles } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Props = {
    settings: {
        suggestions_enabled: boolean;
        auto_publish_enabled: boolean;
        push_enabled: boolean;
    };
    source_label: string;
};

const CONFIRM_TOKEN = 'AUTO-PUBLISH';

export default function AdminSettings({ settings, source_label }: Props) {
    const { props } = usePage<{
        flash?: { status?: string };
        errors?: Record<string, string>;
    }>();
    const status = props.flash?.status;
    const errors = props.errors ?? {};

    const [suggestionsEnabled, setSuggestionsEnabled] = useState(
        settings.suggestions_enabled,
    );
    const [autoPublishEnabled, setAutoPublishEnabled] = useState(
        settings.auto_publish_enabled,
    );
    const [pushEnabled, setPushEnabled] = useState(settings.push_enabled);
    const [confirmOpen, setConfirmOpen] = useState(false);
    const [confirmText, setConfirmText] = useState('');
    const [processing, setProcessing] = useState(false);

    const dirty =
        suggestionsEnabled !== settings.suggestions_enabled ||
        autoPublishEnabled !== settings.auto_publish_enabled ||
        pushEnabled !== settings.push_enabled;

    const turningAutoPublishOn =
        autoPublishEnabled && !settings.auto_publish_enabled;

    const submit = (confirmAutoPublish: boolean) => {
        setProcessing(true);

        router.post(
            '/admin/settings',
            {
                suggestions_enabled: suggestionsEnabled,
                auto_publish_enabled: autoPublishEnabled,
                confirm_auto_publish: confirmAutoPublish,
                push_enabled: pushEnabled,
            },
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
                onSuccess: () => {
                    setConfirmOpen(false);
                    setConfirmText('');
                },
            },
        );
    };

    const onSave = () => {
        if (turningAutoPublishOn) {
            setConfirmOpen(true);

            return;
        }

        submit(false);
    };

    return (
        <>
            <Head title="Admin · Settings" />
            <div className="space-y-6 p-4 md:p-6">
                <Heading
                    title="Settings"
                    description="Runtime toggles for the Lotto product. Changes take effect immediately."
                />

                {status && (
                    <div className="flex items-center gap-2 rounded-lg border border-success/40 bg-success/10 px-3 py-2 text-sm text-success">
                        <CheckCircle2 className="size-4" />
                        <span>{status}</span>
                    </div>
                )}

                <Card>
                    <CardHeader className="flex flex-row items-start gap-3 space-y-0">
                        <div className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                            <Sparkles className="size-5" />
                        </div>
                        <div className="flex-1">
                            <CardTitle className="text-base">
                                PCSO suggestions
                            </CardTitle>
                            <CardDescription>
                                Pre-fill the publish form with numbers scraped
                                from{' '}
                                <span className="font-mono">
                                    {source_label}
                                </span>
                                . You still confirm before any bet is settled.
                            </CardDescription>
                        </div>
                        <label
                            htmlFor="suggestions_enabled"
                            className="flex shrink-0 items-center gap-2 text-sm font-semibold"
                        >
                            <Checkbox
                                id="suggestions_enabled"
                                checked={suggestionsEnabled}
                                onCheckedChange={(v) =>
                                    setSuggestionsEnabled(v === true)
                                }
                            />
                            <span className="select-none">
                                {suggestionsEnabled ? 'On' : 'Off'}
                            </span>
                        </label>
                    </CardHeader>
                </Card>

                <Card>
                    <CardHeader className="flex flex-row items-start gap-3 space-y-0">
                        <div className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-destructive/10 text-destructive">
                            <AlertTriangle className="size-5" />
                        </div>
                        <div className="flex-1">
                            <CardTitle className="text-base">
                                Auto-publish results
                                <span className="ml-2 rounded-full bg-destructive/15 px-2 py-0.5 text-[0.6rem] font-bold tracking-wider text-destructive uppercase">
                                    Advanced
                                </span>
                            </CardTitle>
                            <CardDescription>
                                Settle bets automatically when results are
                                scraped — no admin review. Off by default. Runs
                                every 5 minutes via cron when on.
                            </CardDescription>
                        </div>
                        <label
                            htmlFor="auto_publish_enabled"
                            className="flex shrink-0 items-center gap-2 text-sm font-semibold"
                        >
                            <Checkbox
                                id="auto_publish_enabled"
                                checked={autoPublishEnabled}
                                onCheckedChange={(v) =>
                                    setAutoPublishEnabled(v === true)
                                }
                            />
                            <span className="select-none">
                                {autoPublishEnabled ? 'On' : 'Off'}
                            </span>
                        </label>
                    </CardHeader>
                </Card>

                <Card>
                    <CardHeader className="flex flex-row items-start gap-3 space-y-0">
                        <div className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-success/10 text-success">
                            <Send className="size-5" />
                        </div>
                        <div className="flex-1">
                            <CardTitle className="text-base">
                                Telegram push
                            </CardTitle>
                            <CardDescription>
                                DM every winning user via the bot after each
                                draw settles. Off = no DMs at all.
                            </CardDescription>
                        </div>
                        <label
                            htmlFor="push_enabled"
                            className="flex shrink-0 items-center gap-2 text-sm font-semibold"
                        >
                            <Checkbox
                                id="push_enabled"
                                checked={pushEnabled}
                                onCheckedChange={(v) =>
                                    setPushEnabled(v === true)
                                }
                            />
                            <span className="select-none">
                                {pushEnabled ? 'On' : 'Off'}
                            </span>
                        </label>
                    </CardHeader>
                </Card>

                {Object.keys(errors).length > 0 && (
                    <div className="space-y-1">
                        {Object.entries(errors).map(([k, msg]) => (
                            <InputError key={k} message={msg as string} />
                        ))}
                    </div>
                )}

                <div className="flex items-center justify-end gap-3">
                    {dirty && (
                        <p className="text-xs text-muted-foreground">
                            Unsaved changes
                        </p>
                    )}
                    <Button
                        type="button"
                        onClick={onSave}
                        disabled={!dirty || processing}
                    >
                        Save settings
                    </Button>
                </div>
            </div>

            <Dialog open={confirmOpen} onOpenChange={setConfirmOpen}>
                <DialogContent className="max-w-sm">
                    <DialogHeader>
                        <DialogTitle>Enable auto-publish?</DialogTitle>
                        <DialogDescription>
                            This settles bets automatically when results are
                            scraped, with no admin review. Real money will be
                            paid out without your eyes on the numbers. Type{' '}
                            <span className="font-mono font-bold">
                                {CONFIRM_TOKEN}
                            </span>{' '}
                            to confirm.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-2">
                        <Label htmlFor="confirm-token" className="sr-only">
                            Confirmation
                        </Label>
                        <Input
                            id="confirm-token"
                            value={confirmText}
                            onChange={(e) => setConfirmText(e.target.value)}
                            placeholder={CONFIRM_TOKEN}
                            className="font-mono tracking-wider uppercase"
                            autoFocus
                        />
                    </div>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => {
                                setConfirmOpen(false);
                                setConfirmText('');
                                setAutoPublishEnabled(false);
                            }}
                        >
                            Cancel
                        </Button>
                        <Button
                            disabled={
                                processing ||
                                confirmText.toUpperCase() !== CONFIRM_TOKEN
                            }
                            onClick={() => submit(true)}
                        >
                            Enable auto-publish
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
