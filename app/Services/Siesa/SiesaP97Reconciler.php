<?php

namespace App\Services\Siesa;

use App\Enums\OrderStatusEnum;
use App\Models\Order;
use App\Services\OrderLogService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class SiesaP97Reconciler
{
    private OrderLogService $orderLogService;

    public function __construct(
        OrderLogService $orderLogService
    ) {
        $this->orderLogService = $orderLogService;
    }

    public function reconcile(string $sourceFile, array $parsedReport, bool $dryRun = false): array
    {
        $records = collect($parsedReport['records'] ?? []);
        $recordsByOrderNumber = $records->keyBy('normalized_order_number');
        $dateFrom = $parsedReport['date_from'] ?? null;
        $dateTo = $parsedReport['date_to'] ?? null;

        $confirmed = $this->confirmPresentOrders($sourceFile, $recordsByOrderNumber, $dryRun);
        $reopened = $this->reopenMissingOrders(
            $sourceFile,
            $recordsByOrderNumber,
            $dateFrom,
            $dateTo,
            $dryRun,
            now()->subDay()
        );

        return [
            'source_file' => $sourceFile,
            'date_from' => $dateFrom ? $dateFrom->toDateString() : null,
            'date_to' => $dateTo ? $dateTo->toDateString() : null,
            'records' => $records->count(),
            'confirmed' => $confirmed,
            'reopened' => $reopened,
            'dry_run' => $dryRun,
        ];
    }

    private function confirmPresentOrders(string $sourceFile, Collection $recordsByOrderNumber, bool $dryRun): int
    {
        $confirmed = 0;

        Order::query()
            ->whereIn('shopify_order_number', $recordsByOrderNumber->keys()->all())
            ->orderBy('id')
            ->chunkById(200, function (Collection $orders) use ($sourceFile, $recordsByOrderNumber, $dryRun, &$confirmed) {
                foreach ($orders as $order) {
                    $record = $recordsByOrderNumber->get($order->shopify_order_number);

                    if (!$record) {
                        continue;
                    }

                    $confirmed++;

                    if ($dryRun) {
                        continue;
                    }

                    $previousStatus = $order->status->value;

                    $order->update([
                        'status' => 'completed',
                        'error_message' => null,
                        'processed_at' => now(),
                        'siesa_order_number' => $record['siesa_order_number'],
                        'siesa_document_alt' => $record['document_alt'],
                        'siesa_order_date' => $record['order_date']->toDateString(),
                        'siesa_erp_status' => $record['erp_status'],
                        'siesa_confirmed_at' => now(),
                        'siesa_confirmation_file' => $sourceFile,
                    ]);

                    $this->orderLogService->logSuccess($order, 'siesa_p97_confirmed', [
                        'source_file' => $sourceFile,
                        'previous_status' => $previousStatus,
                        'siesa_order_number' => $record['siesa_order_number'],
                        'document_alt' => $record['document_alt'],
                        'order_date' => $record['order_date']->toDateString(),
                        'erp_status' => $record['erp_status'],
                    ]);
                }
            });

        return $confirmed;
    }

    private function reopenMissingOrders(
        string $sourceFile,
        Collection $recordsByOrderNumber,
        ?CarbonImmutable $dateFrom,
        ?CarbonImmutable $dateTo,
        bool $dryRun,
        $staleSentToSiesaBefore
    ): int {
        if (!$dateFrom || !$dateTo) {
            return 0;
        }

        $reopened = 0;
        $documentAltSet = $recordsByOrderNumber->keys();

        Order::query()
            ->whereIn('status', [
                'completed',
                'rpa_processing',
                'sent_to_siesa',
            ])
            ->whereBetween('created_at', [$dateFrom->startOfDay(), $dateTo->endOfDay()])
            ->whereNotIn('shopify_order_number', $documentAltSet->all())
            ->where(function ($query) use ($staleSentToSiesaBefore) {
                $query->whereIn('status', ['completed', 'rpa_processing'])
                    ->orWhere(function ($sentToSiesaQuery) use ($staleSentToSiesaBefore) {
                        $sentToSiesaQuery->where('status', 'sent_to_siesa')
                            ->where('updated_at', '<=', $staleSentToSiesaBefore);
                    });
            })
            ->orderBy('id')
            ->chunkById(200, function (Collection $orders) use ($sourceFile, $dryRun, &$reopened) {
                foreach ($orders as $order) {
                    $reopened++;

                    if ($dryRun) {
                        continue;
                    }

                    $order->update([
                        'status' => 'pending',
                        'error_message' => 'Pedido no aparece en el P97 de Siesa y queda pendiente para reproceso.',
                        'processed_at' => null,
                    ]);

                    $this->orderLogService->logWarning($order, 'siesa_p97_missing_reopened', [
                        'source_file' => $sourceFile,
                        'previous_status' => $order->getOriginal('status'),
                    ]);
                }
            });

        return $reopened;
    }
}
