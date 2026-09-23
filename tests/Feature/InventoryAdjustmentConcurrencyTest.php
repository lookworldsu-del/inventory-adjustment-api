<?php

namespace Tests\Feature;

use App\Models\AdjustmentReason;
use App\Models\Batch;
use App\Models\InventoryAdjustment;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class InventoryAdjustmentConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_concurrent_adjustments_read_the_latest_locked_quantity(): void
    {
        $batch = Batch::factory()->create(['quantity' => 100]);
        $reason = AdjustmentReason::factory()->create();
        $releaseFirstRequest = new InputStream;
        $first = $this->adjustmentProcess($batch, $reason, 92);
        $second = $this->adjustmentProcess($batch, $reason, 90);
        $first->setInput($releaseFirstRequest);

        try {
            $first->start();
            $this->waitForMarker($first, 'BATCH_RETRIEVED');

            $second->start();
            $this->waitForMarker($second, 'BATCH_QUERY_STARTED');

            // Keep the first transaction open while the second tries to read its batch.
            $observationDeadline = microtime(true) + 0.5;

            while (microtime(true) < $observationDeadline) {
                $second->checkTimeout();

                if (str_contains($second->getOutput(), 'BATCH_RETRIEVED')) {
                    $this->fail('The second request read the batch before the first transaction released its lock.');
                }

                if (! $second->isRunning()) {
                    $this->fail('The second request exited while waiting for the batch.');
                }

                usleep(10000);
            }

            $releaseFirstRequest->write("continue\n");
            $releaseFirstRequest->close();

            $this->assertSame(0, $first->wait(), $first->getErrorOutput());
            $this->assertSame(0, $second->wait(), $second->getErrorOutput());
            $this->assertStringContainsString('HTTP_STATUS:201', $first->getOutput());
            $this->assertStringContainsString('HTTP_STATUS:201', $second->getOutput());
        } finally {
            $releaseFirstRequest->close();
            $first->stop(0);
            $second->stop(0);
        }

        $adjustments = InventoryAdjustment::query()
            ->where('batch_id', $batch->id)
            ->orderBy('id')
            ->get(['old_quantity', 'new_quantity', 'quantity_difference'])
            ->toArray();

        $this->assertSame([
            ['old_quantity' => 100, 'new_quantity' => 92, 'quantity_difference' => -8],
            ['old_quantity' => 92, 'new_quantity' => 90, 'quantity_difference' => -2],
        ], $adjustments);

        $this->assertSame(90, $batch->fresh()->quantity);
        $this->assertDatabaseCount('inventory_adjustments', 2);
    }

    private function waitForMarker(Process $process, string $marker): void
    {
        $deadline = microtime(true) + 10;

        while (! str_contains($process->getOutput(), $marker)) {
            $process->checkTimeout();

            if (! $process->isRunning()) {
                $this->fail("Child process exited before {$marker}.");
            }

            if (microtime(true) >= $deadline) {
                $this->fail("Timed out waiting for {$marker}.");
            }

            usleep(10000);
        }
    }

    private function adjustmentProcess(Batch $batch, AdjustmentReason $reason, int $newQuantity): Process
    {
        $code = <<<'PHP'
require 'vendor/autoload.php';

try {
    $app = require 'bootstrap/app.php';

    if ($app->configurationIsCached()) {
        throw new RuntimeException('The concurrency test requires uncached configuration.');
    }

    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    $kernel->bootstrap();

    if (! $app->environment('testing')
        || config('database.default') !== 'mysql'
        || config('database.connections.mysql.database') !== 'inventory_adjustment_testing'
        || Illuminate\Support\Facades\DB::selectOne('select database() as name')->name !== 'inventory_adjustment_testing') {
        throw new RuntimeException('Refusing to run outside the dedicated MySQL test database.');
    }

    Illuminate\Support\Facades\DB::statement('SET SESSION innodb_lock_wait_timeout = 10');

    Illuminate\Support\Facades\DB::connection()->beforeExecuting(function (string $sql): void {
        if (str_starts_with($sql, 'select * from `batches`')) {
            echo "BATCH_QUERY_STARTED\n";
            flush();
        }
    });

    $hasRetrievedBatch = false;

    App\Models\Batch::retrieved(function () use ($argv, &$hasRetrievedBatch): void {
        if ($hasRetrievedBatch) {
            return;
        }

        $hasRetrievedBatch = true;
        echo "BATCH_RETRIEVED\n";
        flush();

        if ((int) $argv[3] === 92) {
            stream_set_timeout(STDIN, 10);

            if (trim((string) fgets(STDIN)) !== 'continue') {
                throw new RuntimeException('The first request was not released in time.');
            }
        }
    });

    $request = Illuminate\Http\Request::create(
        '/api/v1/inventory-adjustments',
        'POST',
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
        json_encode([
            'batch_id' => (int) $argv[1],
            'reason_id' => (int) $argv[2],
            'new_quantity' => (int) $argv[3],
        ], JSON_THROW_ON_ERROR)
    );

    $response = $kernel->handle($request);
    echo 'HTTP_STATUS:'.$response->getStatusCode()."\n";
    $kernel->terminate($request, $response);
    exit($response->getStatusCode() === 201 ? 0 : 1);
} catch (Throwable $exception) {
    fwrite(STDERR, get_class($exception)." in concurrency test child\n");
    exit(1);
}
PHP;

        return new Process(
            [PHP_BINARY, '-r', $code, (string) $batch->id, (string) $reason->id, (string) $newQuantity],
            base_path(),
            [
                'APP_ENV' => 'testing',
                'APP_DEBUG' => 'false',
                'DB_CONNECTION' => 'mysql',
                'DB_DATABASE' => 'inventory_adjustment_testing',
                'DB_URL' => '',
                'CACHE_STORE' => 'array',
                'SESSION_DRIVER' => 'array',
                'QUEUE_CONNECTION' => 'sync',
                'LOG_CHANNEL' => 'null',
            ],
            timeout: 15,
        );
    }
}
