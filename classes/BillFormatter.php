<?php
/**
 * 账单格式化类 - 生成自定义格式的账单
 */

require_once 'classes/GroupManager.php';
require_once 'classes/ButtonManager.php';
require_once 'classes/RedisManager.php';

class BillFormatter {
    private $db;
    private $groupManager;
    private $buttonManager;
    private $redisManager;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->groupManager = new GroupManager();
        $this->buttonManager = new ButtonManager();
        $this->redisManager = RedisManager::getInstance();
    }
    
    /**
     * 生成自定义格式的账单
     */
    public function generateCustomBill($groupId, $limit = 10) {
        try {
            // 获取今日账单数据
            $startDate = date('Y-m-d 00:00:00');
            $endDate = date('Y-m-d 23:59:59');
            
            // 获取入账记录
            $incomeRecords = $this->getIncomeRecords($groupId, $startDate, $endDate, $limit);
            $incomeCount = $this->getTransactionCount($groupId, 'income', $startDate, $endDate);
            
            // 获取下发记录
            $distributionRecords = $this->getDistributionRecords($groupId, $startDate, $endDate, $limit);
            $distributionCount = $this->getTransactionCount($groupId, 'distribution', $startDate, $endDate);
            
            // 获取统计数据
            $totalIncome = $this->getTotalAmount($groupId, 'income', $startDate, $endDate);
            $totalDistribution = $this->getTotalAmount($groupId, 'distribution', $startDate, $endDate);
            
            // 获取当前费率（仅用于显示）
            $feeRate = $this->groupManager->getSetting($groupId, 'fee_rate', 70);
            
            // 重新计算应下发金额：所有入账交易的 原始金额 × 费率%（不只是最近10条）
            $allIncomeRecords = $this->getAllIncomeRecords($groupId, $startDate, $endDate);
            $shouldDistribute = 0;
            foreach ($allIncomeRecords as $record) {
                $shouldDistribute += $record['original_amount'] * ($record['fee_rate'] / 100);
            }
            $undistributed = $shouldDistribute - $totalDistribution;
            
            // 格式化账单
            $bill = $this->formatBill(
                $incomeRecords, $incomeCount,
                $distributionRecords, $distributionCount,
                $totalIncome, $totalDistribution,
                $feeRate, $shouldDistribute, $undistributed, $groupId
            );
            
            return $bill;
            
        } catch (Exception $e) {
            error_log('生成账单失败: ' . $e->getMessage());
            return '❌ 生成账单失败';
        }
    }
    
    /**
     * 获取入账记录
     */
    private function getIncomeRecords($groupId, $startDate, $endDate, $limit) {
        $sql = "SELECT t.*, u.username, u.first_name 
                FROM transactions t
                LEFT JOIN users u ON t.user_id = u.id
                WHERE t.group_id = ? 
                AND t.transaction_type = 'income' 
                AND t.is_deleted = 0
                AND t.created_at BETWEEN ? AND ?
                ORDER BY t.created_at DESC 
                LIMIT ?";
        
        return $this->db->fetchAll($sql, [$groupId, $startDate, $endDate, $limit]);
    }
    
    /**
     * 获取所有入账记录（用于计算总应下发金额）
     */
    private function getAllIncomeRecords($groupId, $startDate, $endDate) {
        $sql = "SELECT original_amount, fee_rate 
                FROM transactions 
                WHERE group_id = ? 
                AND transaction_type = 'income' 
                AND is_deleted = 0
                AND created_at BETWEEN ? AND ?";
        
        return $this->db->fetchAll($sql, [$groupId, $startDate, $endDate]);
    }
    
    /**
     * 获取下发记录
     */
    private function getDistributionRecords($groupId, $startDate, $endDate, $limit) {
        $sql = "SELECT t.*, u.username, u.first_name 
                FROM transactions t
                LEFT JOIN users u ON t.user_id = u.id
                WHERE t.group_id = ? 
                AND t.transaction_type = 'distribution' 
                AND t.is_deleted = 0
                AND t.created_at BETWEEN ? AND ?
                ORDER BY t.created_at DESC 
                LIMIT ?";
        
        return $this->db->fetchAll($sql, [$groupId, $startDate, $endDate, $limit]);
    }
    
    /**
     * 获取交易数量
     */
    private function getTransactionCount($groupId, $type, $startDate, $endDate) {
        $sql = "SELECT COUNT(*) as count 
                FROM transactions 
                WHERE group_id = :group_id 
                AND transaction_type = :type 
                AND is_deleted = 0
                AND created_at BETWEEN :start_date AND :end_date";
        
        $result = $this->db->fetch($sql, [
            'group_id' => $groupId,
            'type' => $type,
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);
        
        return $result['count'];
    }
    
    /**
     * 获取总金额
     */
    private function getTotalAmount($groupId, $type, $startDate, $endDate) {
        $sql = "SELECT COALESCE(SUM(amount), 0) as total 
                FROM transactions 
                WHERE group_id = :group_id 
                AND transaction_type = :type 
                AND is_deleted = 0
                AND created_at BETWEEN :start_date AND :end_date";
        
        $result = $this->db->fetch($sql, [
            'group_id' => $groupId,
            'type' => $type,
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);
        
        return floatval($result['total']);
    }
    
    /**
     * 格式化账单输出
     */
    private function formatBill($incomeRecords, $incomeCount, $distributionRecords, $distributionCount, 
                               $totalIncome, $totalDistribution, $feeRate, $shouldDistribute, $undistributed, $groupId) {
        
        $bill = "";
        
        // 已入账部分
        $bill .= "<b>已入账 ({$incomeCount}笔)</b>\n";
        if (empty($incomeRecords)) {
            $bill .= "暂无入账记录\n";
        } else {
            foreach ($incomeRecords as $record) {
                $time = date('H:i', strtotime($record['created_at']));
                
                // 原始金额（不修改）
                $originalAmount = $record['original_amount'] ?: $record['amount'];
                $displayAmount = $this->formatAmount($originalAmount);
                
                // 使用记录中保存的费率（历史费率）
                $recordFeeRate = $record['fee_rate'];
                $feeAmount = $originalAmount * ($recordFeeRate / 100);
                $displayFeeAmount = $this->formatAmount($feeAmount);
                
                // 格式化费率显示（去掉小数点）
                $displayFeeRate = $this->formatFeeRate($recordFeeRate);
                
                // 构建可点击的金额链接
                if ($record['message_id']) {
                    $messageLink = $this->getMessageLink($groupId, $record['message_id']);
                    $clickableAmount = $messageLink ? "<a href=\"{$messageLink}\"><b>{$displayAmount}</b></a>" : $displayAmount;
                } else {
                    $clickableAmount = $displayAmount;
                }
                
                $bill .= "{$time}  {$clickableAmount} {$displayFeeRate} = {$displayFeeAmount}\n";
            }
        }
        
        $bill .= "\n<b>已下发 ({$distributionCount}笔)</b>\n";
        if (empty($distributionRecords)) {
            $bill .= "暂无下发记录\n";
        } else {
            foreach ($distributionRecords as $record) {
                $time = date('H:i', strtotime($record['created_at']));
                
                // 下发金额格式化
                $originalAmount = $record['original_amount'] ?: $record['amount'];
                $displayAmount = $this->formatAmount($originalAmount);
                
                // 构建可点击的金额链接
                if ($record['message_id']) {
                    $messageLink = $this->getMessageLink($groupId, $record['message_id']);
                    $clickableAmount = $messageLink ? "<a href=\"{$messageLink}\"><b>{$displayAmount}</b></a>" : $displayAmount;
                } else {
                    $clickableAmount = $displayAmount;
                }
                
                $bill .= "{$time}  {$clickableAmount}\n";
            }
        }
        
        // 统计信息
        $bill .= "\n总入款额：" . number_format($totalIncome, 0, '.', '') . "\n";
        $bill .= "当前费率：{$feeRate}%\n\n";
        $bill .= "应下发：" . number_format($shouldDistribute, 1, '.', '') . " CNY\n";
        $bill .= "已下发：" . number_format($totalDistribution, 1, '.', '') . " CNY\n";
        $bill .= "未下发：" . number_format($undistributed, 1, '.', '') . " CNY\n";
        
        return $bill;
    }
    
    /**
     * 检查是否有数据变化并发送账单（带分布式锁）
     */
    public function checkAndSendBill($groupId, $chatId, $telegramBot) {
        $lockKey = "bill_send_{$groupId}";
        $lockIdentifier = $this->redisManager->acquireLock($lockKey, 5); // 5秒超时
        
        if (!$lockIdentifier) {
            // 获取锁失败，可能是其他进程正在发送账单
            error_log("获取账单发送锁失败，群组ID: {$groupId}");
            return false;
        }
        
        try {
            // 获取当前账单数据的哈希值
            $currentBill = $this->generateCustomBill($groupId);
            $currentHash = md5($currentBill);
            
            // 获取上次的哈希值
            $lastHash = $this->groupManager->getSetting($groupId, 'last_bill_hash', '');
            
            // 如果数据有变化，发送新账单
            if ($currentHash !== $lastHash) {
                // 生成内联键盘（支持多按钮）
                $keyboard = $this->buttonManager->generateInlineKeyboard($groupId);
                
                $telegramBot->sendMessage($chatId, $currentBill, $keyboard);
                
                // 保存新的哈希值
                $this->groupManager->setSetting($groupId, 'last_bill_hash', $currentHash);
                
                return true;
            }
            
            return false;
            
        } finally {
            // 确保释放锁
            $this->redisManager->releaseLock($lockKey, $lockIdentifier);
        }
    }
    
    /**
     * 获取群组的Telegram群组ID（用于构建消息链接）
     */
    public function setGroupTelegramId($groupId, $telegramGroupId) {
        // 从完整的群组ID中提取数字部分
        $numericId = str_replace('-100', '', $telegramGroupId);
        $this->groupManager->setSetting($groupId, 'telegram_numeric_id', $numericId);
    }
    
    /**
     * 获取消息链接
     */
    public function getMessageLink($groupId, $messageId) {
        if (!$messageId) return '';
        
        // 先尝试从设置中获取数字ID
        $numericId = $this->groupManager->getSetting($groupId, 'telegram_numeric_id');
        
        // 如果没有设置，尝试从群组的telegram_group_id自动生成
        if (!$numericId) {
            $group = $this->db->fetch("SELECT telegram_group_id FROM groups WHERE id = :id", ['id' => $groupId]);
            if ($group && $group['telegram_group_id']) {
                $telegramGroupId = $group['telegram_group_id'];
                
                // 处理群组ID格式
                if (strpos($telegramGroupId, '-100') === 0) {
                    // 超级群组格式：-1001234567890 -> 1234567890
                    $numericId = substr($telegramGroupId, 4);
                } elseif (strpos($telegramGroupId, '-') === 0) {
                    // 普通群组格式：-123456789 -> 123456789
                    $numericId = substr($telegramGroupId, 1);
                } else {
                    // 已经是正数格式
                    $numericId = $telegramGroupId;
                }
                
                // 保存设置以便下次使用
                $this->groupManager->setSetting($groupId, 'telegram_numeric_id', $numericId);
            }
        }
        
        if ($numericId) {
            return "https://t.me/c/{$numericId}/{$messageId}";
        }
        
        return '';
    }
    
    /**
     * 格式化金额显示 - 整数不显示小数点，有小数才显示
     */
    private function formatAmount($amount) {
        $amount = floatval($amount);
        
        // 如果是整数，不显示小数点
        if ($amount == intval($amount)) {
            return number_format($amount, 0, '.', ''); // 去掉千位分隔符
        }
        
        // 有小数时，显示小数点，但去除尾随零
        return rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.'); // 去掉千位分隔符
    }
    
    /**
     * 格式化费率显示 - 转换为上标格式，70 -> ⁷⁰
     */
    public function formatFeeRate($feeRate) {
        $feeRate = floatval($feeRate);
        
        // 如果是整数，不显示小数点
        if ($feeRate == intval($feeRate)) {
            $rate = intval($feeRate);
        } else {
            // 有小数时，显示小数点，但去除尾随零
            $rate = rtrim(rtrim(number_format($feeRate, 2), '0'), '.');
        }
        
        // 转换为上标格式
        $superscript = '';
        $rateStr = strval($rate);
        for ($i = 0; $i < strlen($rateStr); $i++) {
            $char = $rateStr[$i];
            switch ($char) {
                case '0': $superscript .= '⁰'; break;
                case '1': $superscript .= '¹'; break;
                case '2': $superscript .= '²'; break;
                case '3': $superscript .= '³'; break;
                case '4': $superscript .= '⁴'; break;
                case '5': $superscript .= '⁵'; break;
                case '6': $superscript .= '⁶'; break;
                case '7': $superscript .= '⁷'; break;
                case '8': $superscript .= '⁸'; break;
                case '9': $superscript .= '⁹'; break;
                case '.': $superscript .= '.'; break;
                default: $superscript .= $char; break;
            }
        }
        
        return $superscript;
    }
    
    /**
     * 获取群组访问token
     */
    public function getGroupToken($groupId) {
        // 生成一个基于群组ID的简单token
        return md5($groupId . 'telegram_bot_token_2024');
    }
    
    /**
     * 获取基础URL
     */
    public function getBaseUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $path = dirname($_SERVER['REQUEST_URI'] ?? '');
        return $protocol . '://' . $host . $path;
    }
}
