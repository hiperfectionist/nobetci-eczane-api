<?php

namespace App\Core;

class Cache
{
    private string $cacheDir;
    private int $defaultTtl;

    public function __construct(?string $cacheDir = null, int $defaultTtl = 1800)
    {
        $this->cacheDir = $cacheDir ?? dirname(__DIR__, 2) . '/cache';
        $this->defaultTtl = $defaultTtl;

        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0777, true);
        }
    }

    private function getFilePath(string $key): string
    {
        return $this->cacheDir . '/' . md5($key) . '.cache';
    }

    public function get(string $key): ?array
    {
        $file = $this->getFilePath($key);
        if (!file_exists($file)) {
            return null;
        }

        $content = @file_get_contents($file);
        if (!$content) {
            return null;
        }

        $payload = @json_decode($content, true);
        if (!$payload || !isset($payload['expires_at']) || !isset($payload['data'])) {
            @unlink($file);
            return null;
        }

        if (time() > $payload['expires_at']) {
            @unlink($file);
            return null;
        }

        return $payload['data'];
    }

    public function set(string $key, array $data, ?int $ttl = null): bool
    {
        $file = $this->getFilePath($key);
        $ttl = $ttl ?? $this->defaultTtl;

        $payload = [
            'created_at' => time(),
            'expires_at' => time() + $ttl,
            'data' => $data
        ];

        return (bool) @file_put_contents($file, json_encode($payload, JSON_UNESCAPED_UNICODE));
    }

    public function delete(string $key): bool
    {
        $file = $this->getFilePath($key);
        if (file_exists($file)) {
            return @unlink($file);
        }
        return true;
    }

    public function flush(): bool
    {
        $files = glob($this->cacheDir . '/*.cache');
        if ($files) {
            foreach ($files as $f) {
                @unlink($f);
            }
        }
        return true;
    }
}
