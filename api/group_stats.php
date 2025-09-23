<?php
/**
 * 群组统计API
 */

require_once '../config/config.php';
require_once '../classes/Database.php';

header('Content-Type: application/json');

// 简单的API密钥验证
$apiKey = $_GET['api_key'] ?? '';
if ($apiKey !== 'admin_api_key_2024') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    $db = Database::getInstance();
    
    $groupId = $_GET['group_id'] ?? '';
    $date = $_GET['date'] ?? date('Y-m-d');
    
    if (!$groupId) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing group_id']);
        exit;
    }
    
    // 获取群组基本信息
    $group = $db->fetch("SELECT * FROM groups WHERE id = ?", [$groupId]);
    if (!$group) {
        http_response_code(404);
        echo json_encode(['error' => 'Group not found']);
        exit;
    }
    
    // 获取指定日期的统计
    $dailyStats = $db->fetch("
        SELECT 
            COUNT(*) as transaction_count,
            SUM(CASE WHEN transaction_type = 'income' THEN original_amount ELSE 0 END) as total_income,
            SUM(CASE WHEN transaction_type = 'distribution' THEN amount ELSE 0 END) as total_distribution,
            SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END) as total_fee
        FROM transactions 
        WHERE group_id = ? AND DATE(created_at) = ? AND is_deleted = 0
    ", [$groupId, $date]);
    
    // 获取本月统计
    $monthStats = $db->fetch("
        SELECT 
            COUNT(*) as transaction_count,
            SUM(CASE WHEN transaction_type = 'income' THEN original_amount ELSE 0 END) as total_income,
            SUM(CASE WHEN transaction_type = 'distribution' THEN amount ELSE 0 END) as total_distribution,
            SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END) as total_fee
        FROM transactions 
        WHERE group_id = ? AND YEAR(created_at) = YEAR(?) AND MONTH(created_at) = MONTH(?) AND is_deleted = 0
    ", [$groupId, $date, $date]);
    
    // 获取最近交易记录
    $recentTransactions = $db->fetchAll("
        SELECT 
            t.*,
            u.first_name,
            u.last_name,
            u.username
        FROM transactions t
        LEFT JOIN users u ON t.operator_id = u.id
        WHERE t.group_id = ? AND t.is_deleted = 0
        ORDER BY t.created_at DESC
        LIMIT 10
    ", [$groupId]);
    
    $result = [
        'group' => [
            'id' => $group['id'],
            'group_name' => $group['group_name'],
            'telegram_group_id' => $group['telegram_group_id'],
            'created_at' => $group['created_at']
        ],
        'daily_stats' => $dailyStats ?: [
            'transaction_count' => 0,
            'total_income' => 0,
            'total_distribution' => 0,
            'total_fee' => 0
        ],
        'month_stats' => $monthStats ?: [
            'transaction_count' => 0,
            'total_income' => 0,
            'total_distribution' => 0,
            'total_fee' => 0
        ],
        'recent_transactions' => $recentTransactions ?: []
    ];
    
    echo json_encode($result);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
