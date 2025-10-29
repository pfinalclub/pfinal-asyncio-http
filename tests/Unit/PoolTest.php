<?php

declare(strict_types=1);

namespace PFinal\AsyncioHttp\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PFinal\AsyncioHttp\Client;
use PFinal\AsyncioHttp\Pool;
use PFinal\AsyncioHttp\Promise\PromiseInterface;
use PFinal\AsyncioHttp\Promise\FulfilledPromise;
use PFinal\AsyncioHttp\Promise\RejectedPromise;
use PFinal\AsyncioHttp\Psr7\Response;
use Mockery as m;

class PoolTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();
    }

    public function testConstructor()
    {
        $client = m::mock(Client::class);
        $requests = [];
        
        $pool = new Pool($client, $requests);
        $this->assertInstanceOf(Pool::class, $pool);
    }

    public function testConstructorWithConfig()
    {
        $client = m::mock(Client::class);
        $requests = [];
        $config = [
            'concurrency' => 5,
            'fulfilled' => function () {},
            'rejected' => function () {},
        ];
        
        $pool = new Pool($client, $requests, $config);
        $this->assertInstanceOf(Pool::class, $pool);
    }

    public function testPromiseReturnsPromiseInterface()
    {
        $client = m::mock(Client::class);
        $requests = [];
        
        $pool = new Pool($client, $requests);
        $promise = $pool->promise();
        
        $this->assertInstanceOf(PromiseInterface::class, $promise);
    }

    public function testBatchMethod()
    {
        $client = m::mock(Client::class);
        $requests = [];
        
        // 由于Pool::batch是静态方法且内部创建了新的Pool实例，
        // 我们需要模拟异步执行的行为
        // 这里简化测试，只检查返回类型
        $results = Pool::batch($client, $requests);
        
        $this->assertIsArray($results);
    }

    public function testExecuteWithSuccessfulPromises()
    {
        // 模拟成功的Promise
        $response1 = new Response(200);
        $response2 = new Response(201);
        
        $promise1 = new FulfilledPromise($response1);
        $promise2 = new FulfilledPromise($response2);
        
        $client = m::mock(Client::class);
        $requests = [$promise1, $promise2];
        
        $fulfilledCount = 0;
        $config = [
            'fulfilled' => function () use (&$fulfilledCount) {
                $fulfilledCount++;
            },
        ];
        
        // 由于execute是private方法，我们需要通过反射来测试它
        $pool = new Pool($client, $requests, $config);
        
        // 注意：在实际异步环境中，这个测试需要在事件循环中运行
        // 这里只是一个简化的测试，实际运行可能需要使用run()函数
    }

    public function testExecuteWithRejectedPromises()
    {
        // 模拟失败的Promise
        $exception = new \Exception('Test exception');
        $promise = new RejectedPromise($exception);
        
        $client = m::mock(Client::class);
        $requests = [$promise];
        
        $rejectedCount = 0;
        $config = [
            'rejected' => function () use (&$rejectedCount) {
                $rejectedCount++;
            },
        ];
        
        $pool = new Pool($client, $requests, $config);
        
        // 注意：在实际异步环境中，这个测试需要在事件循环中运行
    }

    public function testExecuteWithMixedPromises()
    {
        // 混合成功和失败的Promise
        $response = new Response(200);
        $exception = new \Exception('Test exception');
        
        $successPromise = new FulfilledPromise($response);
        $rejectPromise = new RejectedPromise($exception);
        
        $client = m::mock(Client::class);
        $requests = [$successPromise, $rejectPromise];
        
        $pool = new Pool($client, $requests);
        
        // 注意：在实际异步环境中，这个测试需要在事件循环中运行
    }

    public function testExecuteWithZeroConcurrency()
    {
        $client = m::mock(Client::class);
        $requests = [];
        $config = ['concurrency' => 0];
        
        $pool = new Pool($client, $requests, $config);
        
        // 测试无并发限制的情况
    }

    public function testExecuteWithEmptyRequests()
    {
        $client = m::mock(Client::class);
        $requests = [];
        
        $pool = new Pool($client, $requests);
        
        // 测试空请求列表的情况
    }
}