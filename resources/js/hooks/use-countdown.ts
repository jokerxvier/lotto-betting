import { useEffect, useState } from 'react';

type Countdown = {
    hours: number;
    minutes: number;
    seconds: number;
    expired: boolean;
};

function compute(targetIso: string | null): Countdown {
    if (!targetIso) {
        return { hours: 0, minutes: 0, seconds: 0, expired: true };
    }

    const diffMs = new Date(targetIso).getTime() - Date.now();

    if (diffMs <= 0) {
        return { hours: 0, minutes: 0, seconds: 0, expired: true };
    }

    const totalSeconds = Math.floor(diffMs / 1000);

    return {
        hours: Math.floor(totalSeconds / 3600),
        minutes: Math.floor((totalSeconds % 3600) / 60),
        seconds: totalSeconds % 60,
        expired: false,
    };
}

/**
 * Re-renders once a second so that `compute(targetIso)` reflects the latest
 * time-of-day. The countdown value itself isn't stored — it's derived on every
 * render from the prop, which avoids the React-hooks lint flag for setting
 * state synchronously inside an effect.
 */
export function useCountdown(targetIso: string | null): Countdown {
    const [, setTick] = useState(0);

    useEffect(() => {
        if (!targetIso) {
            return;
        }

        const id = setInterval(() => setTick((t) => t + 1), 1000);

        return () => clearInterval(id);
    }, [targetIso]);

    return compute(targetIso);
}
