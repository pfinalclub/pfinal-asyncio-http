<?php
/**
 * 文件下载器示例
 * 演示如何下载文件并显示进度
 */

require __DIR__ . '/../../vendor/autoload.php';

use function PfinalClub\Asyncio\{run, create_task, gather};
use PFinal\AsyncioHttp\Client;
use PFinal\AsyncioHttp\Pool;
use PFinal\AsyncioHttp\Utils;

/**
 * 文件下载器
 */
class FileDownloader
{
    private Client $client;
    private string $downloadDir;
    
    public function __construct(string $downloadDir = '/tmp/downloads')
    {
        $this->downloadDir = $downloadDir;
        
        // 确保目录存在
        if (!is_dir($downloadDir)) {
            mkdir($downloadDir, 0755, true);
        }
        
        $this->client = new Client([
            'timeout' => 300,  // 5 分钟超时
            'stream' => true,  // 流式传输
        ]);
    }
    
    /**
     * 下载单个文件
     */
    public function downloadFile(string $url, ?string $filename = null): array
    {
        if ($filename === null) {
            $filename = basename(parse_url($url, PHP_URL_PATH)) ?: 'download_' . time();
        }
        
        $filepath = $this->downloadDir . '/' . $filename;
        
        echo "⬇️  开始下载: {$url}\n";
        echo "   保存到: {$filepath}\n";
        
        try {
            $start = microtime(true);
            
            // 发送请求
            $response = $this->client->get($url, [
                'sink' => $filepath,  // 直接保存到文件
            ]);
            
            $duration = (microtime(true) - $start);
            $size = filesize($filepath);
            $speed = $size / $duration;
            
            echo sprintf(
                "✅ 下载完成: %s (%.2f MB, %.2f 秒, %.2f MB/s)\n\n",
                $filename,
                $size / (1024 * 1024),
                $duration,
                $speed / (1024 * 1024)
            );
            
            return [
                'url' => $url,
                'filename' => $filename,
                'filepath' => $filepath,
                'size' => $size,
                'duration' => $duration,
                'speed' => $speed,
                'success' => true,
            ];
            
        } catch (\Exception $e) {
            echo "❌ 下载失败: {$e->getMessage()}\n\n";
            
            return [
                'url' => $url,
                'filename' => $filename,
                'error' => $e->getMessage(),
                'success' => false,
            ];
        }
    }
    
    /**
     * 并发下载多个文件
     */
    public function downloadFiles(array $files): array
    {
        $tasks = [];
        
        foreach ($files as $file) {
            if (is_array($file)) {
                $url = $file['url'];
                $filename = $file['filename'] ?? null;
            } else {
                $url = $file;
                $filename = null;
            }
            
            $tasks[] = create_task(fn() => $this->downloadFile($url, $filename));
        }
        
        return gather(...$tasks);
    }
    
    /**
     * 使用 Pool 批量下载
     */
    public function downloadFilesWithPool(array $files, int $concurrency = 5): array
    {
        $requests = function () use ($files) {
            foreach ($files as $file) {
                if (is_array($file)) {
                    $url = $file['url'];
                    $filename = $file['filename'] ?? basename(parse_url($url, PHP_URL_PATH));
                } else {
                    $url = $file;
                    $filename = basename(parse_url($url, PHP_URL_PATH));
                }
                
                $filepath = $this->downloadDir . '/' . $filename;
                
                yield $url => $this->client->getAsync($url, [
                    'sink' => $filepath,
                ]);
            }
        };
        
        $results = [];
        
        $pool = new Pool($this->client, $requests(), [
            'concurrency' => $concurrency,
            'fulfilled' => function ($response, $url) use (&$results) {
                echo "✅ 下载完成: {$url}\n";
                $results[] = [
                    'url' => $url,
                    'status' => $response->getStatusCode(),
                    'success' => true,
                ];
            },
            'rejected' => function ($error, $url) use (&$results) {
                echo "❌ 下载失败: {$url} - {$error->getMessage()}\n";
                $results[] = [
                    'url' => $url,
                    'error' => $error->getMessage(),
                    'success' => false,
                ];
            },
        ]);
        
        $pool->promise()->wait();
        
        return $results;
    }
    
    /**
     * 下载并显示进度（模拟）
     */
    public function downloadWithProgress(string $url, ?string $filename = null): array
    {
        if ($filename === null) {
            $filename = basename(parse_url($url, PHP_URL_PATH)) ?: 'download_' . time();
        }
        
        $filepath = $this->downloadDir . '/' . $filename;
        
        echo "⬇️  开始下载: {$url}\n";
        
        try {
            $start = microtime(true);
            
            // 注意：实际的进度回调需要底层支持
            // 这里只是演示 API
            $response = $this->client->get($url, [
                'sink' => $filepath,
                'progress' => function ($downloadTotal, $downloadCurrent, $uploadTotal, $uploadCurrent) {
                    if ($downloadTotal > 0) {
                        $percent = ($downloadCurrent / $downloadTotal) * 100;
                        echo sprintf(
                            "\r   进度: %.1f%% (%s / %s)",
                            $percent,
                            Utils::formatBytes($downloadCurrent),
                            Utils::formatBytes($downloadTotal)
                        );
                    }
                },
            ]);
            
            echo "\n";
            
            $duration = (microtime(true) - $start);
            $size = filesize($filepath);
            
            echo sprintf(
                "✅ 下载完成: %s (%s, %.2f 秒)\n\n",
                $filename,
                Utils::formatBytes($size),
                $duration
            );
            
            return [
                'url' => $url,
                'filename' => $filename,
                'success' => true,
            ];
            
        } catch (\Exception $e) {
            echo "\n❌ 下载失败: {$e->getMessage()}\n\n";
            
            return [
                'url' => $url,
                'error' => $e->getMessage(),
                'success' => false,
            ];
        }
    }
}

/**
 * 使用示例
 */
function main(): void
{
    echo "=== 文件下载器示例 ===\n\n";
    
    $downloader = new FileDownloader('/tmp/downloads');
    
    // 示例文件（使用一些公开的小文件）
    $files = [
        [
            'url' => 'https://raw.githubusercontent.com/php/php-src/master/README.md',
            'filename' => 'php-readme.md',
        ],
        [
            'url' => 'https://raw.githubusercontent.com/composer/composer/main/README.md',
            'filename' => 'composer-readme.md',
        ],
        [
            'url' => 'https://raw.githubusercontent.com/laravel/laravel/master/README.md',
            'filename' => 'laravel-readme.md',
        ],
    ];
    
    // 方法 1：逐个下载
    echo "方法 1：逐个下载\n";
    echo str_repeat('-', 50) . "\n";
    
    $result = $downloader->downloadFile(
        'https://raw.githubusercontent.com/symfony/symfony/7.0/README.md',
        'symfony-readme.md'
    );
    
    if ($result['success']) {
        echo "文件大小: " . Utils::formatBytes($result['size']) . "\n";
        echo "下载速度: " . Utils::formatBytes($result['speed']) . "/s\n";
    }
    
    echo "\n" . str_repeat('=', 50) . "\n\n";
    
    // 方法 2：并发下载
    echo "方法 2：并发下载多个文件\n";
    echo str_repeat('-', 50) . "\n";
    
    $start = microtime(true);
    $results = $downloader->downloadFiles($files);
    $duration = (microtime(true) - $start);
    
    $successCount = count(array_filter($results, fn($r) => $r['success']));
    $totalSize = array_sum(array_column(
        array_filter($results, fn($r) => $r['success']),
        'size'
    ));
    
    echo sprintf(
        "总结: %d/%d 成功, 总大小 %s, 耗时 %.2f 秒\n",
        $successCount,
        count($results),
        Utils::formatBytes($totalSize),
        $duration
    );
    
    echo "\n" . str_repeat('=', 50) . "\n\n";
    
    // 提示
    echo "💡 提示:\n";
    echo "  - 下载的文件保存在: /tmp/downloads/\n";
    echo "  - 可以通过调整并发数来优化下载速度\n";
    echo "  - 大文件下载时建议使用流式传输（stream: true）\n";
    echo "  - 可以使用 'sink' 选项直接保存到文件\n";
}

run(main(...));

