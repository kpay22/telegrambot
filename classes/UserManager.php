<?php
/**
 * 用户管理类
 */
class UserManager {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * 获取或创建用户
     */
    public function getOrCreateUser($telegramUser) {
        $user = $this->getUserByTelegramId($telegramUser['id']);
        
        if (!$user) {
            // 创建新用户
            $userData = [
                'telegram_id' => $telegramUser['id'],
                'username' => $telegramUser['username'] ?? null,
                'first_name' => $telegramUser['first_name'] ?? null,
                'last_name' => $telegramUser['last_name'] ?? null,
                'permission_level' => PERMISSION_USER
            ];
            
            $userId = $this->db->insert('users', $userData);
            return $this->getUserById($userId);
        } else {
            // 更新用户信息
            $updateData = [
                'username' => $telegramUser['username'] ?? null,
                'first_name' => $telegramUser['first_name'] ?? null,
                'last_name' => $telegramUser['last_name'] ?? null,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $this->db->update('users', $updateData, 'id = :id', ['id' => $user['id']]);
            return $this->getUserById($user['id']);
        }
    }
    
    /**
     * 通过Telegram ID获取用户
     */
    public function getUserByTelegramId($telegramId) {
        return $this->db->fetch(
            "SELECT * FROM users WHERE telegram_id = :telegram_id",
            ['telegram_id' => $telegramId]
        );
    }
    
    /**
     * 通过用户ID获取用户
     */
    public function getUserById($userId) {
        return $this->db->fetch(
            "SELECT * FROM users WHERE id = :id",
            ['id' => $userId]
        );
    }
    
    /**
     * 设置用户权限
     */
    public function setUserPermission($userId, $permissionLevel) {
        return $this->db->update(
            'users',
            ['permission_level' => $permissionLevel],
            'id = :id',
            ['id' => $userId]
        );
    }
    
    /**
     * 检查用户权限
     */
    public function hasPermission($userId, $requiredLevel) {
        $user = $this->getUserById($userId);
        if (!$user) {
            return false;
        }
        
        return $user['permission_level'] <= $requiredLevel;
    }
    
    /**
     * 检查用户是否为超级管理员
     */
    public function isSuperAdmin($userId) {
        return $this->hasPermission($userId, PERMISSION_SUPER_ADMIN);
    }
    
    /**
     * 检查用户是否为管理员
     */
    public function isAdmin($userId) {
        return $this->hasPermission($userId, PERMISSION_ADMIN);
    }
    
    /**
     * 添加全局权限用户
     */
    public function addGlobalPermission($telegramId, $permissionLevel, $addedBy) {
        $user = $this->getUserByTelegramId($telegramId);
        if (!$user) {
            return false;
        }
        
        return $this->setUserPermission($user['id'], $permissionLevel);
    }
    
    /**
     * 删除全局权限用户
     */
    public function removeGlobalPermission($telegramId) {
        $user = $this->getUserByTelegramId($telegramId);
        if (!$user) {
            return false;
        }
        
        return $this->setUserPermission($user['id'], PERMISSION_USER);
    }
    
    /**
     * 获取用户显示名称
     */
    public function getUserDisplayName($user) {
        if ($user['username']) {
            return '@' . $user['username'];
        }
        
        $name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
        return $name ?: 'User#' . $user['telegram_id'];
    }
    
    /**
     * 获取权限级别名称
     */
    public function getPermissionName($level) {
        switch ($level) {
            case PERMISSION_SUPER_ADMIN:
                return '超级管理员';
            case PERMISSION_ADMIN:
                return '管理员';
            case PERMISSION_OPERATOR:
                return '操作员';
            case PERMISSION_USER:
            default:
                return '普通用户';
        }
    }
}
