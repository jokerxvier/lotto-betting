import { Icon2D, Icon3D } from '@/components/lotto/game-icons';
import { cn } from '@/lib/utils';

type Size = 'sm' | 'md' | 'lg';

type Props = {
    code: string;
    size?: Size;
    className?: string;
};

/**
 * Game code → emblem. For branded games (2D / 3D) we render the Ez Swerte
 * SVG artwork — square card with the code and decorative number balls
 * baked in. Unknown codes fall back to a token-colored text badge using
 * the --game-2d / --game-3d / --game-4d / --game-6d tokens from app.css.
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
    // The branded SVG carries its own rounded background card, so for known
    // codes we drop the tinted wrapper and let the artwork breathe edge-to-edge.
    const iconClass = cn(
        'shrink-0',
        size === 'sm' && 'size-9',
        size === 'md' && 'size-12',
        size === 'lg' && 'size-14',
        className,
    );

    switch (code.toLowerCase()) {
        case '2d':
        case 'ez2':
            return <Icon2D className={iconClass} />;
        case '3d':
        case 'swertres':
            return <Icon3D className={iconClass} />;
    }

    return (
        <div
            className={cn(
                'relative flex shrink-0 items-center justify-center overflow-hidden text-primary-foreground uppercase shadow-sm',
                size === 'sm' &&
                    'size-9 rounded-lg text-xs font-black tracking-tighter',
                size === 'md' &&
                    'size-12 rounded-xl text-base font-black tracking-tighter',
                size === 'lg' &&
                    'size-14 rounded-xl text-lg font-black tracking-tighter',
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
