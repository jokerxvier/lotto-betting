import { Monitor, Moon, Sun } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { Appearance } from '@/hooks/use-appearance';
import { useAppearance } from '@/hooks/use-appearance';

const ICON = {
    light: Sun,
    dark: Moon,
    system: Monitor,
} as const;

/**
 * Small icon-only theme switcher for the LottoLayout top bar. Trigger shows
 * the currently-stored `appearance` (Sun / Moon / Monitor). Settings page's
 * `/settings/appearance` keeps its own bigger tab control — both back the
 * same `useAppearance()` hook so they stay in sync live.
 *
 * Renders nothing on SSR / first client render. `useAppearance` reads
 * `localStorage` which only exists on the client, so the SSR snapshot
 * always says `'light'`. If the user has actually opted into dark, the
 * client would flip to a Moon icon after hydration and React would log a
 * mismatch. Mounted-flag gate avoids the warning without harming UX —
 * the toggle pops in within one paint of the initial render.
 */
export default function ThemeToggle() {
    const [mounted, setMounted] = useState(false);
    const { appearance, updateAppearance } = useAppearance();

    // Textbook one-shot mount flag — required to gate icon render until
    // the client has read localStorage. Not the state-in-effect anti-pattern.
    // eslint-disable-next-line react-hooks/set-state-in-effect
    useEffect(() => setMounted(true), []);

    if (!mounted) {
        return <span className="size-9" aria-hidden />;
    }

    const Icon = ICON[appearance];

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label="Toggle theme"
                    className="text-muted-foreground hover:text-foreground"
                >
                    <Icon className="size-5" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-36">
                <DropdownMenuRadioGroup
                    value={appearance}
                    onValueChange={(v) => updateAppearance(v as Appearance)}
                >
                    <DropdownMenuRadioItem value="light">
                        <Sun className="mr-2 size-4" />
                        Light
                    </DropdownMenuRadioItem>
                    <DropdownMenuRadioItem value="dark">
                        <Moon className="mr-2 size-4" />
                        Dark
                    </DropdownMenuRadioItem>
                    <DropdownMenuRadioItem value="system">
                        <Monitor className="mr-2 size-4" />
                        System
                    </DropdownMenuRadioItem>
                </DropdownMenuRadioGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
