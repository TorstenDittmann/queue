<?php

namespace Utopia\Queue;

class Client
{
    protected string $queue;
    protected string $namespace;
    protected Connection $connection;
    public function __construct(string $queue, Connection $connection, string $namespace = 'utopia-queue')
    {
        $this->queue = $queue;
        $this->namespace = $namespace;
        $this->connection = $connection;
    }

    public function enqueue(array $payload): bool
    {
        $payload = [
            'pid' => \uniqid(more_entropy: true),
            'queue' => $this->queue,
            'timestamp' => \intval(\microtime()),
            'payload' => $payload
        ];

        return $this->connection->leftPushArray("{$this->namespace}.queue.{$this->queue}", $payload);
    }

    public function getJob(string $pid): Job|false
    {
        $job = $this->connection->get("{$this->namespace}.jobs.{$this->queue}.{$pid}");

        if ($job === false) {
            return false;
        }

        return new Job($job);
    }

    public function listJobs(int $total = 50, int $offset = 0): array
    {
        return $this->connection->listRange("{$this->namespace}.queue.{$this->queue}", $total, $offset);
    }

    public function getQueueSize(): int
    {
        return $this->connection->listSize("{$this->namespace}.queue.{$this->queue}");
    }
}