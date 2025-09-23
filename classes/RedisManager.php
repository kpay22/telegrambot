<?php
/**
 * Redis管理器 - 用于分布式锁和缓存
 */

class RedisManager {
    private static $instance = null;
    private $redis;
    private $isAvailable = false;
    
    private function __construct() {
        $this->connect();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function connect() {
        try {
            // 检查Redis扩展是否可用
            if (!extension_loaded('redis')) {
                error_log('Redis扩展未安装，将使用文件锁作为备选方案');
                return;
            }
            
            $this->redis = new Redis();
            $this->redis->connect('127.0.0.1', 6379, 1); // 1秒超时
            $this->redis->ping(); // 测试连接
            $this->isAvailable = true;
            
        } catch (Exception $e) {
            error_log('Redis连接失败: ' . $e->getMessage());
            $this->isAvailable = false;
        }
    }
    
    /**
     * 获取分布式锁
     */
    public function acquireLock($key, $timeout = 10, $retryDelay = 100000) {
        if (!$this->isAvailable) {
            return $this->acquireFileLock($key, $timeout);
        }
        
        $lockKey = "lock:{$key}";
        $identifier = uniqid(gethostname(), true);
        $endTime = microtime(true) + $timeout;
        
        while (microtime(true) < $endTime) {
            if ($this->redis->set($lockKey, $identifier, ['nx', 'ex' => $timeout])) {
                return $identifier;
            }
            
            usleep($retryDelay);
        }
        
        return false;
    }
    
    /**
     * 释放分布式锁
     */
    public function releaseLock($key, $identifier) {
        if (!$this->isAvailable) {
            return $this->releaseFileLock($key, $identifier);
        }
        
        $lockKey = "lock:{$key}";
        $script = "
            if redis.call('get', KEYS[1]) == ARGV[1] then
                return redis.call('del', KEYS[1])
            else
                return 0
            end
        ";
        
        return $this->redis->eval($script, [$lockKey, $identifier], 1);
    }
    
    /**
     * 设置缓存
     */
    public function set($key, $value, $ttl = 3600) {
        if (!$this->isAvailable) {
            return $this->setFileCache($key, $value, $ttl);
        }
        
        $serialized = serialize($value);
        return $this->redis->setex("cache:{$key}", $ttl, $serialized);
    }
    
    /**
     * 获取缓存
     */
    public function get($key) {
        if (!$this->isAvailable) {
            return $this->getFileCache($key);
        }
        
        $value = $this->redis->get("cache:{$key}");
        return $value ? unserialize($value) : false;
    }
    
    /**
     * 删除缓存
     */
    public function delete($key) {
        if (!$this->isAvailable) {
            return $this->deleteFileCache($key);
        }
        
        return $this->redis->del("cache:{$key}");
    }
    
    /**
     * 文件锁备选方案
     */
    private function acquireFileLock($key, $timeout) {
        $lockFile = sys_get_temp_dir() . "/lock_{$key}.lock";
        $identifier = uniqid(gethostname(), true);
        $endTime = microtime(true) + $timeout;
        
        while (microtime(true) < $endTime) {
            if (file_put_contents($lockFile, $identifier, LOCK_EX | LOCK_NB) !== false) {
                return $identifier;
            }
            usleep(100000); // 100ms
        }
        
        return false;
    }
    
    private function releaseFileLock($key, $identifier) {
        $lockFile = sys_get_temp_dir() . "/lock_{$key}.lock";
        if (file_exists($lockFile) && file_get_contents($lockFile) === $identifier) {
            return unlink($lockFile);
        }
        return false;
    }
    
    /**
     * 文件缓存备选方案
     */
    private function setFileCache($key, $value, $ttl) {
        $cacheFile = sys_get_temp_dir() . "/cache_{$key}.tmp";
        $data = [
            'value' => $value,
            'expires' => time() + $ttl
        ];
        return file_put_contents($cacheFile, serialize($data), LOCK_EX) !== false;
    }
    
    private function getFileCache($key) {
        $cacheFile = sys_get_temp_dir() . "/cache_{$key}.tmp";
        if (!file_exists($cacheFile)) {
            return false;
        }
        
        $data = unserialize(file_get_contents($cacheFile));
        if (!$data || $data['expires'] < time()) {
            unlink($cacheFile);
            return false;
        }
        
        return $data['value'];
    }
    
    private function deleteFileCache($key) {
        $cacheFile = sys_get_temp_dir() . "/cache_{$key}.tmp";
        return file_exists($cacheFile) ? unlink($cacheFile) : true;
    }
    
    /**
     * 检查Redis是否可用
     */
    public function isAvailable() {
        return $this->isAvailable;
    }
}
