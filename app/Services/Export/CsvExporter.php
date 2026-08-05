<?php

declare(strict_types=1);

namespace App\Services\Export;

final class CsvExporter
{
    /**
     * @param string[] $headers
     * @param array<int,array<int,mixed>> $rows
     */
    public static function stream(string $filename, array $headers, array $rows): never
    {
        http_response_code(200);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $out = fopen('php://output', 'w');
        // UTF-8 BOM so Excel/LibreOffice detect the encoding instead of
        // mangling non-ASCII characters (customer names, etc.).
        fwrite($out, "\xEF\xBB\xBF");
        // PHP 8.4 deprecates fputcsv() without an explicit $escape
        // argument (the old default, backslash-escaping, is being
        // phased out) - passing it explicitly avoids a deprecation
        // notice that would otherwise print into the output stream
        // ahead of the CSV content and corrupt the downloaded file.
        fputcsv($out, $headers, escape: '\\');

        foreach ($rows as $row) {
            fputcsv($out, $row, escape: '\\');
        }

        fclose($out);
        exit;
    }
}
