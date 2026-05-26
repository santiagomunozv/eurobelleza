<?php

namespace App\Services\Siesa;

use Carbon\CarbonImmutable;

class SiesaP97Parser
{
    public function parse(string $content): array
    {
        $content = $this->decodeContent($content);

        return [
            'date_from' => $this->extractReportDate($content, 'Fecha Inicial'),
            'date_to' => $this->extractReportDate($content, 'Fecha Final'),
            'records' => $this->extractRecords($content),
        ];
    }

    private function decodeContent(string $content): string
    {
        if (mb_check_encoding($content, 'UTF-8')) {
            return $content;
        }

        return mb_convert_encoding($content, 'UTF-8', 'ISO-8859-1');
    }

    private function extractReportDate(string $content, string $label): ?CarbonImmutable
    {
        if (!preg_match('/' . preg_quote($label, '/') . '\s*:\s*(\d{4}\/\d{2}\/\d{2})/u', $content, $matches)) {
            return null;
        }

        return CarbonImmutable::createFromFormat('Y/m/d', $matches[1])->startOfDay();
    }

    private function extractRecords(string $content): array
    {
        $records = [];

        foreach (preg_split('/\R/u', $content) as $lineNumber => $line) {
            $line = trim($line);

            if (!preg_match(
                '/^(\d{6})\s+(\d{8})\s+(\d{4}\/\d{2}\/\d{2})\s+(\d{12}(?:-\d{2})?)\s+(.+?)\s+(\S+)$/u',
                $line,
                $matches
            )) {
                continue;
            }

            $records[] = [
                'line_number' => $lineNumber + 1,
                'siesa_order_number' => $matches[1],
                'document_alt' => $matches[2],
                'normalized_order_number' => $this->normalizeDocumentAlt($matches[2]),
                'order_date' => CarbonImmutable::createFromFormat('Y/m/d', $matches[3])->startOfDay(),
                'customer_code' => $matches[4],
                'customer_name' => trim($matches[5]),
                'erp_status' => trim($matches[6]),
            ];
        }

        return $records;
    }

    private function normalizeDocumentAlt(string $documentAlt): string
    {
        $normalized = ltrim($documentAlt, '0');

        return $normalized === '' ? '0' : $normalized;
    }
}
