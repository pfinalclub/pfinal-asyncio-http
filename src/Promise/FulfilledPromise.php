<?php

declare(strict_types=1);

namespace PFinal\AsyncioHttp\Promise;

/**
 * 已完成的 Promise
 */
class FulfilledPromise implements PromiseInterface
{
    private $value;

    public function __construct($value)
    {
        $this->value = $value;
    }

    public function then(
        ?callable $onFulfilled = null,
        ?callable $onRejected = null
    ): PromiseInterface {
        if (!$onFulfilled) {
            return $this;
        }

        try {
            $result = $onFulfilled($this->value);

            return new FulfilledPromise($result);
        } catch (\Throwable $e) {
            return new RejectedPromise($e);
        }
    }

    public function otherwise(callable $onRejected): PromiseInterface
    {
        return $this;
    }

    public function wait(bool $unwrap = true)
    {
        return $this->value;
    }

    public function getState(): string
    {
        return 'fulfilled';
    }

    public function cancel(): void
    {
        // 已完成的 Promise 无法取消
    }
}

