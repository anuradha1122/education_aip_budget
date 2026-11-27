<?php

// Run only against the dedicated, disposable database named below.
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$database = 'education_aip_numbering_test';
config(['database.default' => 'mysql', 'database.connections.mysql.database' => $database, 'database.connections.mysql.timezone' => '+00:00']);
DB::purge('mysql');
if (DB::connection()->getDatabaseName() !== $database || DB::selectOne('SELECT DATABASE() AS name')->name !== $database) {
    throw new RuntimeException('Refusing to run outside the dedicated test database.');
}

if (($argv[1] ?? '') === 'worker') {
    [, , $mode, $link, $year, $dir] = $argv;
    try {
        if ($mode === 'a') {
            $result = DB::transaction(function () use ($link, $year, $dir) {
                DB::table('financial_years')->where('id', $year)->lockForUpdate()->first();
                file_put_contents($dir.'/locked', '1');
                $deadline = microtime(true) + 20;
                while (! is_file($dir.'/release')) {
                    if (microtime(true) > $deadline) throw new RuntimeException('Timed out waiting for release.');
                    usleep(50000);
                }
                return app(\App\Services\EstimateService::class)->create([
                    'aip_activity_office_id' => (int) $link, 'title' => 'Probe A',
                    'items' => [['description' => 'Probe', 'rate_in_cents' => 100, 'units' => 1, 'people' => 1]],
                ]);
            });
        } else {
            file_put_contents($dir.'/b-started', '1');
            $result = app(\App\Services\EstimateService::class)->create([
                'aip_activity_office_id' => (int) $link, 'title' => 'Probe B',
                'items' => [['description' => 'Probe', 'rate_in_cents' => 100, 'units' => 1, 'people' => 1]],
            ]);
        }
        echo json_encode($result, JSON_THROW_ON_ERROR);
        exit(0);
    } catch (Throwable $e) {
        fwrite(STDERR, $e->getMessage().PHP_EOL);
        exit(1);
    }
}

$existing = DB::selectOne('SELECT COUNT(*) AS total FROM information_schema.tables WHERE table_schema = DATABASE()');
if ((int) $existing->total !== 0) throw new RuntimeException('The dedicated test database must be empty. Create a new empty database; nothing was removed.');
echo "Using dedicated empty test database: $database\n";
Artisan::call('migrate', ['--database' => 'mysql', '--force' => true]);
echo "Migrations completed.\n";
$now = now();
$year = DB::table('financial_years')->insertGetId(['year' => 2027, 'start_date' => '2027-01-01', 'end_date' => '2027-12-31', 'is_closed' => false, 'created_at' => $now, 'updated_at' => $now]);
$office = DB::table('offices')->insertGetId(['code' => 'TEST-ZONE', 'name' => 'Test zone', 'type' => 'zonal', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
$activity = DB::table('aip_activities')->insertGetId(['financial_year_id' => $year, 'activity_number' => '1', 'description' => 'Concurrency test', 'created_at' => $now, 'updated_at' => $now]);
$link = DB::table('aip_activity_offices')->insertGetId(['aip_activity_id' => $activity, 'office_id' => $office, 'created_at' => $now, 'updated_at' => $now]);
$dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'aip-numbering-'.bin2hex(random_bytes(8));
if (! mkdir($dir, 0700)) throw new RuntimeException('Cannot create temporary coordination directory.');
$a = new Process([PHP_BINARY, __FILE__, 'worker', 'a', (string) $link, (string) $year, $dir]);
$b = new Process([PHP_BINARY, __FILE__, 'worker', 'b', (string) $link, (string) $year, $dir]);
$a->setTimeout(30); $b->setTimeout(30);
try {
    $a->start();
    $deadline = microtime(true) + 15;
    while (! is_file($dir.'/locked')) {
        if (! $a->isRunning() || microtime(true) > $deadline) throw new RuntimeException('Worker A did not acquire the lock: '.$a->getErrorOutput());
        usleep(50000);
    }
    $b->start();
    $deadline = microtime(true) + 15;
    while (! is_file($dir.'/b-started')) {
        if (! $b->isRunning() || microtime(true) > $deadline) throw new RuntimeException('Worker B did not start: '.$b->getErrorOutput());
        usleep(50000);
    }
    usleep(500000);
    if (! $b->isRunning()) throw new RuntimeException('Worker B finished while the year lock was held; the test could not confirm overlap. '.$b->getErrorOutput());
    file_put_contents($dir.'/release', '1');
    $a->wait(); $b->wait();
    if (! $a->isSuccessful() || ! $b->isSuccessful()) throw new RuntimeException('Worker failure: '.$a->getErrorOutput().' '.$b->getErrorOutput());
    $results = [json_decode($a->getOutput(), true, 512, JSON_THROW_ON_ERROR), json_decode($b->getOutput(), true, 512, JSON_THROW_ON_ERROR)];
    $refs = array_column($results, 'reference'); sort($refs);
    if ($refs !== ['2027/TEST-ZONE/EST/0001', '2027/TEST-ZONE/EST/0002']) throw new RuntimeException('Unexpected references: '.implode(', ', $refs));
    if (DB::table('estimates')->count() !== 2 || DB::table('estimate_items')->count() !== 2 || (int) DB::table('estimate_reference_sequences')->value('last_number') !== 2) throw new RuntimeException('Unexpected database state.');
    echo "PASS: Worker B was active while the lock was held; both estimates saved with distinct sequential references.\n";
    echo implode(PHP_EOL, $refs).PHP_EOL;
    echo "Test records remain only in $database.\n";
} finally {
    if ($a->isRunning()) $a->stop(1);
    if ($b->isRunning()) $b->stop(1);
    foreach (['locked', 'release', 'b-started'] as $name) if (is_file($dir.'/'.$name)) unlink($dir.'/'.$name);
    rmdir($dir);
}
