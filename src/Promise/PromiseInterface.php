<?php

declare(strict_types=1);

namespace PFinal\AsyncioHttp\Promise;

/**
 * Promise 接口
 * 兼容 Guzzle Promise
 */
interface PromiseInterface
{
    /**
     * 添加成功和失败的回调
     *
     * @param callable|null $onFulfilled
     * @param callable|null $onRejected
     * @return PromiseInterface
     */
    public function then(
        ?callable $onFulfilled = null,
        ?callable $onRejected = null
    ): PromiseInterface;

    /**
     * 添加失败回调
     *
     * @param callable $onRejected
     * @return PromiseInterface
     */
    public function otherwise(callable $onRejected): PromiseInterface;

    /**
     * 等待 Promise 完成并返回结果
     *
     * @param bool $unwrap
     * @return mixed
     */
    public function wait(bool $unwrap = true);

    /**
     * 获取 Promise 状态
     *
     * @return string pending, fulfilled, 或 rejected
     */
    public function getState(): string;

    /**
     * 取消 Promise
     */
    public function cancel(): void;
}

