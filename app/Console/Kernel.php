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

        // Refresco y reproceso de pedidos no completados (últimos 30 días) a las 3:00 AM
        $schedule->command('orders:refresh --non-completed --days=30 --reprocess')
            ->dailyAt('03:00')
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
