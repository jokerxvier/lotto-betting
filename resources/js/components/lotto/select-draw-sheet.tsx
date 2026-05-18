import { Clock } from 'lucide-react';
import type { PropsWithChildren } from 'react';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';

export type UpcomingDraw = {
    id: number;
    draw_at: string;
    cutoff_at: string;
};

type Props = PropsWithChildren<{
    /** Header / aria-context. */
    gameName: string;
    /** List sorted ascending by cutoff_at. */
    draws: UpcomingDraw[];
    /**
     * Called with the picked draw. Caller is responsible for closing this
     * sheet and opening the bet wizard bound to that draw.
     */
    onPick: (draw: UpcomingDraw) => void;
}>;

const formatRow = (iso: string): string =>
    new Date(iso).toLocaleString('en-PH', {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });

/**
 * Bottom-sheet picker listing every upcoming draw in the 7-day window
 * for the given game. Triggered by the ADVANCE button on the GameCard.
 *
 * Each row is a full-width button. Cutoff is enforced server-side at
 * cart submit (Hard Rule 4), so this list intentionally doesn't filter
 * past-cutoff rows itself — the parent already does that via the
 * controller payload.
 */
export default function SelectDrawSheet({
    gameName,
    draws,
    onPick,
    children,
}: Props) {
    if (draws.length === 0) {
        return <>{children}</>;
    }

    return (
        <Sheet>
            <SheetTrigger asChild>{children}</SheetTrigger>
            <SheetContent
                side="bottom"
                className="mx-auto max-h-[92svh] max-w-[380px] gap-0 overflow-y-auto rounded-t-2xl"
            >
                <div className="space-y-4 px-5 pt-4 pb-8">
                    <header className="space-y-1 text-center">
                        <SheetTitle className="text-base font-semibold tracking-wide uppercase">
                            Select draw
                        </SheetTitle>
                        <SheetDescription>
                            Pick which {gameName} draw your bet is for.
                        </SheetDescription>
                    </header>

                    <ul className="space-y-2">
                        {draws.map((d) => (
                            <li key={d.id}>
                                <Button
                                    type="button"
                                    size="lg"
                                    className="w-full justify-between font-semibold tracking-wide uppercase"
                                    onClick={() => onPick(d)}
                                >
                                    <span className="flex items-center gap-2">
                                        <Clock className="size-4 opacity-80" />
                                        {formatRow(d.draw_at)}
                                    </span>
                                </Button>
                            </li>
                        ))}
                    </ul>
                </div>
            </SheetContent>
        </Sheet>
    );
}
