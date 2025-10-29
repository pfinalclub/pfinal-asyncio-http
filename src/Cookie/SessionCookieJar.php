<?php

declare(strict_types=1);

namespace PFinal\AsyncioHttp\Cookie;

/**
 * 会话 Cookie 容器
 * 使用 PHP Session 持久化 Cookie
 */
class SessionCookieJar extends CookieJar
{
    private string $sessionKey;

    public function __construct(string $sessionKey = 'pfinal_cookies')
    {
        $this->sessionKey = $sessionKey;

        // 从 Session 加载 Cookie
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION[$this->sessionKey])) {
            $cookies = $_SESSION[$this->sessionKey];
            if (is_array($cookies)) {
                parent::__construct(false, array_map(
                    fn($data) => new SetCookie($data),
                    $cookies
                ));
                return;
            }
        }

        parent::__construct();
    }

    /**
     * 析构时保存到 Session
     */
    public function __destruct()
    {
        $this->save();
    }

    /**
     * 保存到 Session
     */
    public function save(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $_SESSION[$this->sessionKey] = $this->toArray();
    }

    /**
     * 设置 Cookie（覆盖以自动保存）
     */
    public function setCookie(SetCookie $cookie): void
    {
        parent::setCookie($cookie);
        $this->save();
    }
}

