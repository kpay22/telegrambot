<?php
/**
 * 群组管理类
 */
class GroupManager {
    private $db;
    private $redisManager;
    
    public function __construct() {
        $this->db = Database::getInstance();
        require_once 'classes/RedisManager.php';
        $this->redisManager = RedisManager::getInstance();
    }
    
    /**
     * 获取或创建群组
     */
    public function getOrCreateGroup($chat) {
        $group = $this->getGroupByTelegramId($chat['id']);
        
        if (!$group) {
            // 创建新群组
            $groupData = [
                'telegram_group_id' => $chat['id'],
                'group_name' => $chat['title'] ?? null,
                'group_type' => $chat['type']
            ];
            
            $groupId = $this->db->insert('groups', $groupData);
            return $this->getGroupById($groupId);
        } else {
            // 更新群组信息
            $updateData = [
                'group_name' => $chat['title'] ?? null,
                'group_type' => $chat['type'],
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $this->db->update('groups', $updateData, 'id = :id', ['id' => $group['id']]);
            return $this->getGroupById($group['id']);
        }
    }
    
    /**
     * 通过Telegram ID获取群组
     */
    public function getGroupByTelegramId($telegramGroupId) {
        return $this->db->fetch(
            "SELECT * FROM groups WHERE telegram_group_id = :telegram_group_id",
            ['telegram_group_id' => $telegramGroupId]
        );
    }
    
    /**
     * 通过群组ID获取群组
     */
    public function getGroupById($groupId) {
        return $this->db->fetch(
            "SELECT * FROM groups WHERE id = :id",
            ['id' => $groupId]
        );
    }
    
    /**
     * 添加群组操作员
     */
    public function addOperator($groupId, $userId, $addedBy = null) {
        try {
            $data = [
                'group_id' => $groupId,
                'user_id' => $userId,
                'added_by' => $addedBy
            ];
            
            return $this->db->insert('group_operators', $data);
        } catch (Exception $e) {
            // 可能是重复添加
            return false;
        }
    }
    
    /**
     * 移除群组操作员
     */
    public function removeOperator($groupId, $userId) {
        return $this->db->delete(
            'group_operators',
            'group_id = :group_id AND user_id = :user_id',
            ['group_id' => $groupId, 'user_id' => $userId]
        );
    }
    
    /**
     * 检查是否为群组操作员
     */
    public function isOperator($groupId, $userId) {
        $result = $this->db->fetch(
            "SELECT id FROM group_operators WHERE group_id = :group_id AND user_id = :user_id",
            ['group_id' => $groupId, 'user_id' => $userId]
        );
        
        return !empty($result);
    }
    
    /**
     * 获取群组所有操作员
     */
    public function getOperators($groupId) {
        return $this->db->fetchAll(
            "SELECT u.*, go.created_at as added_at 
             FROM users u 
             JOIN group_operators go ON u.id = go.user_id 
             WHERE go.group_id = :group_id
             ORDER BY go.created_at ASC",
            ['group_id' => $groupId]
        );
    }
    
    /**
     * 设置群组配置
     */
    public function setSetting($groupId, $key, $value) {
        // 先尝试更新
        $updated = $this->db->update(
            'group_settings',
            ['setting_value' => $value, 'updated_at' => date('Y-m-d H:i:s')],
            'group_id = :group_id AND setting_key = :setting_key',
            ['group_id' => $groupId, 'setting_key' => $key]
        );
        
        // 如果没有更新到记录，则插入新记录
        if ($updated === 0) {
            $result = $this->db->insert('group_settings', [
                'group_id' => $groupId,
                'setting_key' => $key,
                'setting_value' => $value
            ]);
        } else {
            $result = true;
        }
        
        // 清除相关缓存
        $cacheKey = "group_setting_{$groupId}_{$key}";
        $this->redisManager->delete($cacheKey);
        
        return $result;
    }
    
    /**
     * 获取群组配置
     */
    public function getSetting($groupId, $key, $default = null) {
        // 尝试从缓存获取
        $cacheKey = "group_setting_{$groupId}_{$key}";
        $cachedValue = $this->redisManager->get($cacheKey);
        
        if ($cachedValue !== false) {
            return $cachedValue;
        }
        
        // 从数据库获取
        $result = $this->db->fetch(
            "SELECT setting_value FROM group_settings WHERE group_id = :group_id AND setting_key = :setting_key",
            ['group_id' => $groupId, 'setting_key' => $key]
        );
        
        $value = $result ? $result['setting_value'] : $default;
        
        // 缓存结果（5分钟）
        $this->redisManager->set($cacheKey, $value, 300);
        
        return $value;
    }
    
    /**
     * 获取群组所有配置
     */
    public function getAllSettings($groupId) {
        $results = $this->db->fetchAll(
            "SELECT setting_key, setting_value FROM group_settings WHERE group_id = :group_id",
            ['group_id' => $groupId]
        );
        
        $settings = [];
        foreach ($results as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        
        return $settings;
    }
    
    /**
     * 删除群组配置
     */
    public function deleteSetting($groupId, $key) {
        return $this->db->delete(
            'group_settings',
            'group_id = :group_id AND setting_key = :setting_key',
            ['group_id' => $groupId, 'setting_key' => $key]
        );
    }
    
    /**
     * 设置记账状态
     */
    public function setAccountingStatus($groupId, $status) {
        return $this->db->update(
            'groups',
            ['accounting_status' => $status ? 1 : 0],
            'id = :id',
            ['id' => $groupId]
        );
    }
    
    /**
     * 检查记账是否开启
     */
    public function isAccountingEnabled($groupId) {
        $group = $this->getGroupById($groupId);
        return $group && $group['accounting_status'] == 1;
    }
    
    /**
     * 添加所有群成员为操作员（需要配合Telegram API）
     */
    public function addAllMembersAsOperators($groupId, $memberIds, $addedBy) {
        $this->db->beginTransaction();
        
        try {
            foreach ($memberIds as $userId) {
                $this->addOperator($groupId, $userId, $addedBy);
            }
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            return false;
        }
    }
    
    /**
     * 移除所有群操作员
     */
    public function removeAllOperators($groupId) {
        return $this->db->delete(
            'group_operators',
            'group_id = :group_id',
            ['group_id' => $groupId]
        );
    }
}
