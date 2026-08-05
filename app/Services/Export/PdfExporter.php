<?php

declare(strict_types=1);

namespace App\Services\Export;

use Dompdf\Dompdf;
use Dompdf\Options;

final class PdfExporter
{
    /**
     * @param string[] $headers
     * @param array<int,array<int,mixed>> $rows
     * @param string[] $summaryLines optional key facts printed above the table (e.g. date range, totals)
     */
    public static function stream(string $filename, string $title, array $headers, array $rows, array $summaryLines = []): never
    {
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'Helvetica');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(self::renderHtml($title, $headers, $rows, $summaryLines));
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        http_response_code(200);
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $dompdf->output();
        exit;
    }

    private static function renderHtml(string $title, array $headers, array $rows, array $summaryLines): string
    {
        $e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

        $html = '<html><head><meta charset="UTF-8"><style>'
            . 'body{font-family:Helvetica,Arial,sans-serif;font-size:11px;color:#111;}'
            . 'h1{font-size:16px;color:#8a6d1f;margin-bottom:4px;}'
            . 'p.summary{font-size:11px;color:#444;margin:2px 0;}'
            . 'table{width:100%;border-collapse:collapse;margin-top:12px;}'
            . 'th,td{border:1px solid #ccc;padding:4px 6px;text-align:left;}'
            . 'th{background:#f0eadd;}'
            . '</style></head><body>';

        $html .= '<h1>Kymera Collection &mdash; ' . $e($title) . '</h1>';
        $html .= '<p class="summary">Generated ' . $e(date('F j, Y g:ia')) . '</p>';

        foreach ($summaryLines as $line) {
            $html .= '<p class="summary">' . $e($line) . '</p>';
        }

        $html .= '<table><thead><tr>';

        foreach ($headers as $header) {
            $html .= '<th>' . $e($header) . '</th>';
        }

        $html .= '</tr></thead><tbody>';

        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($row as $cell) {
                $html .= '<td>' . $e($cell) . '</td>';
            }
            $html .= '</tr>';
        }

        $html .= '</tbody></table></body></html>';

        return $html;
    }
}
