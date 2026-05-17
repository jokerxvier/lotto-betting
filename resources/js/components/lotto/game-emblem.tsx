import { cn } from '@/lib/utils';

type Size = 'sm' | 'md' | 'lg';

type Props = {
    code: string;
    size?: Size;
    className?: string;
};

/**
 * Game code → token-colored badge. Uses the --game-2d / --game-3d / --game-4d
 * / --game-6d tokens from app.css so 2D reads as red, 3D as purple, etc.
 * Unknown codes fall back to --primary. The subtle radial highlight matches
 * the LottoBall language so emblem + balls feel like one family.
 */
function bgClass(code: string): string {
    switch (code.toLowerCase()) {
        case '2d':
            return 'bg-game-2d';
        case '3d':
            return 'bg-game-3d';
        case '4d':
            return 'bg-game-4d';
        case '6d':
            return 'bg-game-6d';
        default:
            return 'bg-primary';
    }
}

export default function GameEmblem({ code, size = 'md', className }: Props) {
    return (
        <div
            className={cn(
                'relative flex shrink-0 items-center justify-center overflow-hidden text-primary-foreground uppercase shadow-sm',
                size === 'sm' && 'size-9 rounded-lg text-xs font-black tracking-tighter',
                size === 'md' && 'size-12 rounded-xl text-base font-black tracking-tighter',
                size === 'lg' && 'size-14 rounded-xl text-lg font-black tracking-tighter',
                bgClass(code),
                className,
            )}
        >
            <span
                aria-hidden
                className="absolute inset-0 bg-[radial-gradient(circle_at_30%_25%,oklch(1_0_0/0.3),transparent_60%)]"
            />
            <span className="relative">{code}</span>
        </div>
    );
}
