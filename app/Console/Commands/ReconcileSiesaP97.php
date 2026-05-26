<?php

namespace App\Console\Commands;

use App\Services\Siesa\SiesaP97Parser;
use App\Services\Siesa\SiesaP97Reconciler;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ReconcileSiesaP97 extends Command
{
    protected $signature = 'siesa:reconcile-p97
                            {--dry-run : Simular sin actualizar pedidos ni borrar archivos}
                            {--file= : Procesar un archivo específico dentro de confirmaciones/}';

    protected $description = 'Reconcilia pedidos confirmados en Siesa desde archivos P97';

    private SiesaP97Parser $parser;
    private SiesaP97Reconciler $reconciler;

    public function __construct(
        SiesaP97Parser $parser,
        SiesaP97Reconciler $reconciler
    ) {
        parent::__construct();

        $this->parser = $parser;
        $this->reconciler = $reconciler;
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $files = $this->filesToProcess();

        if (empty($files)) {
            $this->warn('Sin archivos P97 pendientes en confirmaciones/.');
            return self::SUCCESS;
        }

        $rows = [];

        foreach ($files as $path) {
            try {
                $content = $this->readP97File($path);
                $parsedReport = $this->parser->parse($content);
                $summary = $this->reconciler->reconcile($path, $parsedReport, $dryRun);

                if (!$dryRun) {
                    $this->deleteP97File($path);
                }

                $rows[] = [
                    $path,
                    $summary['date_from'] ?? 'N/A',
                    $summary['date_to'] ?? 'N/A',
                    $summary['records'],
                    $summary['confirmed'],
                    $summary['reopened'],
                    $dryRun ? 'dry-run' : 'aplicado',
                ];
            } catch (\Throwable $e) {
                Log::error('No se pudo procesar archivo P97 de Siesa', [
                    'path' => $path,
                    'error' => $e->getMessage(),
                ]);

                $this->error("No se pudo procesar {$path}: {$e->getMessage()}");
            }
        }

        if (!empty($rows)) {
            $this->table(
                ['Archivo', 'Desde', 'Hasta', 'Registros P97', 'Confirmados', 'Reabiertos', 'Modo'],
                $rows
            );
        }

        return self::SUCCESS;
    }

    private function filesToProcess(): array
    {
        $specificFile = $this->option('file');

        if ($specificFile) {
            return [ltrim($specificFile, '/')];
        }

        return collect(Storage::disk('siesa_confirmaciones')->files())
            ->filter(fn($path) => str_ends_with(strtoupper($path), '.P97'))
            ->sort()
            ->values()
            ->all();
    }

    private function readP97File(string $path): string
    {
        try {
            $content = Storage::disk('siesa_confirmaciones')->get($path);

            if (is_string($content)) {
                return $content;
            }

            throw new \RuntimeException('Storage retornó null al leer desde siesa_confirmaciones.');
        } catch (\Throwable $e) {
            $fullPath = 'confirmaciones/' . ltrim($path, '/');

            try {
                $content = Storage::disk('siesa_bucket')->get($fullPath);

                if (is_string($content)) {
                    return $content;
                }

                throw new \RuntimeException('Storage retornó null al leer desde siesa_bucket.');
            } catch (\Throwable $fallbackException) {
                throw new \RuntimeException(
                    "No se pudo leer {$path} desde siesa_confirmaciones ni {$fullPath} desde siesa_bucket. " .
                    "Error original: {$e->getMessage()} | Fallback: {$fallbackException->getMessage()}",
                    0,
                    $fallbackException
                );
            }
        }
    }

    private function deleteP97File(string $path): void
    {
        try {
            Storage::disk('siesa_confirmaciones')->delete($path);
        } catch (\Throwable $e) {
            Storage::disk('siesa_bucket')->delete('confirmaciones/' . ltrim($path, '/'));
        }
    }
}
