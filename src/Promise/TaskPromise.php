<?php

declare(strict_types=1);

namespace PFinal\AsyncioHttp\Promise;

use PfinalClub\Asyncio\Task;
use function PfinalClub\Asyncio\{await, create_task};

/**
 * pfinal-asyncio Task 适配器
 * 将 Task 包装成 Promise
 */
class TaskPromise implements PromiseInterface
{
    private Task $task;
    private string $state = 'pending';
    private $result;
    private ?\Throwable $exception = null;
    private array $onFulfilledCallbacks = [];
    private array $onRejectedCallbacks = [];

    public function __construct(Task $task)
    {
        $this->task = $task;
    }

    public function then(
        ?callable $onFulfilled = null,
        ?callable $onRejected = null
    ): PromiseInterface {
        if ($this->state === 'fulfilled' && $onFulfilled) {
            try {
                $result = $onFulfilled($this->result);

                return new FulfilledPromise($result);
            } catch (\Throwable $e) {
                return new RejectedPromise($e);
            }
        }

        if ($this->state === 'rejected' && $onRejected) {
            try {
                $result = $onRejected($this->exception);

                return new FulfilledPromise($result);
            } catch (\Throwable $e) {
                return new RejectedPromise($e);
            }
        }

        if ($onFulfilled) {
            $this->onFulfilledCallbacks[] = $onFulfilled;
        }

        if ($onRejected) {
            $this->onRejectedCallbacks[] = $onRejected;
        }

        return $this;
    }

    public function otherwise(callable $onRejected): PromiseInterface
    {
        return $this->then(null, $onRejected);
    }

    public function wait(bool $unwrap = true)
    {
        if ($this->state !== 'pending') {
            if ($unwrap && $this->state === 'rejected') {
                throw $this->exception;
            }

            return $this->result;
        }

        try {
            $this->result = await($this->task);
            $this->state = 'fulfilled';

            foreach ($this->onFulfilledCallbacks as $callback) {
                try {
                    $callback($this->result);
                } catch (\Throwable $e) {
                    // 忽略回调中的错误
                }
            }

            return $this->result;
        } catch (\Throwable $e) {
            $this->exception = $e;
            $this->state = 'rejected';

            foreach ($this->onRejectedCallbacks as $callback) {
                try {
                    $callback($e);
                } catch (\Throwable $callbackError) {
                    // 忽略回调中的错误
                }
            }

            if ($unwrap) {
                throw $e;
            }

            return null;
        }
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function cancel(): void
    {
        if ($this->state === 'pending') {
            $this->task->cancel();
            $this->state = 'rejected';
            $this->exception = new \RuntimeException('Promise cancelled');
        }
    }

    public function getTask(): Task
    {
        return $this->task;
    }
}

