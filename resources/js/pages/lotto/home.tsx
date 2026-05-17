import { Head } from '@inertiajs/react';
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
                {games.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No active games right now. Check back soon.
                    </p>
                ) : (
                    games.map((game) => <GameCard key={game.id} game={game} />)
                )}
            </div>
        </>
    );
}
