<?php

declare(strict_types=1);

namespace PFinal\AsyncioHttp\Promise;

/**
 * 已拒绝的 Promise
 */
class RejectedPromise implements PromiseInterface
{
    private \Throwable $reason;

    public function __construct(\Throwable $reason)
    {
        $this->reason = $reason;
    }

    public function then(
        ?callable $onFulfilled = null,
        ?callable $onRejected = null
    ): PromiseInterface {
        if (!$onRejected) {
            return $this;
        }

        try {
            $result = $onRejected($this->reason);

            return new FulfilledPromise($result);
        } catch (\Throwable $e) {
            return new RejectedPromise($e);
        }
    }

    public function otherwise(callable $onRejected): PromiseInterface
    {
        return $this->then(null, $onRejected);
    }

    public function wait(bool $unwrap = true)
    {
        if ($unwrap) {
            throw $this->reason;
        }

        return null;
    }

    public function getState(): string
    {
        return 'rejected';
    }

    public function cancel(): void
    {
        // 已拒绝的 Promise 无法取消
    }
}

