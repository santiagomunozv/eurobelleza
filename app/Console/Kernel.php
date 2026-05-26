<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // Recupera pedidos que no llegaron por webhook
        $schedule->command('shopify:sync-missing-orders')
            ->dailyAt('02:00')
            ->timezone('America/Bogota');

        // Actualiza datos frescos de Shopify sin despachar reprocesos
        $schedule->command('orders:refresh-shopify-data --non-completed --days=5')
            ->dailyAt('02:20')
            ->timezone('America/Bogota');

        // Marcado de pagos vencidos que ya no deben quedar como pendientes operativos
        $schedule->command('orders:mark-expired-payments --days=3 --max-days=30')
            ->dailyAt('02:30')
            ->timezone('America/Bogota');

        // Despacha a cola los pedidos que sí se van a procesar
        $schedule->command('orders:dispatch-pending --validate')
            ->dailyAt('03:00')
            ->timezone('America/Bogota');

        // Revisión de resultados y errores reportados por el RPA
        $schedule->command('siesa:process-rpa-results')
            ->everyThirtyMinutes()
            ->timezone('America/Bogota');
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
