import {
    createContext,
    useCallback,
    useContext,
    useMemo,
    useState,
} from 'react';
import type { PropsWithChildren } from 'react';

export type DraftLeg = {
    /** Local UUID — only used for React keys and remove(). */
    id: string;
    drawId: number;
    drawAt: string;
    gameCode: string;
    gameName: string;
    picksCount: number;
    gameBetTypeId: number;
    betTypeCode: string;
    betTypeLabel: string;
    numbers: number[];
    /** Decimal string, always "X.XX". */
    amount: string;
};

type CartContextValue = {
    legs: DraftLeg[];
    add: (leg: Omit<DraftLeg, 'id'>) => void;
    remove: (id: string) => void;
    clear: () => void;
    legsForDraw: (drawId: number) => DraftLeg[];
    /** Sum of all leg amounts as a decimal string, "X.XX". */
    totalAmount: string;
};

const CartContext = createContext<CartContextValue | null>(null);

const toCents = (s: string): number => Math.round(Number.parseFloat(s) * 100);
const fromCents = (c: number): string => (c / 100).toFixed(2);

export function CartProvider({ children }: PropsWithChildren) {
    const [legs, setLegs] = useState<DraftLeg[]>([]);

    const add = useCallback((leg: Omit<DraftLeg, 'id'>) => {
        setLegs((prev) => [...prev, { ...leg, id: crypto.randomUUID() }]);
    }, []);

    const remove = useCallback((id: string) => {
        setLegs((prev) => prev.filter((l) => l.id !== id));
    }, []);

    const clear = useCallback(() => setLegs([]), []);

    const legsForDraw = useCallback(
        (drawId: number) => legs.filter((l) => l.drawId === drawId),
        [legs],
    );

    const totalAmount = useMemo(
        () => fromCents(legs.reduce((acc, l) => acc + toCents(l.amount), 0)),
        [legs],
    );

    const value = useMemo<CartContextValue>(
        () => ({ legs, add, remove, clear, legsForDraw, totalAmount }),
        [legs, add, remove, clear, legsForDraw, totalAmount],
    );

    return (
        <CartContext.Provider value={value}>{children}</CartContext.Provider>
    );
}

export function useCart(): CartContextValue {
    const ctx = useContext(CartContext);

    if (!ctx) {
        throw new Error('useCart must be used within <CartProvider>');
    }

    return ctx;
}
