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
        // Recupera pedidos que no llegaron por webhook antes de cada ventana del RPA
        $schedule->command('shopify:sync-missing-orders')
            ->cron('15 6,12,18 * * *')
            ->timezone('America/Bogota');

        // Actualiza datos frescos de Shopify sin despachar reprocesos antes de cada ventana del RPA
        $schedule->command('orders:refresh-shopify-data --non-completed --days=5')
            ->cron('25 6,12,18 * * *')
            ->timezone('America/Bogota');

        // Marcado de pagos vencidos que ya no deben quedar como pendientes operativos
        $schedule->command('orders:mark-expired-payments --days=3 --max-days=30')
            ->cron('35 6,12,18 * * *')
            ->timezone('America/Bogota');

        // Despacha a cola los pedidos que sí se van a procesar antes de que el RPA lea S3
        $schedule->command('orders:dispatch-pending --validate')
            ->cron('45 6,12,18 * * *')
            ->timezone('America/Bogota');

        // Revisión de resultados y errores reportados por el RPA
        $schedule->command('siesa:process-rpa-results')
            ->cron('5,35 * * * *')
            ->withoutOverlapping()
            ->timezone('America/Bogota');

        // Confirmación contra P97: corre después de resultados/P99 para que P97 sea la verdad final
        $schedule->command('siesa:reconcile-p97')
            ->cron('10,40 * * * *')
            ->withoutOverlapping()
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
