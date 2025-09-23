<?php
/**
 * 数据库连接类
 * 
 * @package TelegramAccountingBot
 * @author Your Name
 * @version 1.1.0
 * @since 2024-01-01
 */
class Database {
    private static $instance = null;
    private $connection;
    
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
        $maxRetries = 3;
        $retryDelay = 1; // 秒
        
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_TIMEOUT => 5, // 5秒超时
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET,
                ];
                
                $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
                
                // 测试连接
                $this->connection->query("SELECT 1");
                
                return; // 连接成功，退出重试循环
                
            } catch (PDOException $e) {
                error_log("数据库连接失败 (尝试 {$attempt}/{$maxRetries}): " . $e->getMessage());
                
                if ($attempt === $maxRetries) {
                    throw new Exception("数据库连接失败，已重试 {$maxRetries} 次");
                }
                
                // 等待后重试
                sleep($retryDelay);
                $retryDelay *= 2; // 指数退避
            }
        }
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    public function query($sql, $params = []) {
        try {
            // 检查连接是否有效
            if (!$this->isConnectionValid()) {
                $this->reconnect();
            }
            
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            // 如果是连接相关错误，尝试重连
            if ($this->isConnectionError($e)) {
                try {
                    $this->reconnect();
                    $stmt = $this->connection->prepare($sql);
                    $stmt->execute($params);
                    return $stmt;
                } catch (Exception $retryException) {
                    error_log("数据库重连后查询仍然失败: " . $retryException->getMessage());
                }
            }
            
            error_log("数据库查询失败: " . $e->getMessage() . " SQL: " . $sql);
            throw new Exception("数据库查询失败");
        }
    }
    
    /**
     * 检查连接是否有效
     */
    private function isConnectionValid() {
        try {
            $this->connection->query("SELECT 1");
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * 重新连接数据库
     */
    private function reconnect() {
        $this->connection = null;
        $this->connect();
    }
    
    /**
     * 判断是否为连接相关错误
     */
    private function isConnectionError(PDOException $e) {
        $connectionErrors = [
            '2006', // MySQL server has gone away
            '2013', // Lost connection to MySQL server
            'HY000' // General error
        ];
        
        $errorCode = $e->getCode();
        return in_array($errorCode, $connectionErrors) || 
               strpos($e->getMessage(), 'server has gone away') !== false ||
               strpos($e->getMessage(), 'Lost connection') !== false;
    }
    
    public function fetch($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }
    
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    public function insert($table, $data) {
        $columns = array_keys($data);
        $placeholders = ':' . implode(', :', $columns);
        $sql = "INSERT INTO {$table} (" . implode(', ', $columns) . ") VALUES ({$placeholders})";
        
        $stmt = $this->query($sql, $data);
        return $this->connection->lastInsertId();
    }
    
    public function update($table, $data, $where, $whereParams = []) {
        $setParts = [];
        foreach (array_keys($data) as $column) {
            $setParts[] = "{$column} = :{$column}";
        }
        
        $sql = "UPDATE {$table} SET " . implode(', ', $setParts) . " WHERE {$where}";
        $params = array_merge($data, $whereParams);
        
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }
    
    public function delete($table, $where, $params = []) {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }
    
    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }
    
    public function commit() {
        return $this->connection->commit();
    }
    
    public function rollback() {
        return $this->connection->rollback();
    }
}
