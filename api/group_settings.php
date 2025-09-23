<?php
/**
 * 群组设置API
 */

require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/GroupManager.php';

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
    $groupManager = new GroupManager();
    
    $method = $_SERVER['REQUEST_METHOD'];
    $groupId = $_GET['group_id'] ?? '';
    
    if (!$groupId) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing group_id']);
        exit;
    }
    
    switch ($method) {
        case 'GET':
            // 获取群组设置
            $group = $db->fetch("SELECT * FROM groups WHERE id = ?", [$groupId]);
            if (!$group) {
                http_response_code(404);
                echo json_encode(['error' => 'Group not found']);
                exit;
            }
            
            $settings = [
                'group_name' => $group['group_name'],
                'fee_rate' => $groupManager->getSetting($groupId, 'fee_rate', 70),
                'exchange_rate' => $groupManager->getSetting($groupId, 'exchange_rate', 7.2),
                'accounting_enabled' => $groupManager->getSetting($groupId, 'accounting_enabled', 0),
                'telegram_group_id' => $group['telegram_group_id']
            ];
            
            echo json_encode($settings);
            break;
            
        case 'POST':
        case 'PUT':
            // 更新群组设置
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (isset($input['group_name'])) {
                $db->query("UPDATE groups SET group_name = ? WHERE id = ?", 
                    [$input['group_name'], $groupId]);
            }
            
            if (isset($input['fee_rate'])) {
                $groupManager->setSetting($groupId, 'fee_rate', floatval($input['fee_rate']));
            }
            
            if (isset($input['exchange_rate'])) {
                $groupManager->setSetting($groupId, 'exchange_rate', floatval($input['exchange_rate']));
            }
            
            if (isset($input['accounting_enabled'])) {
                $groupManager->setSetting($groupId, 'accounting_enabled', $input['accounting_enabled'] ? 1 : 0);
            }
            
            echo json_encode(['success' => true, 'message' => 'Settings updated']);
            break;
            
        case 'DELETE':
            // 删除群组
            $db->query("DELETE FROM groups WHERE id = ?", [$groupId]);
            echo json_encode(['success' => true, 'message' => 'Group deleted']);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
