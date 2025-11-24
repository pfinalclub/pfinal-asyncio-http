<?php

declare(strict_types=1);

namespace PFinal\AsyncioHttp;

use function PfinalClub\Asyncio\{create_task, gather, semaphore};

/**
 * 并发请求池
 * 
 * 使用 pfinalclub/asyncio 的 gather 和 semaphore 实现并发控制
 * 
 * @example
 * use function PfinalClub\Asyncio\run;
 * 
 * run(function() {
 *     $client = new Client();
 *     
 *     // 创建请求任务
 *     $requests = [];
 *     for ($i = 1; $i <= 100; $i++) {
 *         $requests[] = fn() => $client->get("https://api.example.com/users/{$i}");
 *     }
 *     
 *     // 并发执行，最多 25 个并发
 *     $results = Pool::batch($client, $requests, [
 *         'concurrency' => 25,
 *         'fulfilled' => fn($response, $index) => echo "Request {$index} succeeded\n",
 *         'rejected' => fn($e, $index) => echo "Request {$index} failed: {$e->getMessage()}\n",
 *     ]);
 * });
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
            'concurrency' => 25,     // 默认并发数
            'fulfilled' => null,     // 请求成功回调
            'rejected' => null,      // 请求失败回调
        ], $config);
    }

    /**
     * 执行池中的所有请求
     */
    public function execute(): array
    {
        $concurrency = $this->config['concurrency'];
        $fulfilled = $this->config['fulfilled'];
        $rejected = $this->config['rejected'];

        // 创建信号量限制并发
        $sem = $concurrency > 0 ? semaphore($concurrency) : null;
        $tasks = [];
        $index = 0;

        foreach ($this->requests as $key => $request) {
            $currentIndex = $index++;
            $currentKey = is_int($key) ? $currentIndex : $key;

            $tasks[] = create_task(function () use ($request, $currentKey, $fulfilled, $rejected, $sem) {
                // 获取信号量（控制并发）
                if ($sem) {
                    $sem->acquire();
                }
                
                try {
                    // 执行请求
                    $response = is_callable($request) ? $request() : $request;

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
        }

        if (empty($tasks)) {
            return [];
        }

        // 并发执行所有任务
        return gather(...$tasks);
    }

    /**
     * 批量处理请求的静态方法
     *
     * @param Client $client HTTP 客户端实例
     * @param array $requests 包含 callable 或响应的数组
     * @param array $config 配置选项
     * @return array 所有请求的结果
     */
    public static function batch(Client $client, array $requests, array $config = []): array
    {
        $pool = new self($client, $requests, $config);
        return $pool->execute();
    }
}
