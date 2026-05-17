import { useEffect, useRef } from 'react';
import type { TelegramAuthUser } from '@/types/telegram';

type Props = {
    botUsername: string;
    onAuth: (user: TelegramAuthUser) => void;
    size?: 'large' | 'medium' | 'small';
    cornerRadius?: number;
};

/**
 * Mounts the official Telegram Login Widget into a placeholder div. The
 * widget script is appended once per instance; the auth callback is
 * exposed via a unique global function name so it survives React re-renders
 * (Telegram captures the callback at script-load time).
 */
export default function TelegramLoginButton({
    botUsername,
    onAuth,
    size = 'large',
    cornerRadius = 8,
}: Props) {
    const containerRef = useRef<HTMLDivElement | null>(null);
    const latestOnAuth = useRef(onAuth);

    useEffect(() => {
        latestOnAuth.current = onAuth;
    }, [onAuth]);

    useEffect(() => {
        if (!containerRef.current) {
            return;
        }

        const callbackName = `tg_auth_${Math.random().toString(36).slice(2, 10)}`;
        (window as unknown as Record<string, (u: TelegramAuthUser) => void>)[
            callbackName
        ] = (user) => latestOnAuth.current(user);

        const script = document.createElement('script');
        script.src = 'https://telegram.org/js/telegram-widget.js?22';
        script.async = true;
        script.setAttribute('data-telegram-login', botUsername);
        script.setAttribute('data-size', size);
        script.setAttribute('data-radius', String(cornerRadius));
        script.setAttribute('data-onauth', `${callbackName}(user)`);
        script.setAttribute('data-request-access', 'write');

        containerRef.current.appendChild(script);

        const node = containerRef.current;

        return () => {
            delete (window as unknown as Record<string, unknown>)[callbackName];

            if (node) {
                node.innerHTML = '';
            }
        };
    }, [botUsername, size, cornerRadius]);

    return <div ref={containerRef} className="flex justify-center" />;
}
