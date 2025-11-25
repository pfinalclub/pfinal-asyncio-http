# AsyncIO HTTP Core

<div align="center">

🚀 **Production-Grade Async HTTP Client for PHP**

[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.1-blue)](https://www.php.net/)
[![Asyncio Version](https://img.shields.io/badge/asyncio-%5E3.0-purple)](https://github.com/pfinalclub/pfinal-asyncio)
[![PSR-7](https://img.shields.io/badge/PSR--7-compatible-orange)](https://www.php-fig.org/psr/psr-7/)
[![PSR-18](https://img.shields.io/badge/PSR--18-compatible-orange)](https://www.php-fig.org/psr/psr-18/)

**[English](README.md)** | [中文文档](README_CN.md)

---

*Part of the [pfinal-asyncio](https://github.com/pfinalclub/pfinal-asyncio) ecosystem*

</div>

## 📖 Overview

**AsyncIO HTTP Core** is a production-grade, high-performance async HTTP client built on top of the [pfinal-asyncio](https://github.com/pfinalclub/pfinal-asyncio) framework. It leverages PHP 8.1+ Fiber technology to provide true asynchronous I/O with a clean, synchronous-looking API.

### 🎯 Key Features

- **🚀 True Async I/O** - Native PHP 8.1+ Fiber, zero blocking
- **⚡ Zero-Config Concurrency** - Built-in `gather()` and `Semaphore` support
- **📦 PSR Standards** - Full PSR-7 (HTTP Message) & PSR-18 (HTTP Client) compliance
- **🔧 Middleware System** - Flexible onion-model middleware architecture
- **🎨 Elegant API** - Intuitive, `requests`-like interface
- **🔄 Connection Reuse** - Automatic HTTP Keep-Alive with connection pooling
- **🛡️ Production Ready** - Battle-tested error handling and retry policies
- **📊 Monitoring** - Built-in metrics and performance tracking
- **🌐 HTTP/1.1 & HTTP/2** - Protocol version negotiation support

## 📋 Requirements

| Requirement | Version | Notes |
|------------|---------|-------|
| **PHP** | >= 8.1 | Fiber support required |
| **pfinalclub/asyncio** | ^3.0 | Core async runtime |
| **Workerman** | >= 4.1 | Event loop (auto-installed) |
| **ext-ev** (optional) | * | 10-20x performance boost 🚀 |
| **ext-event** (optional) | * | 3-5x performance boost ⚡ |

## 📦 Installation

```bash
composer require pfinalclub/asyncio-http-core
```

### 🔥 Performance Boost (Recommended)

For production environments, install the `ev` extension for maximum performance:

```bash
# macOS
brew install libev
pecl install ev

# Ubuntu/Debian
sudo apt-get install libev-dev
pecl install ev

# CentOS/RHEL
sudo yum install libev-devel
pecl install ev
```

**Performance Comparison:**

| Event Loop | Throughput | Speed |
|-----------|-----------|-------|
| Select (default) | 80 req/s | 1x baseline |
| Event | 322 req/s | 4x faster ⚡ |
| Ev | 833 req/s | **10.4x faster** 🚀 |

## 🚀 Quick Start

### Basic Request

```php
<?php
require 'vendor/autoload.php';

use PFinal\AsyncioHttp\Client;
use function PfinalClub\Asyncio\run;

run(function() {
$client = new Client();

    // Simple GET request
    $response = $client->get('https://api.github.com/users/octocat');
echo $response->getBody();

    // POST with JSON
$response = $client->post('https://api.example.com/users', [
        'json' => ['name' => 'Alice', 'email' => 'alice@example.com']
]);
    
    echo "Status: {$response->getStatusCode()}\n";
});
```

### Concurrent Requests

```php
use function PfinalClub\Asyncio\{run, create_task, gather};

run(function() {
    $client = new Client();
    
    // Create concurrent tasks
    $tasks = [
        create_task(fn() => $client->get('https://api.github.com/users/octocat')),
        create_task(fn() => $client->get('https://api.github.com/users/torvalds')),
        create_task(fn() => $client->get('https://api.github.com/users/gvanrossum')),
    ];
    
    // Execute concurrently and wait for all
    $responses = gather(...$tasks);
    
    foreach ($responses as $i => $response) {
        echo "User {$i}: {$response->getStatusCode()}\n";
    }
});
```

### Batch Requests with Pool

```php
use PFinal\AsyncioHttp\Pool;

run(function() {
    $client = new Client();
    
    // Create 100 requests
    $requests = [];
        for ($i = 1; $i <= 100; $i++) {
        $requests[] = fn() => $client->get("https://api.example.com/items/{$i}");
    }
    
    // Execute with concurrency limit of 25
    $results = Pool::batch($client, $requests, [
        'concurrency' => 25,
        'fulfilled' => fn($response, $index) => echo "✅ Request {$index} succeeded\n",
        'rejected' => fn($e, $index) => echo "❌ Request {$index} failed: {$e->getMessage()}\n",
    ]);
    
    $successCount = count(array_filter($results, fn($r) => $r['state'] === 'fulfilled'));
    echo "Success: {$successCount}/100\n";
});
```

## 💡 Why No `Async` Methods?

Unlike traditional Promise-based async libraries, **pfinalclub/asyncio** uses **PHP Fiber**. In Fiber, operations **look synchronous but execute asynchronously**.

```php
// ❌ Traditional async libraries (Guzzle, ReactPHP)
$promise = $client->getAsync('https://api.example.com');
$response = $promise->wait();  // Explicit wait

// ✅ pfinalclub/asyncio (Fiber-based)
$response = $client->get('https://api.example.com');  // Auto-async!
```

**The magic:** When called inside `run()` or a Fiber, operations automatically yield control to the event loop, enabling true concurrency without callbacks or explicit promises.

## 🔧 Advanced Usage

### Middleware System

```php
use PFinal\AsyncioHttp\Handler\{HandlerStack, AsyncioHandler};
use PFinal\AsyncioHttp\Middleware\{RetryMiddleware, RedirectMiddleware, LogMiddleware};

run(function() {
    $handler = new AsyncioHandler();
    $stack = HandlerStack::create($handler);
    
    // Add retry middleware with exponential backoff
    $stack->push(new RetryMiddleware([
        'max' => 3,
        'delay' => RetryMiddleware::exponentialBackoff(500, 5000),
        'on_retry' => fn($attempt) => echo "Retry attempt {$attempt}\n",
    ]), 'retry');
    
    // Add redirect middleware
    $stack->push(new RedirectMiddleware(['max' => 5]), 'redirect');
    
    // Add logging middleware
    $stack->push(new LogMiddleware($logger), 'log');
    
    $client = new Client(['handler' => $stack]);
    
    // Requests automatically retry, redirect, and log
    $response = $client->get('https://api.example.com/data');
});
```

### Built-in Middleware

| Middleware | Description |
|-----------|-------------|
| `RetryMiddleware` | Automatic retry with exponential backoff |
| `RedirectMiddleware` | HTTP redirect handling (301, 302, etc.) |
| `AuthMiddleware` | Basic/Bearer authentication |
| `CookieMiddleware` | Cookie jar management |
| `LogMiddleware` | Request/response logging |
| `HistoryMiddleware` | Request history tracking |
| `HttpErrorsMiddleware` | Convert HTTP errors to exceptions |
| `ProgressMiddleware` | Upload/download progress tracking |

### Request Options

```php
$response = $client->request('POST', 'https://api.example.com/data', [
    // Query parameters
    'query' => ['page' => 1, 'limit' => 20],
    
    // Headers
    'headers' => [
        'User-Agent' => 'MyApp/1.0',
        'Accept' => 'application/json',
    ],
    
    // JSON body
    'json' => ['name' => 'Bob', 'age' => 30],
    
    // Form data
    'form_params' => ['username' => 'bob', 'password' => 'secret'],
    
    // Raw body
    'body' => 'raw data',
    
    // Timeout (seconds)
    'timeout' => 10,
    
    // SSL verification
    'verify' => true,
    
    // Retry configuration
    'retry' => [
        'max' => 3,
        'delay' => 1000,  // milliseconds
    ],
    
    // Redirect configuration
    'allow_redirects' => [
        'max' => 5,
        'strict' => false,
    ],
    
    // Proxy
    'proxy' => [
        'http' => 'tcp://proxy.example.com:8080',
        'https' => 'tcp://proxy.example.com:8080',
    ],
]);
```

## 🎯 Real-World Examples

### Building an API Client

```php
class GitHubClient
{
    private Client $client;
    
    public function __construct(string $token)
    {
        $this->client = new Client([
            'base_uri' => 'https://api.github.com',
            'headers' => [
                'Authorization' => "Bearer {$token}",
                'Accept' => 'application/vnd.github.v3+json',
            ],
            'timeout' => 10,
        ]);
    }
    
    public function getUser(string $username): array
    {
        $response = $this->client->get("/users/{$username}");
        return json_decode($response->getBody(), true);
    }
    
    public function getReposConcurrently(string $username, int $pages = 3): array
    {
        // Fetch multiple pages concurrently
        $tasks = [];
        for ($page = 1; $page <= $pages; $page++) {
            $tasks[] = create_task(fn() => $this->client->get("/users/{$username}/repos", [
                'query' => ['page' => $page, 'per_page' => 100]
            ]));
        }
        
        $responses = gather(...$tasks);
        
        $repos = [];
        foreach ($responses as $response) {
            $repos = array_merge($repos, json_decode($response->getBody(), true));
        }
        
        return $repos;
    }
}

// Usage
run(function() {
    $github = new GitHubClient('your-token-here');
    
    $user = $github->getUser('octocat');
    echo "User: {$user['name']}\n";
    
    $repos = $github->getReposConcurrently('octocat', 3);
    echo "Total repos: " . count($repos) . "\n";
});
```

### Web Scraping

```php
run(function() {
    $client = new Client(['timeout' => 10]);
    
    // Fetch homepage
    $response = $client->get('https://news.ycombinator.com');
    preg_match_all('/<a href="(item\?id=\d+)">/', $response->getBody(), $matches);
    $links = array_slice($matches[1], 0, 30);
    
    // Scrape all links concurrently (10 concurrent requests)
    $tasks = array_map(
        fn($link) => fn() => $client->get("https://news.ycombinator.com/{$link}"),
        $links
    );
    
    $results = Pool::batch($client, $tasks, [
        'concurrency' => 10,
        'fulfilled' => fn($response, $i) => echo "✅ Scraped: {$links[$i]}\n",
        'rejected' => fn($e, $i) => echo "❌ Failed: {$links[$i]}\n",
    ]);
    
    echo "Scraped: " . count($results) . " pages\n";
});
```

## 🔍 Comparison with Other Libraries

| Feature | AsyncIO HTTP | Guzzle | ReactPHP | Amphp |
|---------|--------------|--------|----------|-------|
| **Base Technology** | PHP Fiber | cURL | Event Loop | Event Loop |
| **Async Model** | Native Coroutine | Sync/Promise | Promise/Callback | Promise/Generator |
| **Code Style** | Sync-looking (actually async) | Synchronous | Callback-heavy | Generator-based |
| **Performance** | ⭐⭐⭐⭐⭐ | ⭐⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐⭐⭐ |
| **Learning Curve** | Easy 📚 | Easy 📚 | Steep 📚📚📚 | Moderate 📚📚 |
| **Concurrency Control** | Built-in | Manual | Complex | Built-in |
| **PSR Standards** | ✅ PSR-7/18 | ✅ PSR-7/18 | ❌ | ✅ PSR-7 |
| **Middleware** | ✅ Onion Model | ✅ Onion Model | Manual | Manual |

## 📚 Documentation

### Core Documentation

- [API Reference](docs/api-reference.md)
- [Middleware Guide](docs/middleware.md)
- [Concurrent Requests](docs/concurrent-requests.md)
- [Error Handling](docs/error-handling.md)
- [Performance Tuning](docs/performance.md)

### Examples

Explore the `examples/` directory for complete working examples:

- `01_basic_request.php` - Basic HTTP requests
- `02_concurrent_requests.php` - Concurrent request patterns
- `03_pool_example.php` - Pool batch processing
- `04_middleware_auth.php` - Authentication middleware
- `05_retry_middleware.php` - Retry strategies

### Ecosystem Packages

Part of the **pfinal-asyncio** ecosystem:

- [**pfinalclub/asyncio**](https://github.com/pfinalclub/pfinal-asyncio) - Core async runtime
- [**pfinalclub/asyncio-database**](https://github.com/pfinalclub/asyncio-database) - Async database pool
- [**pfinalclub/asyncio-redis**](https://github.com/pfinalclub/asyncio-redis) - Async Redis client

## 🧪 Testing

```bash
# Run all tests
composer test

# Run specific test suites
composer test:unit
composer test:integration

# Generate coverage report
composer test:coverage

# Run static analysis
composer phpstan
composer psalm
composer analyse

# Fix code style
composer cs-fix

# Run complete QA suite
composer qa
```

## 📊 Performance Benchmarks

Run benchmarks to see performance metrics:

```bash
composer benchmark
```

Example results (100 concurrent requests):

```
Event Loop    | Time (s) | Throughput | Speed
--------------+----------+------------+-------
Select        |   1.25   |  80 req/s  | 1x
Event         |   0.31   | 322 req/s  | 4x ⚡
Ev            |   0.12   | 833 req/s  | 10.4x 🚀
```

## 🤝 Contributing

Contributions are welcome! Please read our [Contributing Guide](CONTRIBUTING.md) for details.

### Development Setup

```bash
git clone https://github.com/pfinalclub/asyncio-http-core.git
cd asyncio-http-core
composer install
composer test
```

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🙏 Acknowledgments

- [**pfinalclub/asyncio**](https://github.com/pfinalclub/pfinal-asyncio) - Core async framework
- [**Workerman**](https://www.workerman.net/) - High-performance event loop
- [**Python asyncio**](https://docs.python.org/3/library/asyncio.html) - API design inspiration
- [**Guzzle**](https://github.com/guzzle/guzzle) - PSR standards reference

## 📞 Support

- **Documentation**: [GitHub Wiki](https://github.com/pfinalclub/asyncio-http-core/wiki)
- **Issues**: [GitHub Issues](https://github.com/pfinalclub/asyncio-http-core/issues)
- **Discussions**: [GitHub Discussions](https://github.com/pfinalclub/asyncio-http-core/discussions)
- **Parent Project**: [pfinal-asyncio](https://github.com/pfinalclub/pfinal-asyncio)

## 🌟 Star History

If you find this project useful, please consider giving it a star! ⭐

---

<div align="center">

**Version**: 1.0.0  
**Release Date**: 2025-01-24  
**Status**: Stable Release

🚀 **Production-Grade Async HTTP Client for PHP!**

*Built with ❤️ by the pfinal-asyncio team*

</div>
