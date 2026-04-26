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
        // Sincronización diaria de pedidos de Shopify a las 2:00 AM
        $schedule->command('shopify:sync-orders')
            ->dailyAt('02:00')
            ->timezone('America/Bogota');

        // Marcado de pagos vencidos que ya no deben quedar como pendientes operativos
        $schedule->command('orders:mark-expired-payments --days=3 --max-days=30')
            ->dailyAt('02:30')
            ->timezone('America/Bogota');

        // Refresco y reproceso de pedidos pending/failed recientes a las 3:00 AM
        $schedule->command('orders:refresh --non-completed --days=2 --reprocess')
            ->dailyAt('03:00')
            ->timezone('America/Bogota');

        // Revisión de resultados y errores reportados por el RPA
        $schedule->command('siesa:check-errors')
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
