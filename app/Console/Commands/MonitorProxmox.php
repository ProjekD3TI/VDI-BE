<?php

namespace App\Console\Commands;

use App\Events\ProxmoxMetricsUpdated;
use App\Services\InfluxDbService;
use Illuminate\Console\Command;

class MonitorProxmox extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:monitor-proxmox';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle(InfluxDbService $influxService)
    {
        $this->info('Starting Proxmox Realtime Monitor...');

        while (true) {
            $this->info('[' . now()->format('H:i:s') . '] Mengambil data dari InfluxDB...');

            $metrics = $influxService->getRealtimeMetrics();

            if ($metrics) {
                $this->info('[' . now()->format('H:i:s') . '] Berhasil! Menyiarkan data ke Reverb...');
                broadcast(new ProxmoxMetricsUpdated($metrics));
            } else {
                $this->error('[' . now()->format('H:i:s') . '] Gagal mengambil data. Metrics kosong/null.');
            }

            sleep(2);
        }
    }
}
