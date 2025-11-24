<?php

declare(strict_types=1);

namespace PFinal\AsyncioHttp;

use PFinal\AsyncioHttp\Promise\PromiseInterface;
use PFinal\AsyncioHttp\Promise\TaskPromise;

use function PfinalClub\Asyncio\{create_task, gather, semaphore};

/**
 * 并发请求池
 * 类似 Guzzle Pool
 */
class Pool
{
    private Client $client;
    private iterable $requests;
    private array $config;

    public function __construct(Client $client, iterable $requests, array $config = [])
    {
        $this->client = $client;
        $this->requests = $requests;
        $this->config = array_merge([
            'concurrency' => 25,
            'fulfilled' => null,
            'rejected' => null,
        ], $config);
    }

    /**
     * 执行池中的所有请求
     */
    public function promise(): PromiseInterface
    {
        $task = create_task(function () {
            return $this->execute();
        });

        return new TaskPromise($task);
    }

    private function execute(): array
    {
        $concurrency = $this->config['concurrency'];
        $fulfilled = $this->config['fulfilled'];
        $rejected = $this->config['rejected'];

        // 使用 pfinalclub/asyncio v2.1 的 semaphore 进行并发控制
        $sem = $concurrency > 0 ? semaphore($concurrency) : null;
        $tasks = [];
        $index = 0;

        foreach ($this->requests as $key => $promise) {
            $currentIndex = $index++;
            $currentKey = is_int($key) ? $currentIndex : $key;

            $task = create_task(function () use ($promise, $currentKey, $fulfilled, $rejected, $sem) {
                // 获取信号量（如果并发已满则等待）
                if ($sem) {
                    $sem->acquire();
                }
                
                try {
                    // 执行请求
                    $response = $promise instanceof PromiseInterface
                        ? $promise->wait()
                        : $promise;

                    // 调用成功回调
                    if ($fulfilled) {
                        $fulfilled($response, $currentKey);
                    }

                    return ['key' => $currentKey, 'value' => $response, 'state' => 'fulfilled'];
                } catch (\Throwable $e) {
                    // 调用失败回调
                    if ($rejected) {
                        $rejected($e, $currentKey);
                    }

                    return ['key' => $currentKey, 'reason' => $e, 'state' => 'rejected'];
                } finally {
                    // 释放信号量
                    if ($sem) {
                        $sem->release();
                    }
                }
            });

            $tasks[] = $task;
        }

        if (empty($tasks)) {
            return [];
        }

        // 并发执行所有任务
        $results = gather(...$tasks);

        return $results;
    }

    /**
     * 批量处理请求
     */
    public static function batch(Client $client, array $requests, array $config = []): array
    {
        $pool = new self($client, $requests, $config);

        return $pool->promise()->wait();
    }
}

