<?php

declare(strict_types=1);

namespace PFinal\AsyncioHttp\Promise;

use function PfinalClub\Asyncio\{gather, create_task};

/**
 * Promise 工具函数
 */

if (!function_exists('PFinal\AsyncioHttp\Promise\promise_for')) {
    /**
     * 为给定值创建 Promise
     */
    function promise_for($value): PromiseInterface
    {
        if ($value instanceof PromiseInterface) {
            return $value;
        }

        if ($value instanceof \PFinal\Asyncio\Task) {
            return new TaskPromise($value);
        }

        return new FulfilledPromise($value);
    }
}

if (!function_exists('PFinal\AsyncioHttp\Promise\rejection_for')) {
    /**
     * 为给定原因创建拒绝的 Promise
     */
    function rejection_for($reason): PromiseInterface
    {
        if ($reason instanceof PromiseInterface) {
            return $reason;
        }

        if ($reason instanceof \Throwable) {
            return new RejectedPromise($reason);
        }

        return new RejectedPromise(new \RuntimeException((string) $reason));
    }
}

if (!function_exists('PFinal\AsyncioHttp\Promise\all')) {
    /**
     * 等待所有 Promise 完成
     */
    function all(array $promises): PromiseInterface
    {
        if (empty($promises)) {
            return new FulfilledPromise([]);
        }

        $tasks = [];
        foreach ($promises as $key => $promise) {
            if ($promise instanceof TaskPromise) {
                $tasks[$key] = $promise->getTask();
            } else {
                // 非 Task Promise，直接等待
                $tasks[$key] = create_task(fn() => $promise->wait());
            }
        }

        $task = create_task(function () use ($tasks) {
            return gather(...array_values($tasks));
        });

        return new TaskPromise($task);
    }
}

if (!function_exists('PFinal\AsyncioHttp\Promise\settle')) {
    /**
     * 等待所有 Promise 完成（包括失败的）
     */
    function settle(array $promises): PromiseInterface
    {
        $task = create_task(function () use ($promises) {
            $results = [];
            foreach ($promises as $key => $promise) {
                try {
                    $results[$key] = [
                        'state' => 'fulfilled',
                        'value' => $promise->wait(),
                    ];
                } catch (\Throwable $e) {
                    $results[$key] = [
                        'state' => 'rejected',
                        'reason' => $e,
                    ];
                }
            }

            return $results;
        });

        return new TaskPromise($task);
    }
}

if (!function_exists('PFinal\AsyncioHttp\Promise\inspect')) {
    /**
     * 检查 Promise 状态
     */
    function inspect(PromiseInterface $promise): array
    {
        $state = $promise->getState();

        if ($state === 'fulfilled') {
            return [
                'state' => 'fulfilled',
                'value' => $promise->wait(false),
            ];
        }

        if ($state === 'rejected') {
            try {
                $promise->wait(false);
            } catch (\Throwable $e) {
                return [
                    'state' => 'rejected',
                    'reason' => $e,
                ];
            }
        }

        return ['state' => 'pending'];
    }
}

if (!function_exists('PFinal\AsyncioHttp\Promise\unwrap')) {
    /**
     * 解包 Promise 数组
     */
    function unwrap(array $promises): array
    {
        $results = [];
        foreach ($promises as $key => $promise) {
            $results[$key] = $promise->wait();
        }

        return $results;
    }
}

