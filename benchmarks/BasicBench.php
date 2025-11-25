<?php

declare(strict_types=1);

namespace PFinal\AsyncioHttp\Benchmark;

use PFinal\AsyncioHttp\Client;
use function PfinalClub\Asyncio\{run, create_task, gather};

/**
 * 基础性能基准测试
 * 
 * 运行方式：
 * composer benchmark
 * 
 * 或：
 * vendor/bin/phpbench run benchmarks --report=default
 */
class BasicBench
{
    /**
     * 单个请求性能
     * 
     * @Revs(10)
     * @Iterations(5)
     */
    public function benchSingleRequest(): void
    {
        run(function() {
            $client = new Client(['timeout' => 5]);
            $response = $client->get('https://httpbin.org/get');
            
            assert($response->getStatusCode() === 200);
        });
    }

    /**
     * 10 个并发请求性能
     * 
     * @Revs(5)
     * @Iterations(3)
     */
    public function benchTenConcurrentRequests(): void
    {
        run(function() {
            $client = new Client(['timeout' => 10]);
            
            $tasks = [];
            for ($i = 0; $i < 10; $i++) {
                $tasks[] = create_task(fn() => $client->get('https://httpbin.org/get'));
            }
            
            $responses = gather(...$tasks);
            
            assert(count($responses) === 10);
        });
    }

    /**
     * 50 个并发请求性能
     * 
     * @Revs(3)
     * @Iterations(3)
     */
    public function benchFiftyConcurrentRequests(): void
    {
        run(function() {
            $client = new Client(['timeout' => 30]);
            
            $tasks = [];
            for ($i = 0; $i < 50; $i++) {
                $tasks[] = create_task(fn() => $client->get('https://httpbin.org/get'));
            }
            
            $responses = gather(...$tasks);
            
            assert(count($responses) === 50);
        });
    }

    /**
     * POST JSON 请求性能
     * 
     * @Revs(10)
     * @Iterations(5)
     */
    public function benchPostJsonRequest(): void
    {
        run(function() {
            $client = new Client(['timeout' => 5]);
            
            $response = $client->post('https://httpbin.org/post', [
                'json' => [
                    'name' => 'Test User',
                    'email' => 'test@example.com',
                    'data' => range(1, 100),
                ],
            ]);
            
            assert($response->getStatusCode() === 200);
        });
    }

    /**
     * 带重试的请求性能
     * 
     * @Revs(5)
     * @Iterations(3)
     */
    public function benchRequestWithRetry(): void
    {
        run(function() {
            $client = new Client([
                'timeout' => 10,
                'retry' => [
                    'max' => 3,
                    'delay' => 100,
                ],
            ]);
            
            $response = $client->get('https://httpbin.org/get');
            
            assert($response->getStatusCode() === 200);
        });
    }

    /**
     * 大响应体处理性能
     * 
     * @Revs(5)
     * @Iterations(3)
     */
    public function benchLargeResponse(): void
    {
        run(function() {
            $client = new Client(['timeout' => 15]);
            
            // 请求 1MB 的数据
            $response = $client->get('https://httpbin.org/bytes/1048576');
            
            assert(strlen($response->getBody()) >= 1000000);
        });
    }

    /**
     * 连接复用性能测试
     * 
     * @Revs(5)
     * @Iterations(3)
     */
    public function benchConnectionReuse(): void
    {
        run(function() {
            $client = new Client([
                'timeout' => 10,
                'max_connections' => 5,
            ]);
            
            // 向同一个域名发送 20 个请求
            $tasks = [];
            for ($i = 0; $i < 20; $i++) {
                $tasks[] = create_task(fn() => $client->get('https://httpbin.org/get'));
            }
            
            $responses = gather(...$tasks);
            
            assert(count($responses) === 20);
        });
    }
}

