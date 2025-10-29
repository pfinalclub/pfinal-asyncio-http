<?php
/**
 * 完整的 API 客户端示例
 * 演示如何构建一个生产级的 API 客户端
 */

require __DIR__ . '/../../vendor/autoload.php';

use function PFinal\Asyncio\{run, create_task, gather};
use PFinal\AsyncioHttp\Client;
use PFinal\AsyncioHttp\Handler\HandlerStack;
use PFinal\AsyncioHttp\Middleware\Middleware;
use PFinal\AsyncioHttp\Cookie\FileCookieJar;

/**
 * GitHub API 客户端
 */
class GitHubApiClient
{
    private Client $client;
    private string $token;
    
    public function __construct(string $token, array $options = [])
    {
        $this->token = $token;
        
        // 创建处理器栈
        $stack = HandlerStack::create();
        
        // 添加重试策略
        $stack->push(Middleware::retry([
            'max' => 3,
            'delay' => Middleware\RetryMiddleware::exponentialBackoff(1000),
            'decide' => Middleware\RetryMiddleware::statusCodeDecider([500, 502, 503]),
            'on_retry' => function ($attempt, $request, $error) {
                echo "⚠️  重试第 {$attempt} 次: {$request->getUri()}\n";
            },
        ]), 'retry');
        
        // 添加日志（如果提供了 logger）
        if (isset($options['logger'])) {
            $stack->push(Middleware::log($options['logger']), 'log');
        }
        
        // 添加认证
        $stack->push(function ($handler) {
            return function ($request, $options) use ($handler) {
                $request = $request
                    ->withHeader('Authorization', 'token ' . $this->token)
                    ->withHeader('Accept', 'application/vnd.github.v3+json')
                    ->withHeader('User-Agent', 'PFinal-AsyncIO-HTTP/1.0');
                
                return $handler($request, $options);
            };
        }, 'auth');
        
        // 创建客户端
        $this->client = new Client(array_merge([
            'handler' => $stack,
            'base_uri' => 'https://api.github.com',
            'timeout' => 30,
            'http_errors' => false,  // 手动处理错误
        ], $options));
    }
    
    /**
     * 获取用户信息
     */
    public function getUser(string $username): array
    {
        $response = $this->client->get("/users/{$username}");
        return $this->handleResponse($response);
    }
    
    /**
     * 获取用户的仓库列表
     */
    public function getUserRepos(string $username, int $page = 1, int $perPage = 30): array
    {
        $response = $this->client->get("/users/{$username}/repos", [
            'query' => [
                'page' => $page,
                'per_page' => $perPage,
                'sort' => 'updated',
            ],
        ]);
        
        return $this->handleResponse($response);
    }
    
    /**
     * 获取仓库信息
     */
    public function getRepo(string $owner, string $repo): array
    {
        $response = $this->client->get("/repos/{$owner}/{$repo}");
        return $this->handleResponse($response);
    }
    
    /**
     * 并发获取多个仓库
     */
    public function getRepos(array $repos): array
    {
        $tasks = [];
        foreach ($repos as $key => $repo) {
            [$owner, $name] = explode('/', $repo);
            $tasks[$key] = create_task(fn() => $this->getRepo($owner, $name));
        }
        
        return gather(...$tasks);
    }
    
    /**
     * 搜索仓库
     */
    public function searchRepos(string $query, int $page = 1): array
    {
        $response = $this->client->get('/search/repositories', [
            'query' => [
                'q' => $query,
                'page' => $page,
                'per_page' => 30,
            ],
        ]);
        
        return $this->handleResponse($response);
    }
    
    /**
     * 获取 API 限流信息
     */
    public function getRateLimit(): array
    {
        $response = $this->client->get('/rate_limit');
        return $this->handleResponse($response);
    }
    
    /**
     * 处理响应
     */
    private function handleResponse($response): array
    {
        $statusCode = $response->getStatusCode();
        $body = json_decode($response->getBody()->getContents(), true);
        
        if ($statusCode >= 400) {
            throw new \RuntimeException(
                "GitHub API 错误 ({$statusCode}): " . ($body['message'] ?? 'Unknown error')
            );
        }
        
        return $body;
    }
}

/**
 * 使用示例
 */
function main(): void
{
    // 创建客户端（需要 GitHub Token）
    $token = getenv('GITHUB_TOKEN') ?: 'your-github-token-here';
    $client = new GitHubApiClient($token);
    
    echo "=== GitHub API 客户端示例 ===\n\n";
    
    // 1. 获取用户信息
    echo "1. 获取用户信息\n";
    try {
        $user = $client->getUser('octocat');
        echo "用户名: {$user['login']}\n";
        echo "名称: {$user['name']}\n";
        echo "公开仓库: {$user['public_repos']}\n";
        echo "关注者: {$user['followers']}\n";
        echo "\n";
    } catch (\Exception $e) {
        echo "错误: {$e->getMessage()}\n\n";
    }
    
    // 2. 获取用户仓库
    echo "2. 获取用户仓库（前5个）\n";
    try {
        $repos = $client->getUserRepos('octocat', 1, 5);
        foreach ($repos as $repo) {
            echo "  - {$repo['name']} (⭐ {$repo['stargazers_count']})\n";
        }
        echo "\n";
    } catch (\Exception $e) {
        echo "错误: {$e->getMessage()}\n\n";
    }
    
    // 3. 并发获取多个仓库
    echo "3. 并发获取多个仓库\n";
    try {
        $repoList = [
            'php/php-src',
            'laravel/laravel',
            'symfony/symfony',
        ];
        
        $start = microtime(true);
        $repos = $client->getRepos($repoList);
        $duration = (microtime(true) - $start) * 1000;
        
        foreach ($repos as $repo) {
            echo "  - {$repo['full_name']} (⭐ {$repo['stargazers_count']})\n";
        }
        echo sprintf("  耗时: %.2f ms\n", $duration);
        echo "\n";
    } catch (\Exception $e) {
        echo "错误: {$e->getMessage()}\n\n";
    }
    
    // 4. 搜索仓库
    echo "4. 搜索仓库: 'php async'\n";
    try {
        $result = $client->searchRepos('php async', 1);
        echo "找到 {$result['total_count']} 个结果，显示前 5 个:\n";
        
        foreach (array_slice($result['items'], 0, 5) as $repo) {
            echo "  - {$repo['full_name']} (⭐ {$repo['stargazers_count']})\n";
        }
        echo "\n";
    } catch (\Exception $e) {
        echo "错误: {$e->getMessage()}\n\n";
    }
    
    // 5. 检查限流
    echo "5. API 限流信息\n";
    try {
        $rateLimit = $client->getRateLimit();
        $core = $rateLimit['rate'];
        echo "剩余请求: {$core['remaining']}/{$core['limit']}\n";
        echo "重置时间: " . date('Y-m-d H:i:s', $core['reset']) . "\n";
    } catch (\Exception $e) {
        echo "错误: {$e->getMessage()}\n";
    }
}

run(main(...));

