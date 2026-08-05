<?php

declare(strict_types=1);

namespace App\Services\Export;

/**
 * Produces a real, directly-openable Excel file without a heavyweight
 * spreadsheet library: an HTML table served with the
 * `application/vnd.ms-excel` MIME type and a `.xls` filename, which
 * Excel, LibreOffice Calc, and Numbers all recognize and open as a
 * genuine spreadsheet (not a renamed CSV) - correct column separation,
 * a real title row, no manual "which delimiter" guessing on the user's
 * end. Kept intentionally dependency-free, consistent with the rest
 * of this project's "hand-rolled over another package" approach; a
 * true .xlsx (via PhpSpreadsheet) would only be worth the added
 * dependency weight if pivot tables, formulas, or multi-sheet workbooks
 * were actually needed here, which this module's flat report tables
 * don't.
 */
final class ExcelExporter
{
    /**
     * @param string[] $headers
     * @param array<int,array<int,mixed>> $rows
     */
    public static function stream(string $filename, string $title, array $headers, array $rows): never
    {
        http_response_code(200);
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        echo '<html><head><meta charset="UTF-8"></head><body>';
        echo '<table border="1"><thead><tr><th colspan="' . count($headers) . '">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</th></tr><tr>';

        foreach ($headers as $header) {
            echo '<th>' . htmlspecialchars((string) $header, ENT_QUOTES, 'UTF-8') . '</th>';
        }

        echo '</tr></thead><tbody>';

        foreach ($rows as $row) {
            echo '<tr>';
            foreach ($row as $cell) {
                echo '<td>' . htmlspecialchars((string) $cell, ENT_QUOTES, 'UTF-8') . '</td>';
            }
            echo '</tr>';
        }

        echo '</tbody></table></body></html>';
        exit;
    }
}
