<?php

declare(strict_types=1);

namespace App\Support;

final class CsvSanitizer
{
    /**
     * Neutralizes spreadsheet formula injection (=, +, -, @, TAB, CR) across CSV cells.
     *
     * @param  list<mixed>  $cells
     * @return list<mixed>
     */
    public static function row(array $cells): array
    {
        return array_map(static function (mixed $cell): mixed {
            if (! is_string($cell) || $cell === '') {
                return $cell;
            }

            $first = $cell[0];
            if (in_array($first, ['=', '+', '-', '@', "\t", "\r"], true)) {
                return "'" . $cell;
            }

            return $cell;
        }, $cells);
    }
}
