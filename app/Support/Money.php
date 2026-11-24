<?php

namespace App\Support;

/**
 * The resort's one money format.
 *
 * A guest reads the peso amount on a facility page, in their booking summary, in
 * their payment history, and in the arrival-pass email; a staff member reads the
 * same amount on the reservation, the payment ledger, and the report. Every one
 * of those is the same number and must be the same string — two decimals,
 * thousands separators, no wrapping — because a figure that looks different in
 * two places reads as two different figures.
 *
 * This is the definition. Three front-ends use it and nothing reimplements it:
 *
 *  - `resources/views/components/amount.blade.php` (`x-amount`) for rendered pages,
 *  - this class directly where Blade cannot be used (email bodies, copy built in
 *    a service),
 *  - `resources/js/money.js` for live Alpine expressions, which mirrors these
 *    two decimals and separators because the browser and the server cannot share
 *    code. Its output is asserted against this class in `MoneyFormatTest`.
 *
 * Money is a decimal string everywhere it is stored (`decimal:2` casts), so this
 * accepts a string, an int, or a float and rounds half-away-from-zero the way
 * `number_format()` does.
 */
final class Money
{
    public const SYMBOL = '₱';

    /** Just the figure: "1350.5" becomes "1,350.50". */
    public static function format(int|float|string|null $amount): string
    {
        return number_format((float) $amount, 2, '.', ',');
    }

    /** The symbol and the figure, as one readable string: "₱1,350.50". */
    public static function of(int|float|string|null $amount): string
    {
        return self::SYMBOL.self::format($amount);
    }
}
