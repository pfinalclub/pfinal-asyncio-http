<?php

declare(strict_types=1);

namespace PFinal\AsyncioHttp\Cookie;

/**
 * 单个 Cookie 类
 * 表示一个 HTTP Cookie
 */
class SetCookie
{
    private array $data;

    private static array $defaults = [
        'Name' => null,
        'Value' => null,
        'Domain' => null,
        'Path' => '/',
        'Max-Age' => null,
        'Expires' => null,
        'Secure' => false,
        'HttpOnly' => false,
        'SameSite' => null,
    ];

    public function __construct(array $data = [])
    {
        $this->data = array_merge(self::$defaults, $data);

        // 标准化 Expires
        if ($this->data['Expires'] !== null && !is_numeric($this->data['Expires'])) {
            $this->data['Expires'] = strtotime($this->data['Expires']);
        }
    }

    /**
     * 从 Set-Cookie 头创建
     */
    public static function fromString(string $cookie): self
    {
        $data = self::$defaults;
        $parts = array_filter(array_map('trim', explode(';', $cookie)));

        // 第一部分是 name=value
        if (isset($parts[0])) {
            [$name, $value] = array_pad(explode('=', $parts[0], 2), 2, '');
            $data['Name'] = $name;
            $data['Value'] = $value;
            array_shift($parts);
        }

        // 解析其他属性
        foreach ($parts as $part) {
            [$key, $value] = array_pad(explode('=', $part, 2), 2, true);
            $key = ucfirst(strtolower($key));

            switch ($key) {
                case 'Expires':
                    $data['Expires'] = strtotime($value);
                    break;
                case 'Max-age':
                    $data['Max-Age'] = (int)$value;
                    break;
                case 'Domain':
                    $data['Domain'] = ltrim($value, '.');
                    break;
                case 'Path':
                    $data['Path'] = $value;
                    break;
                case 'Secure':
                    $data['Secure'] = true;
                    break;
                case 'Httponly':
                    $data['HttpOnly'] = true;
                    break;
                case 'Samesite':
                    $data['SameSite'] = ucfirst(strtolower($value));
                    break;
            }
        }

        return new self($data);
    }

    /**
     * 获取属性
     */
    public function get(string $name)
    {
        return $this->data[$name] ?? null;
    }

    /**
     * 设置属性
     */
    public function with(string $name, $value): self
    {
        $new = clone $this;
        $new->data[$name] = $value;
        return $new;
    }

    /**
     * 获取名称
     */
    public function getName(): ?string
    {
        return $this->data['Name'];
    }

    /**
     * 获取值
     */
    public function getValue(): ?string
    {
        return $this->data['Value'];
    }

    /**
     * 获取域名
     */
    public function getDomain(): ?string
    {
        return $this->data['Domain'];
    }

    /**
     * 获取路径
     */
    public function getPath(): string
    {
        return $this->data['Path'];
    }

    /**
     * 获取过期时间
     */
    public function getExpires(): ?int
    {
        return $this->data['Expires'];
    }

    /**
     * 获取 Max-Age
     */
    public function getMaxAge(): ?int
    {
        return $this->data['Max-Age'];
    }

    /**
     * 是否 Secure
     */
    public function getSecure(): bool
    {
        return (bool)$this->data['Secure'];
    }

    /**
     * 是否 HttpOnly
     */
    public function getHttpOnly(): bool
    {
        return (bool)$this->data['HttpOnly'];
    }

    /**
     * 获取 SameSite
     */
    public function getSameSite(): ?string
    {
        return $this->data['SameSite'];
    }

    /**
     * 检查 Cookie 是否过期
     */
    public function isExpired(): bool
    {
        $expires = $this->getExpires();

        return $expires !== null && time() > $expires;
    }

    /**
     * 检查 Cookie 是否会话 Cookie
     */
    public function isSession(): bool
    {
        return $this->getExpires() === null && $this->getMaxAge() === null;
    }

    /**
     * 检查 Cookie 是否匹配域名
     */
    public function matchesDomain(string $domain): bool
    {
        $cookieDomain = $this->getDomain();

        if ($cookieDomain === null) {
            return true;
        }

        // 精确匹配
        if (strcasecmp($domain, $cookieDomain) === 0) {
            return true;
        }

        // 子域名匹配
        if (str_ends_with($domain, '.' . $cookieDomain)) {
            return true;
        }

        return false;
    }

    /**
     * 检查 Cookie 是否匹配路径
     */
    public function matchesPath(string $path): bool
    {
        $cookiePath = $this->getPath();

        // 精确匹配
        if ($path === $cookiePath) {
            return true;
        }

        // 前缀匹配
        if (str_starts_with($path, $cookiePath)) {
            // 如果 cookiePath 以 / 结尾，或者下一个字符是 /
            if (str_ends_with($cookiePath, '/') || $path[strlen($cookiePath)] === '/') {
                return true;
            }
        }

        return false;
    }

    /**
     * 检查 Cookie 是否应该包含在请求中
     */
    public function shouldSend(string $domain, string $path, bool $secure): bool
    {
        // 检查是否过期
        if ($this->isExpired()) {
            return false;
        }

        // 检查域名
        if (!$this->matchesDomain($domain)) {
            return false;
        }

        // 检查路径
        if (!$this->matchesPath($path)) {
            return false;
        }

        // 检查 Secure 标志
        if ($this->getSecure() && !$secure) {
            return false;
        }

        return true;
    }

    /**
     * 转换为 Cookie 头字符串
     */
    public function toString(): string
    {
        return $this->getName() . '=' . $this->getValue();
    }

    /**
     * 转换为 Set-Cookie 头字符串
     */
    public function toSetCookieString(): string
    {
        $parts = [$this->getName() . '=' . $this->getValue()];

        if ($this->getDomain()) {
            $parts[] = 'Domain=' . $this->getDomain();
        }

        if ($this->getPath()) {
            $parts[] = 'Path=' . $this->getPath();
        }

        if ($this->getExpires()) {
            $parts[] = 'Expires=' . gmdate('D, d M Y H:i:s T', $this->getExpires());
        }

        if ($this->getMaxAge() !== null) {
            $parts[] = 'Max-Age=' . $this->getMaxAge();
        }

        if ($this->getSecure()) {
            $parts[] = 'Secure';
        }

        if ($this->getHttpOnly()) {
            $parts[] = 'HttpOnly';
        }

        if ($this->getSameSite()) {
            $parts[] = 'SameSite=' . $this->getSameSite();
        }

        return implode('; ', $parts);
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}

