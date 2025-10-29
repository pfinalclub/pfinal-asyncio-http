<?php
/**
 * 网页爬虫示例
 * 演示如何使用并发请求爬取网页
 */

require __DIR__ . '/../../vendor/autoload.php';

use function PFinal\Asyncio\{run, create_task, gather, semaphore};
use PFinal\AsyncioHttp\Client;
use PFinal\AsyncioHttp\Pool;

/**
 * 简单网页爬虫
 */
class WebScraper
{
    private Client $client;
    private array $visited = [];
    private int $maxConcurrency;
    
    public function __construct(int $maxConcurrency = 20)
    {
        $this->maxConcurrency = $maxConcurrency;
        
        $this->client = new Client([
            'timeout' => 30,
            'allow_redirects' => [
                'max' => 3,
            ],
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (compatible; WebScraper/1.0)',
            ],
        ]);
    }
    
    /**
     * 爬取单个页面
     */
    public function fetchPage(string $url): array
    {
        if (isset($this->visited[$url])) {
            return $this->visited[$url];
        }
        
        try {
            $start = microtime(true);
            $response = $this->client->get($url);
            $duration = (microtime(true) - $start) * 1000;
            
            $result = [
                'url' => $url,
                'status' => $response->getStatusCode(),
                'content_type' => $response->getHeaderLine('Content-Type'),
                'content_length' => strlen($response->getBody()->getContents()),
                'duration' => $duration,
                'success' => true,
            ];
            
            $this->visited[$url] = $result;
            return $result;
            
        } catch (\Exception $e) {
            $result = [
                'url' => $url,
                'error' => $e->getMessage(),
                'success' => false,
            ];
            
            $this->visited[$url] = $result;
            return $result;
        }
    }
    
    /**
     * 批量爬取页面
     */
    public function fetchPages(array $urls): array
    {
        $sem = semaphore($this->maxConcurrency);
        $tasks = [];
        
        foreach ($urls as $url) {
            $tasks[] = create_task(function () use ($url, $sem) {
                $sem->acquire();
                try {
                    return $this->fetchPage($url);
                } finally {
                    $sem->release();
                }
            });
        }
        
        return gather(...$tasks);
    }
    
    /**
     * 使用 Pool 批量爬取
     */
    public function fetchPagesWithPool(array $urls): array
    {
        $requests = function () use ($urls) {
            foreach ($urls as $url) {
                yield $url => $this->client->getAsync($url);
            }
        };
        
        $results = [];
        $errors = [];
        
        $pool = new Pool($this->client, $requests(), [
            'concurrency' => $this->maxConcurrency,
            'fulfilled' => function ($response, $url) use (&$results) {
                $results[$url] = [
                    'url' => $url,
                    'status' => $response->getStatusCode(),
                    'content_type' => $response->getHeaderLine('Content-Type'),
                    'content_length' => strlen($response->getBody()->getContents()),
                    'success' => true,
                ];
            },
            'rejected' => function ($error, $url) use (&$errors) {
                $errors[$url] = [
                    'url' => $url,
                    'error' => $error->getMessage(),
                    'success' => false,
                ];
            },
        ]);
        
        $pool->promise()->wait();
        
        return array_merge($results, $errors);
    }
    
    /**
     * 获取统计信息
     */
    public function getStats(): array
    {
        $total = count($this->visited);
        $success = count(array_filter($this->visited, fn($r) => $r['success'] ?? false));
        $failed = $total - $success;
        
        return [
            'total' => $total,
            'success' => $success,
            'failed' => $failed,
        ];
    }
}

/**
 * 使用示例
 */
function main(): void
{
    echo "=== 网页爬虫示例 ===\n\n";
    
    $scraper = new WebScraper(maxConcurrency: 20);
    
    // 准备要爬取的 URL
    $urls = [
        'https://www.php.net/',
        'https://www.php.net/manual/en/',
        'https://www.php.net/downloads',
        'https://www.php.net/docs.php',
        'https://www.php.net/support.php',
        'https://www.php.net/get-involved.php',
        'https://packagist.org/',
        'https://github.com/',
        'https://stackoverflow.com/',
        'https://laravel.com/',
    ];
    
    // 方法 1：使用 gather
    echo "方法 1：使用 gather 并发爬取\n";
    $start = microtime(true);
    $results = $scraper->fetchPages($urls);
    $duration = (microtime(true) - $start) * 1000;
    
    echo "爬取结果：\n";
    foreach ($results as $result) {
        if ($result['success']) {
            echo sprintf(
                "  ✅ %s - %d (%s, %.2f ms)\n",
                $result['url'],
                $result['status'],
                $result['content_type'],
                $result['duration']
            );
        } else {
            echo sprintf(
                "  ❌ %s - %s\n",
                $result['url'],
                $result['error']
            );
        }
    }
    
    echo sprintf("\n总耗时: %.2f ms\n", $duration);
    
    $stats = $scraper->getStats();
    echo sprintf(
        "统计: 总计 %d，成功 %d，失败 %d\n",
        $stats['total'],
        $stats['success'],
        $stats['failed']
    );
    
    echo "\n" . str_repeat('=', 50) . "\n\n";
    
    // 方法 2：使用 Pool
    echo "方法 2：使用 Pool 批量爬取\n";
    
    $moreUrls = [
        'https://symfony.com/',
        'https://www.doctrine-project.org/',
        'https://twig.symfony.com/',
        'https://www.phpunit.de/',
        'https://getcomposer.org/',
    ];
    
    $start = microtime(true);
    $results = $scraper->fetchPagesWithPool($moreUrls);
    $duration = (microtime(true) - $start) * 1000;
    
    echo "爬取结果：\n";
    $successCount = 0;
    $failedCount = 0;
    
    foreach ($results as $result) {
        if ($result['success']) {
            $successCount++;
            echo sprintf(
                "  ✅ %s - %d (%s)\n",
                $result['url'],
                $result['status'],
                $result['content_type']
            );
        } else {
            $failedCount++;
            echo sprintf(
                "  ❌ %s - %s\n",
                $result['url'],
                $result['error']
            );
        }
    }
    
    echo sprintf("\n总耗时: %.2f ms\n", $duration);
    echo sprintf("统计: 成功 %d，失败 %d\n", $successCount, $failedCount);
    
    echo "\n" . str_repeat('=', 50) . "\n\n";
    
    // 性能对比
    echo "性能对比：\n";
    echo "如果串行执行，每个请求平均 500ms，{count($urls)} 个请求需要 " . (count($urls) * 0.5) . " 秒\n";
    echo "使用并发，实际只需 " . ($duration / 1000) . " 秒\n";
    echo "性能提升约 " . round((count($urls) * 0.5) / ($duration / 1000), 1) . " 倍 🚀\n";
}

run(main(...));

