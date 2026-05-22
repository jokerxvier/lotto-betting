import type { ImgHTMLAttributes } from 'react';

/**
 * Ez Swerte branded game-emblem artwork. The PNGs live in
 * `public/images/games/` so they're served straight from the web root
 * (stable URLs, no Vite bundling) — the parent component sizes them via
 * className.
 *
 * Decoding hints (`loading="eager"` + `decoding="async"`) keep the home
 * cards from popping in late on first paint; these images are above-
 * the-fold on every screen that uses GameEmblem.
 */

type Props = Omit<ImgHTMLAttributes<HTMLImageElement>, 'src' | 'alt'>;

export function Icon2D(props: Props) {
    return (
        <img
            src="/images/games/2d.png"
            alt="2D"
            loading="eager"
            decoding="async"
            {...props}
        />
    );
}

export function Icon3D(props: Props) {
    return (
        <img
            src="/images/games/3d.png"
            alt="3D"
            loading="eager"
            decoding="async"
            {...props}
        />
    );
}
