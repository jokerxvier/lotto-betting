export type User = {
    id: number;
    name: string | null;
    username: string | null;
    wallet_code: string;
    telegram_id: number | null;
    avatar?: string;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type SharedWallet = {
    balance: string;
    wallet_code: string;
};

export type Auth = {
    user: User | null;
    wallet: SharedWallet | null;
};
