/**
 * Payload shape the Telegram Login Widget hands back to the page callback
 * after a successful sign-in. Mirror of the docs at
 * https://core.telegram.org/widgets/login#receiving-authorization-data.
 *
 * The backend re-verifies the HMAC and freshness before trusting any of
 * these fields — the type is just for plumbing.
 */
export type TelegramAuthUser = {
    id: number;
    first_name: string;
    last_name?: string;
    username?: string;
    photo_url?: string;
    auth_date: number;
    hash: string;
};

/**
 * Subset of the Mini App SDK we actually consume. See:
 * https://core.telegram.org/bots/webapps#initializing-mini-apps
 */
export type TelegramWebApp = {
    initData: string;
    ready: () => void;
    expand?: () => void;
};

declare global {
    interface Window {
        TelegramLoginWidget?: {
            dataOnauth?: (user: TelegramAuthUser) => void;
        };
        Telegram?: {
            WebApp?: TelegramWebApp;
        };
    }
}
