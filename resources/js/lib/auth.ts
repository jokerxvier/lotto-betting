/**
 * Shared client-side auth constraints. Mirror of `App\Auth\Username::REGEX` —
 * bump both together when changing the username format.
 */
export const USERNAME_REGEX = /^[a-z0-9_]{3,32}$/;
