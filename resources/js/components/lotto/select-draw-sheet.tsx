import { Clock } from 'lucide-react';
import { useState, type PropsWithChildren } from 'react';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { formatDrawRow } from '@/lib/draw-time';

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
     * Called with the picked draw. The sheet auto-closes before the
     * callback runs so the bet wizard the caller opens lands as the
     * only foreground sheet (no overlapping bottom sheets).
     */
    onPick: (draw: UpcomingDraw) => void;
}>;

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
    const [open, setOpen] = useState(false);

    if (draws.length === 0) {
        return <>{children}</>;
    }

    const handlePick = (d: UpcomingDraw) => {
        // Close the picker before handing off to the caller's bet wizard.
        // Otherwise the caller's BetSheet opens on top of this one, and
        // when the player finishes the bet only the BetSheet closes —
        // the picker stays mounted and the player has to dismiss it.
        setOpen(false);
        onPick(d);
    };

    return (
        <Sheet open={open} onOpenChange={setOpen}>
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
                                    onClick={() => handlePick(d)}
                                >
                                    <span className="flex items-center gap-2">
                                        <Clock className="size-4 opacity-80" />
                                        {formatDrawRow(d.draw_at)}
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
