/**
 * Crypto-backed random integer in [min, max] inclusive. Used by the bet
 * sheet's "Lucky Pick" — purely cosmetic; the server is authoritative on
 * actual draw outcomes. We use crypto to dodge the modulo-bias trap and
 * to feel like a real lotto pick.
 */
export function randomInt(min: number, max: number): number {
    const span = max - min + 1;
    const buf = new Uint32Array(1);
    crypto.getRandomValues(buf);

    return min + (buf[0] % span);
}
