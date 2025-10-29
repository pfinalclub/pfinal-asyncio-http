<?php

declare(strict_types=1);

namespace PFinal\AsyncioHttp\Cookie;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * 文件持久化 Cookie 容器
 * 将 Cookie 保存到文件
 */
class FileCookieJar extends CookieJar
{
    private string $filename;
    private bool $storeSessionCookies;

    public function __construct(
        string $filename,
        bool $storeSessionCookies = false
    ) {
        parent::__construct();

        $this->filename = $filename;
        $this->storeSessionCookies = $storeSessionCookies;

        // 加载现有 Cookie
        if (file_exists($filename)) {
            $this->load($filename);
        }
    }

    /**
     * 析构时保存 Cookie
     */
    public function __destruct()
    {
        $this->save($this->filename);
    }

    /**
     * 从文件加载 Cookie
     */
    public function load(string $filename): void
    {
        if (!file_exists($filename)) {
            return;
        }

        $content = file_get_contents($filename);
        if ($content === false) {
            return;
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            return;
        }

        foreach ($data as $cookieData) {
            $cookie = new SetCookie($cookieData);

            // 跳过已过期的 Cookie
            if ($cookie->isExpired()) {
                continue;
            }

            $this->setCookie($cookie);
        }
    }

    /**
     * 保存 Cookie 到文件
     */
    public function save(string $filename): void
    {
        $cookies = [];

        foreach ($this as $cookie) {
            // 跳过已过期的 Cookie
            if ($cookie->isExpired()) {
                continue;
            }

            // 如果不保存会话 Cookie，跳过它们
            if (!$this->storeSessionCookies && $cookie->isSession()) {
                continue;
            }

            $cookies[] = [
                'Name' => $cookie->getName(),
                'Value' => $cookie->getValue(),
                'Domain' => $cookie->getDomain(),
                'Path' => $cookie->getPath(),
                'Expires' => $cookie->getExpires(),
                'Max-Age' => $cookie->getMaxAge(),
                'Secure' => $cookie->getSecure(),
                'HttpOnly' => $cookie->getHttpOnly(),
                'SameSite' => $cookie->getSameSite(),
            ];
        }

        $json = json_encode($cookies, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        // 确保目录存在
        $dir = dirname($filename);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($filename, $json, LOCK_EX);
    }

    /**
     * 从响应中提取 Cookie（覆盖以自动保存）
     */
    public function extractCookies(RequestInterface $request, ResponseInterface $response): void
    {
        parent::extractCookies($request, $response);
        $this->save($this->filename);
    }

    /**
     * 设置 Cookie（覆盖以自动保存）
     */
    public function setCookie(SetCookie $cookie): void
    {
        parent::setCookie($cookie);
    }
}

