import { Head } from '@inertiajs/react';
import { Sparkles } from 'lucide-react';
import GameCard from '@/components/lotto/game-card';
import type { GameCardData } from '@/components/lotto/game-card';

type Props = {
    games: GameCardData[];
};

export default function LottoHome({ games }: Props) {
    return (
        <>
            <Head title="Lotto" />
            <div className="space-y-4 p-4">
                <header className="flex items-baseline justify-between">
                    <h1 className="text-lg font-bold tracking-tight">
                        Today's draws
                    </h1>
                    <span className="text-[0.65rem] font-bold tracking-wider text-muted-foreground uppercase">
                        Tap a game to bet
                    </span>
                </header>

                {games.length === 0 ? (
                    <div className="flex flex-col items-center gap-3 rounded-2xl border border-dashed border-border bg-card p-8 text-center">
                        <div className="flex size-12 items-center justify-center rounded-full bg-muted text-muted-foreground">
                            <Sparkles className="size-5" />
                        </div>
                        <div>
                            <p className="text-sm font-semibold">
                                No active games right now
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Check back soon — new draws are scheduled daily.
                            </p>
                        </div>
                    </div>
                ) : (
                    <div className="space-y-3">
                        {games.map((game) => (
                            <GameCard key={game.id} game={game} />
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}
