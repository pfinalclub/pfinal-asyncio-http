# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2025-01-24

### 🎉 Initial Stable Release

First stable release of `asyncio-http-core` - a production-grade async HTTP client for the pfinal-asyncio ecosystem.

### Features

#### Core Features

- ✅ PSR-7/PSR-18 compliant HTTP client
- ✅ Native PHP Fiber support (PHP 8.1+)
- ✅ Async I/O with pfinalclub/asyncio
- ✅ Middleware system (onion model)
- ✅ Connection pooling with HTTP Keep-Alive
- ✅ Concurrent request handling
- ✅ Built-in retry mechanism
- ✅ Redirect handling
- ✅ Cookie management
- ✅ Authentication middleware
- ✅ Logging and monitoring
- ✅ PSR-3 logger interface support
- ✅ Comprehensive test suite
- ✅ Full documentation and examples

### Features

#### Core Features

- Async HTTP client with synchronous-looking API
- Zero-config concurrency with `gather()` and `create_task()`
- Automatic connection reuse
- Request/response streaming
- Multipart form data
- File uploads
- Progress tracking

#### Middleware

- RetryMiddleware - Exponential backoff retry logic
- RedirectMiddleware - HTTP redirect following
- AuthMiddleware - Basic and Bearer authentication
- CookieMiddleware - Cookie jar support
- LogMiddleware - PSR-3 logging
- HttpErrorsMiddleware - Exception on HTTP errors
- HistoryMiddleware - Request history tracking
- ProgressMiddleware - Upload/download progress

#### Developer Experience

- Full PSR-7 message implementation
- PSR-18 client interface
- Type-safe API (strict_types)
- Comprehensive PHPDoc
- PHPUnit test suite
- Code style enforcement
- Static analysis ready (PHPStan, Psalm)

### Requirements

- PHP >= 8.1 (Fiber support required)
- pfinalclub/asyncio ^2.1
- Workerman >= 4.1

### Performance

- **Select** (built-in): Baseline performance
- **Event** (optional): 3-5x faster
- **Ev** (recommended): 10-20x faster

---

## Future Releases

See [UPGRADE.md](UPGRADE.md) for version migration guides.

## Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for contribution guidelines.

## Links

- [GitHub Repository](https://github.com/pfinalclub/asyncio-http-core)
- [Parent Project: pfinal-asyncio](https://github.com/pfinalclub/pfinal-asyncio)
- [Issue Tracker](https://github.com/pfinalclub/asyncio-http-core/issues)
- [Documentation](https://github.com/pfinalclub/asyncio-http-core#readme)

