<?php namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel {

    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        'App\Console\Commands\PruneRawLogs',
        'App\Console\Commands\PruneOldStats',
        'App\Console\Commands\UpdatePlayerCache',
        'App\Console\Commands\GenerateBulkRecords'
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
	$schedule->command('prune_logs')
		 ->daily();
        $schedule->command('prune_stats')
                 ->daily();
        $schedule->command('update_player_cache')
                 ->hourly();
    }

}
