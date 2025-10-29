<?php

declare(strict_types=1);

namespace PFinal\AsyncioHttp;

use Psr\Http\Message\MessageInterface;

/**
 * 消息体摘要器
 * 生成消息体的简短摘要（用于日志）
 */
class BodySummarizer
{
    private int $maxBodyLength;

    public function __construct(int $maxBodyLength = 120)
    {
        $this->maxBodyLength = $maxBodyLength;
    }

    /**
     * 获取消息体摘要
     */
    public function summarize(MessageInterface $message): ?string
    {
        $body = $message->getBody();

        if (!$body->isReadable() || !$body->isSeekable()) {
            return null;
        }

        $size = $body->getSize();

        if ($size === 0) {
            return null;
        }

        // 获取当前位置
        $pos = $body->tell();

        // 读取内容
        $body->rewind();
        $content = $body->read(min($this->maxBodyLength, $size));

        // 恢复位置
        $body->seek($pos);

        // 检查是否为二进制内容
        if ($this->isBinary($content)) {
            return sprintf('(binary %s)', Utils::formatBytes($size));
        }

        // 如果内容被截断
        if ($size > $this->maxBodyLength) {
            $content = Utils::truncate($content, $this->maxBodyLength);
        }

        return trim($content);
    }

    /**
     * 检查是否为二进制内容
     */
    private function isBinary(string $content): bool
    {
        // 检查是否包含 null 字节或太多不可打印字符
        if (str_contains($content, "\0")) {
            return true;
        }

        $printable = 0;
        $len = min(strlen($content), 1000);

        for ($i = 0; $i < $len; $i++) {
            $ord = ord($content[$i]);
            if (($ord >= 32 && $ord < 127) || in_array($ord, [9, 10, 13], true)) {
                $printable++;
            }
        }

        return ($printable / $len) < 0.75;
    }
}

