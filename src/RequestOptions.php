<?php

declare(strict_types=1);

namespace PFinal\AsyncioHttp;

/**
 * 请求选项常量
 * 完全兼容 Guzzle RequestOptions
 */
final class RequestOptions
{
    /**
     * 基础 URI
     * @var string
     */
    public const BASE_URI = 'base_uri';

    /**
     * 是否允许重定向
     * @var string
     */
    public const ALLOW_REDIRECTS = 'allow_redirects';

    /**
     * 认证配置
     * @var string
     */
    public const AUTH = 'auth';

    /**
     * 请求体
     * @var string
     */
    public const BODY = 'body';

    /**
     * 证书配置
     * @var string
     */
    public const CERT = 'cert';

    /**
     * Cookie 配置
     * @var string
     */
    public const COOKIES = 'cookies';

    /**
     * 连接超时（秒）
     * @var string
     */
    public const CONNECT_TIMEOUT = 'connect_timeout';

    /**
     * 调试模式
     * @var string
     */
    public const DEBUG = 'debug';

    /**
     * 解码内容
     * @var string
     */
    public const DECODE_CONTENT = 'decode_content';

    /**
     * 请求延迟（毫秒）
     * @var string
     */
    public const DELAY = 'delay';

    /**
     * Expect: 100-continue 配置
     * @var string
     */
    public const EXPECT = 'expect';

    /**
     * 表单参数
     * @var string
     */
    public const FORM_PARAMS = 'form_params';

    /**
     * 请求头
     * @var string
     */
    public const HEADERS = 'headers';

    /**
     * HTTP 错误处理
     * @var string
     */
    public const HTTP_ERRORS = 'http_errors';

    /**
     * IDN 转换
     * @var string
     */
    public const IDN_CONVERSION = 'idn_conversion';

    /**
     * JSON 数据
     * @var string
     */
    public const JSON = 'json';

    /**
     * 多部分数据
     * @var string
     */
    public const MULTIPART = 'multipart';

    /**
     * 接收到头部时的回调
     * @var string
     */
    public const ON_HEADERS = 'on_headers';

    /**
     * 传输统计回调
     * @var string
     */
    public const ON_STATS = 'on_stats';

    /**
     * 进度回调
     * @var string
     */
    public const PROGRESS = 'progress';

    /**
     * 代理配置
     * @var string
     */
    public const PROXY = 'proxy';

    /**
     * 查询参数
     * @var string
     */
    public const QUERY = 'query';

    /**
     * 读取超时（秒）
     * @var string
     */
    public const READ_TIMEOUT = 'read_timeout';

    /**
     * 保存响应到文件
     * @var string
     */
    public const SINK = 'sink';

    /**
     * SSL 密钥
     * @var string
     */
    public const SSL_KEY = 'ssl_key';

    /**
     * 流式响应
     * @var string
     */
    public const STREAM = 'stream';

    /**
     * 同步模式
     * @var string
     */
    public const SYNCHRONOUS = 'synchronous';

    /**
     * 超时（秒）
     * @var string
     */
    public const TIMEOUT = 'timeout';

    /**
     * SSL 证书验证
     * @var string
     */
    public const VERIFY = 'verify';

    /**
     * HTTP 协议版本
     * @var string
     */
    public const VERSION = 'version';

    /**
     * 强制 IP 解析
     * @var string
     */
    public const FORCE_IP_RESOLVE = 'force_ip_resolve';

    private function __construct()
    {
        // 防止实例化
    }
}

