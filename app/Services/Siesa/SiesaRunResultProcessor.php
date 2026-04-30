<?php

namespace App\Services\Siesa;

use App\Enums\OrderStatusEnum;
use App\Models\Order;
use App\Repositories\OrderRepository;
use App\Services\OrderLogService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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
        $filesWithWarning = $this->normalizeWarningEntries($payload['files_with_warning'] ?? []);
        $filesWithError = $this->normalizeErrorEntries($payload['files_with_error'] ?? []);
        $filesUnresolved = $this->normalizeUnresolvedEntries($payload['files_unresolved'] ?? []);
        $this->prioritizeResultCategories(
            $filesWithoutError,
            $filesWithWarning,
            $filesWithError,
            $filesUnresolved
        );
        $fatalError = $payload['fatal_error'] ?? null;

        $processed = 0;
        $completed = 0;
        $failed = 0;
        $warnings = 0;
        $unresolved = 0;
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
            $this->deleteSourceOrderFile($fileName, $runId);
            $completed++;
        }

        foreach ($filesWithWarning as $warningEntry) {
            $fileName = $warningEntry['file_name'];
            $order = $this->resolveOrderFromFileName($fileName);

            if (!$order) {
                $missing[] = $fileName;
                continue;
            }

            $warningMessage = empty($warningEntry['warnings'])
                ? 'Siesa completó el pedido con advertencias sin detalle.'
                : implode(' | ', $warningEntry['warnings']);

            $this->orderRepository->updateStatus($order, OrderStatusEnum::COMPLETED, $warningMessage);
            $this->orderLogService->logWarning($order, 'rpa_run_completed_with_warning', [
                'run_id' => $runId,
                'result_path' => $resultPath,
                'file_name' => $fileName,
                'p99_key' => $warningEntry['p99_key'],
                'warnings' => $warningEntry['warnings'],
            ]);
            $this->deleteSourceOrderFile($fileName, $runId, $warningEntry['s3_key']);
            $completed++;
            $warnings++;
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
            $this->deleteSourceOrderFile($fileName, $runId, $errorEntry['s3_key']);
            $failed++;
        }

        foreach ($filesUnresolved as $unresolvedEntry) {
            $fileName = $unresolvedEntry['file_name'];
            $order = $this->resolveOrderFromFileName($fileName);

            if (!$order) {
                $missing[] = $fileName;
                continue;
            }

            $this->orderRepository->updateStatus($order, OrderStatusEnum::RPA_PROCESSING);
            $this->orderLogService->logWarning($order, 'rpa_run_unresolved_result', [
                'run_id' => $runId,
                'result_path' => $resultPath,
                'file_name' => $fileName,
                'p99_key' => $unresolvedEntry['p99_key'],
                'reason' => $unresolvedEntry['reason'],
            ]);
            $this->deleteSourceOrderFile($fileName, $runId, $unresolvedEntry['s3_key']);
            $unresolved++;
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
            'warnings' => $warnings,
            'unresolved' => $unresolved,
            'missing_files' => array_values(array_unique($missing)),
            'fatal_error' => $fatalError,
        ];
    }

    private function normalizeFileList(array $files): array
    {
        return collect($files)
            ->map(fn($file) => is_string($file) ? trim($file) : '')
            ->filter()
            ->unique(fn($file) => strtoupper($file))
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
                        's3_key' => null,
                        'p99_key' => null,
                        'errors' => [],
                    ];
                }

                if (!is_array($entry)) {
                    return null;
                }

                return [
                    'file_name' => trim((string) ($entry['file'] ?? $entry['file_name'] ?? '')),
                    's3_key' => $entry['s3_key'] ?? null,
                    'p99_key' => $entry['p99_key'] ?? null,
                    'errors' => collect($entry['errors'] ?? [])
                        ->map(fn($line) => trim((string) $line))
                        ->filter()
                        ->values()
                        ->all(),
                ];
            })
            ->filter(fn($entry) => !empty($entry['file_name']))
            ->pipe(fn($entries) => $this->mergeEntriesByFileName($entries->all(), 'errors'));
    }

    private function normalizeWarningEntries(array $entries): array
    {
        return collect($entries)
            ->map(function ($entry) {
                if (is_string($entry)) {
                    return [
                        'file_name' => trim($entry),
                        's3_key' => null,
                        'p99_key' => null,
                        'warnings' => [],
                    ];
                }

                if (!is_array($entry)) {
                    return null;
                }

                return [
                    'file_name' => trim((string) ($entry['file'] ?? $entry['file_name'] ?? '')),
                    's3_key' => $entry['s3_key'] ?? null,
                    'p99_key' => $entry['p99_key'] ?? null,
                    'warnings' => collect($entry['warnings'] ?? [])
                        ->map(fn($line) => trim((string) $line))
                        ->filter()
                        ->values()
                        ->all(),
                ];
            })
            ->filter(fn($entry) => !empty($entry['file_name']))
            ->pipe(fn($entries) => $this->mergeEntriesByFileName($entries->all(), 'warnings'));
    }

    private function normalizeUnresolvedEntries(array $entries): array
    {
        return collect($entries)
            ->map(function ($entry) {
                if (is_string($entry)) {
                    return [
                        'file_name' => trim($entry),
                        's3_key' => null,
                        'p99_key' => null,
                        'reason' => null,
                    ];
                }

                if (!is_array($entry)) {
                    return null;
                }

                return [
                    'file_name' => trim((string) ($entry['file'] ?? $entry['file_name'] ?? '')),
                    's3_key' => $entry['s3_key'] ?? null,
                    'p99_key' => $entry['p99_key'] ?? null,
                    'reason' => $entry['reason'] ?? null,
                ];
            })
            ->filter(fn($entry) => !empty($entry['file_name']))
            ->pipe(fn($entries) => $this->mergeEntriesByFileName($entries->all()));
    }

    private function prioritizeResultCategories(
        array &$filesWithoutError,
        array &$filesWithWarning,
        array &$filesWithError,
        array &$filesUnresolved
    ): void {
        $errorFiles = $this->fileNameSet(array_column($filesWithError, 'file_name'));
        $unresolvedFiles = $this->fileNameSet(array_column($filesUnresolved, 'file_name'));
        $warningFiles = $this->fileNameSet(array_column($filesWithWarning, 'file_name'));

        $filesUnresolved = collect($filesUnresolved)
            ->reject(fn($entry) => isset($errorFiles[strtoupper($entry['file_name'])]))
            ->values()
            ->all();

        $filesWithWarning = collect($filesWithWarning)
            ->reject(fn($entry) => isset($errorFiles[strtoupper($entry['file_name'])]))
            ->reject(fn($entry) => isset($unresolvedFiles[strtoupper($entry['file_name'])]))
            ->values()
            ->all();

        $blockedFiles = $errorFiles + $unresolvedFiles + $warningFiles;
        $filesWithoutError = collect($filesWithoutError)
            ->reject(fn($fileName) => isset($blockedFiles[strtoupper($fileName)]))
            ->values()
            ->all();
    }

    private function mergeEntriesByFileName(array $entries, ?string $detailKey = null): array
    {
        $merged = [];

        foreach ($entries as $entry) {
            $fileName = $entry['file_name'];
            $key = strtoupper($fileName);

            if (!isset($merged[$key])) {
                $merged[$key] = $entry;

                if ($detailKey !== null) {
                    $merged[$key][$detailKey] = array_values(array_unique($entry[$detailKey] ?? []));
                }

                continue;
            }

            $merged[$key]['s3_key'] = $merged[$key]['s3_key'] ?: ($entry['s3_key'] ?? null);
            $merged[$key]['p99_key'] = $merged[$key]['p99_key'] ?: ($entry['p99_key'] ?? null);
            $merged[$key]['reason'] = $merged[$key]['reason'] ?? ($entry['reason'] ?? null);

            if ($detailKey !== null) {
                $merged[$key][$detailKey] = array_values(array_unique(array_merge(
                    $merged[$key][$detailKey] ?? [],
                    $entry[$detailKey] ?? []
                )));
            }
        }

        return array_values($merged);
    }

    private function fileNameSet(array $fileNames): array
    {
        return collect($fileNames)
            ->map(fn($fileName) => strtoupper((string) $fileName))
            ->filter()
            ->mapWithKeys(fn($fileName) => [$fileName => true])
            ->all();
    }

    private function resolveOrderFromFileName(string $fileName): ?Order
    {
        $baseName = pathinfo($fileName, PATHINFO_FILENAME);
        $normalizedOrderNumber = ltrim($baseName, '0');
        $normalizedOrderNumber = $normalizedOrderNumber === '' ? '0' : $normalizedOrderNumber;

        return $this->orderRepository->findByShopifyOrderNumber($normalizedOrderNumber);
    }

    private function deleteSourceOrderFile(string $fileName, string $runId, ?string $s3Key = null): void
    {
        $path = $this->normalizeSiesaPedidoPath($s3Key ?: $fileName);

        try {
            Storage::disk('siesa_pedidos')->delete($path);
        } catch (\Throwable $e) {
            Log::warning('No se pudo eliminar el archivo fuente de pedidos en S3', [
                'run_id' => $runId,
                'file_name' => $fileName,
                's3_key' => $s3Key,
                'delete_path' => $path,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function normalizeSiesaPedidoPath(string $path): string
    {
        $path = ltrim(trim($path), '/');

        if (str_starts_with($path, 'pedidos/')) {
            return substr($path, strlen('pedidos/'));
        }

        return $path;
    }
}
