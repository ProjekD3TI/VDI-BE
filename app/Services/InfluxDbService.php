<?php

namespace App\Services;

use InfluxDB2\Client;
use Exception;
use Illuminate\Support\Facades\Log;
use InfluxDB2\Model\WritePrecision;

class InfluxDbService
{
    protected $client;
    protected $queryApi;

    public function __construct()
    {
        // Pastikan variabel INFLUXDB_URL, INFLUXDB_TOKEN, INFLUXDB_BUCKET, dan INFLUXDB_ORG ada di .env kamu
        $this->client = new Client([
            "url" => env('INFLUXDB_URL'),
            "token" => env('INFLUXDB_TOKEN'),
            "bucket" => env('INFLUXDB_BUCKET'),
            "org" => env('INFLUXDB_ORG'),
            "precision" => WritePrecision::S
        ]);

        $this->queryApi = $this->client->createQueryApi();
    }

    /**
     * Mengambil semua metrik (CPU, Memory, Storage, Uptime) dalam satu kueri gabungan
     * dan mengembalikannya dalam format array siap pakai.
     */
    public function getRealtimeMetrics()
    {
        $bucket = env('INFLUXDB_BUCKET', 'vdi-app');

        // Menggunakan HEREDOC untuk menulis kueri Flux agar lebih bersih
        $fluxQuery = <<<FLUX
        // 1. Uptime
        uptime = from(bucket:"{$bucket}")
          |> range(start:-5m)
          |> filter(fn:(r) => r._measurement == "system" and r.object == "nodes" and r._field == "uptime")
          |> last()
          |> yield(name: "uptime")

        // 2. Memory
        memory = from(bucket:"{$bucket}")
          |> range(start:-5m)
          |> filter(fn:(r) => r._measurement == "memory")
          |> filter(fn:(r) => r._field == "memtotal" or r._field == "memused" or r._field == "memavailable")
          |> last()
          |> pivot(rowKey:["host"], columnKey:["_field"], valueColumn:"_value")
          |> yield(name: "memory")

        // 3. CPU
        cpu = from(bucket:"{$bucket}")
          |> range(start:-5m)
          |> filter(fn:(r) => r._measurement == "cpustat")
          |> filter(fn:(r) => r._field == "cpu" or r._field == "cpus" or r._field == "avg1" or r._field == "avg5" or r._field == "avg15")
          |> last()
          |> pivot(rowKey:["host"], columnKey:["_field"], valueColumn:"_value")
          |> yield(name: "cpu")

        // 4. Storage
        storage = from(bucket:"{$bucket}")
          |> range(start:-5m)
          |> filter(fn:(r) => r.object == "storages")
          |> filter(fn:(r) => r._field == "total" or r._field == "used" or r._field == "avail")
          |> last()
          |> pivot(rowKey:["host"], columnKey:["_field"], valueColumn:"_value")
          |> yield(name: "storage")
        FLUX;

        try {
            // Eksekusi kueri ke server InfluxDB
            $tables = $this->queryApi->query($fluxQuery);

            // Format data mentah menjadi struktur array yang siap dikirim ke frontend
            return $this->formatMetricsData($tables);

        } catch (Exception $e) {
            // Log error jika koneksi ke InfluxDB gagal, agar worker tidak crash
            Log::error('Gagal mengambil metrik Proxmox dari InfluxDB: ' . $e->getMessage());

            // Kembalikan null atau data kosong jika terjadi kegagalan
            return null;
        }
    }

    /**
     * Memproses (parsing) hasil balasan InfluxDB Client menjadi struktur array yang rapi.
     */
    private function formatMetricsData($tables)
    {
        $metrics = [
            'cpu' => null,
            'memory' => null,
            'storage' => [], // Array karena ada banyak node storage (local, local-lvm, zfspool)
            'uptime_seconds' => 0,
        ];

        foreach ($tables as $table) {
            foreach ($table->records as $record) {
                $data = $record->values;
                $measurement = $data['_measurement'] ?? '';
                $field = $data['_field'] ?? '';

                // 1. Parsing CPU
                if ($measurement === 'cpustat') {
                    $metrics['cpu'] = [
                        // Ubah desimal ke persentase
                        'usage_percentage' => round(($data['cpu'] ?? 0) * 100, 2),
                        'cores' => $data['cpus'] ?? 0,
                        'load_avg' => [
                            $data['avg1'] ?? 0,
                            $data['avg5'] ?? 0,
                            $data['avg15'] ?? 0,
                        ]
                    ];
                }

                // 2. Parsing Memory
                if ($measurement === 'memory') {
                    // Konversi dari Bytes ke Gigabytes (GB)
                    $metrics['memory'] = [
                        'total_gb' => round(($data['memtotal'] ?? 0) / 1073741824, 2),
                        'used_gb' => round(($data['memused'] ?? 0) / 1073741824, 2),
                        'available_gb' => round(($data['memavailable'] ?? 0) / 1073741824, 2),
                    ];
                }

                // 3. Parsing Storage
                if ($measurement === 'system' && isset($data['object']) && $data['object'] === 'storages') {
                    // Konversi dari Bytes ke Gigabytes (GB)
                    $metrics['storage'][] = [
                        'name' => $data['nodename'] ?? 'unknown',
                        'type' => $data['type'] ?? 'unknown',
                        'total_gb' => round(($data['total'] ?? 0) / 1073741824, 2),
                        'used_gb' => round(($data['used'] ?? 0) / 1073741824, 2),
                        'available_gb' => round(($data['avail'] ?? 0) / 1073741824, 2),
                    ];
                }

                // 4. Parsing Uptime
                if ($field === 'uptime') {
                    $metrics['uptime_seconds'] = $data['_value'] ?? 0;
                }
            }
        }

        return $metrics;
    }
}