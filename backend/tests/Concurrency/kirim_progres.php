<?php

/**
 * Proses anak untuk ConcurrentProgressTest: mengirim satu POST /api/progress melalui kernel HTTP
 * Laravel dengan koneksi database sendiri, dimulai tepat pada waktu yang ditentukan proses induk.
 *
 * Argumen: JSON {token, payload, mulai (unix time float), jeda_ms}.
 * jeda_ms > 0 membuat proses tidur di dalam transaksi tepat setelah baris laporan dibuat (ketika kunci
 * pekerjaan sedang dipegang), sehingga permintaan lain pasti datang saat transaksi ini belum selesai.
 * Keluaran: JSON {status, body}.
 */

use App\Models\ProgressReport;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$argumen = json_decode($argv[1], true, flags: JSON_THROW_ON_ERROR);

if (($argumen['jeda_ms'] ?? 0) > 0) {
    $app['events']->listen('eloquent.created: '.ProgressReport::class, fn () => usleep($argumen['jeda_ms'] * 1000));
}

while (microtime(true) < $argumen['mulai']) {
    usleep(500);
}

$request = Request::create('/api/progress', 'POST', server: [
    'HTTP_AUTHORIZATION' => 'Bearer '.$argumen['token'],
    'HTTP_ACCEPT' => 'application/json',
    'CONTENT_TYPE' => 'application/json',
], content: json_encode($argumen['payload']));

$response = $kernel->handle($request);

echo json_encode(['status' => $response->getStatusCode(), 'body' => json_decode($response->getContent(), true)]);

$kernel->terminate($request, $response);
