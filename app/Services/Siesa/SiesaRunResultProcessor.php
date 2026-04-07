<?php

namespace App\Services\Siesa;

use App\Enums\OrderStatusEnum;
use App\Models\Order;
use App\Repositories\OrderRepository;
use App\Services\OrderLogService;
use Illuminate\Support\Facades\Log;

class SiesaRunResultProcessor
{
    public function __construct(
        private OrderRepository $orderRepository,
        private OrderLogService $orderLogService
    ) {}

    public function process(string $resultPath, array $payload): array
    {
        $runId = (string) ($payload['run_id'] ?? pathinfo($resultPath, PATHINFO_FILENAME));
        $filesAttempted = $this->normalizeFileList($payload['files_attempted'] ?? []);
        $filesWithoutError = $this->normalizeFileList($payload['files_without_error'] ?? []);
        $filesWithError = $this->normalizeErrorEntries($payload['files_with_error'] ?? []);
        $fatalError = $payload['fatal_error'] ?? null;

        $processed = 0;
        $completed = 0;
        $failed = 0;
        $missing = [];

        foreach ($filesAttempted as $fileName) {
            $order = $this->resolveOrderFromFileName($fileName);

            if (!$order) {
                $missing[] = $fileName;
                continue;
            }

            $this->orderRepository->updateStatus($order, OrderStatusEnum::RPA_PROCESSING);
            $this->orderLogService->logInfo($order, 'rpa_run_file_attempted', [
                'run_id' => $runId,
                'result_path' => $resultPath,
                'file_name' => $fileName,
            ]);
            $processed++;
        }

        foreach ($filesWithoutError as $fileName) {
            $order = $this->resolveOrderFromFileName($fileName);

            if (!$order) {
                $missing[] = $fileName;
                continue;
            }

            $this->orderRepository->updateStatus($order, OrderStatusEnum::COMPLETED);
            $this->orderLogService->logSuccess($order, 'rpa_run_completed_without_error', [
                'run_id' => $runId,
                'result_path' => $resultPath,
                'file_name' => $fileName,
            ]);
            $completed++;
        }

        foreach ($filesWithError as $errorEntry) {
            $fileName = $errorEntry['file_name'];
            $order = $this->resolveOrderFromFileName($fileName);

            if (!$order) {
                $missing[] = $fileName;
                continue;
            }

            $errorLines = $errorEntry['errors'];
            $errorMessage = empty($errorLines)
                ? 'Siesa reportó un error sin detalle en la corrida RPA.'
                : implode(' | ', $errorLines);

            $this->orderRepository->updateStatus($order, OrderStatusEnum::SIESA_ERROR, $errorMessage);
            $this->orderLogService->logError($order, 'rpa_run_completed_with_error', [
                'run_id' => $runId,
                'result_path' => $resultPath,
                'file_name' => $fileName,
                'p99_key' => $errorEntry['p99_key'],
                'errors' => $errorLines,
            ]);
            $failed++;
        }

        if ($fatalError) {
            Log::warning('Corrida RPA finalizada con fatal_error', [
                'run_id' => $runId,
                'result_path' => $resultPath,
                'fatal_error' => $fatalError,
            ]);
        }

        return [
            'run_id' => $runId,
            'processed' => $processed,
            'completed' => $completed,
            'failed' => $failed,
            'missing_files' => array_values(array_unique($missing)),
            'fatal_error' => $fatalError,
        ];
    }

    private function normalizeFileList(array $files): array
    {
        return collect($files)
            ->map(fn($file) => is_string($file) ? trim($file) : '')
            ->filter()
            ->values()
            ->all();
    }

    private function normalizeErrorEntries(array $entries): array
    {
        return collect($entries)
            ->map(function ($entry) {
                if (is_string($entry)) {
                    return [
                        'file_name' => trim($entry),
                        'p99_key' => null,
                        'errors' => [],
                    ];
                }

                if (!is_array($entry)) {
                    return null;
                }

                return [
                    'file_name' => trim((string) ($entry['file'] ?? $entry['file_name'] ?? '')),
                    'p99_key' => $entry['p99_key'] ?? null,
                    'errors' => collect($entry['errors'] ?? [])
                        ->map(fn($line) => trim((string) $line))
                        ->filter()
                        ->values()
                        ->all(),
                ];
            })
            ->filter(fn($entry) => !empty($entry['file_name']))
            ->values()
            ->all();
    }

    private function resolveOrderFromFileName(string $fileName): ?Order
    {
        $baseName = pathinfo($fileName, PATHINFO_FILENAME);
        $normalizedOrderNumber = ltrim($baseName, '0');
        $normalizedOrderNumber = $normalizedOrderNumber === '' ? '0' : $normalizedOrderNumber;

        return $this->orderRepository->findByShopifyOrderNumber($normalizedOrderNumber);
    }
}
