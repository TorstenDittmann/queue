<?php

namespace Utopia\Tests;

use PHPUnit\Framework\TestCase;
use Utopia\Queue\Client;
use Utopia\Queue\Connection\Redis;

use function Swoole\Coroutine\go;
use function Swoole\Coroutine\run;

class SwooleTest extends TestCase
{
    private array $payloads;

    public function setUp(): void
    {
        $this->payloads = [];
        $this->payloads[] = [
            'type' => 'test_string',
            'value' => 'lorem ipsum'
        ];
        $this->payloads[] = [
            'type' => 'test_number',
            'value' => 123
        ];
        $this->payloads[] = [
            'type' => 'test_number',
            'value' => 123.456
        ];
        $this->payloads[] = [
            'type' => 'test_bool',
            'value' => true
        ];
        $this->payloads[] = [
            'type' => 'test_null',
            'value' => null
        ];
        $this->payloads[] = [
            'type' => 'test_array',
            'value' => [
                1,
                2,
                3
            ]
        ];
        $this->payloads[] = [
            'type' => 'test_assoc',
            'value' => [
                'string' => 'ipsum',
                'number' => 123,
                'bool' => true,
                'null' => null
            ]
        ];
        $this->payloads[] = [
            'type' => 'test_exception'
        ];
    }

    public function testEvents(): void
    {
        $connection = new Redis('redis', 6379);

        $this->assertTrue($connection->ping());

        $client = new Client('workerman', $connection);
        $client->resetStats();


        foreach ($this->payloads as $payload) {
            $this->assertTrue($client->enqueue($payload));
        }

        sleep(1);

        $this->assertEquals(8, $client->sumTotalJobs());
        $this->assertEquals(0, $client->getQueueSize());
        $this->assertEquals(0, $client->sumProcessingJobs());
        $this->assertEquals(1, $client->sumFailedJobs());
        $this->assertEquals(7, $client->sumSuccessfulJobs());
    }

    public function testSwoole(): void
    {
        $connection = new Redis('redis', 6379);

        run(function () use ($connection) {
            $client = new Client('swoole', $connection);
            go(function () use ($client) {
                $client->resetStats();

                foreach ($this->payloads as $payload) {
                    $this->assertTrue($client->enqueue($payload));
                }

                sleep(1);

                $this->assertEquals(8, $client->sumTotalJobs());
                $this->assertEquals(0, $client->sumProcessingJobs());
                $this->assertEquals(1, $client->sumFailedJobs());
                $this->assertEquals(7, $client->sumSuccessfulJobs());
            });
        });
    }

    /**
     * @depends testSwoole
     */
    public function testRetrySwoole(): void
    {
        $connection = new Redis('redis', 6379);
        $client = new Client('swoole', $connection);
        $client->resetStats();

        $client->enqueue([
            'type' => 'test_exception'
        ]);
        $client->enqueue([
            'type' => 'test_exception'
        ]);
        $client->enqueue([
            'type' => 'test_exception'
        ]);
        $client->enqueue([
            'type' => 'test_exception'
        ]);

        sleep(1);

        $this->assertEquals(4, $client->sumTotalJobs());
        $this->assertEquals(0, $client->sumProcessingJobs());
        $this->assertEquals(4, $client->sumFailedJobs());
        $this->assertEquals(0, $client->sumSuccessfulJobs());

        $client->resetStats();

        $this->assertEquals(0, $client->sumTotalJobs());
        $this->assertEquals(0, $client->sumProcessingJobs());
        $this->assertEquals(0, $client->sumFailedJobs());
        $this->assertEquals(0, $client->sumSuccessfulJobs());

        $client->retryFailedJobs();

        sleep(1);

        // Retry will retry ALL failed jobs regardless of if they are still tracked in stats
        // Meaning this test has 5 failed jobs due to the previous tests.
        $this->assertEquals(5, $client->sumTotalJobs());
        $this->assertEquals(0, $client->sumProcessingJobs());
        $this->assertEquals(5, $client->sumFailedJobs());
        $this->assertEquals(0, $client->sumSuccessfulJobs());
    }

    /**
     * @depends testEvents
     */
    public function testRetryEvents(): void
    {
        $connection = new Redis('redis', 6379);

        $client = new Client('workerman', $connection);
        $client->resetStats();

        $client->enqueue([
            'type' => 'test_exception'
        ]);
        $client->enqueue([
            'type' => 'test_exception'
        ]);
        $client->enqueue([
            'type' => 'test_exception'
        ]);
        $client->enqueue([
            'type' => 'test_exception'
        ]);

        sleep(1);

        $this->assertEquals(4, $client->sumTotalJobs());
        $this->assertEquals(0, $client->sumProcessingJobs());
        $this->assertEquals(4, $client->sumFailedJobs());
        $this->assertEquals(0, $client->sumSuccessfulJobs());

        $client->resetStats();

        $this->assertEquals(0, $client->sumTotalJobs());
        $this->assertEquals(0, $client->sumProcessingJobs());
        $this->assertEquals(0, $client->sumFailedJobs());
        $this->assertEquals(0, $client->sumSuccessfulJobs());

        $client->retryFailedJobs();

        sleep(1);

        $this->assertEquals(5, $client->sumTotalJobs());
        $this->assertEquals(0, $client->sumProcessingJobs());
        $this->assertEquals(5, $client->sumFailedJobs());
        $this->assertEquals(0, $client->sumSuccessfulJobs());
    }
}
