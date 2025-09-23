<?php
/**
 * 账单详情页面
 */

require_once 'config/config.php';
require_once 'classes/Database.php';
require_once 'classes/GroupManager.php';
require_once 'classes/BillFormatter.php';

// 简单的身份验证（基于群组ID）
$groupToken = $_GET['token'] ?? '';
$groupId = $_GET['group_id'] ?? '';

if (empty($groupToken) || empty($groupId)) {
    die('❌ 缺少必要参数：token 和 group_id');
}

// 验证token
$expectedToken = md5($groupId . 'telegram_bot_token_2024');
if ($groupToken !== $expectedToken) {
    die('❌ 无效的访问token');
}

try {
    $db = Database::getInstance();
    $groupManager = new GroupManager();
    $billFormatter = new BillFormatter();
    
    // 验证群组
    $group = $db->fetch("SELECT * FROM groups WHERE id = ?", [$groupId]);
    if (!$group) {
        die('❌ 群组不存在');
    }
    
    // 获取日期参数
    $date = $_GET['date'] ?? date('Y-m-d');
    
    // 获取指定日期的交易记录
    $transactions = getTransactionsByDate($groupId, $date);
    $stats = getDailyStats($groupId, $date);
    
} catch (Exception $e) {
    die('❌ 数据库错误：' . $e->getMessage());
}

function getTransactionsByDate($groupId, $date) {
    global $db;
    
    return $db->fetchAll("
        SELECT 
            t.*,
            u.first_name,
            u.last_name,
            u.username
        FROM transactions t
        LEFT JOIN users u ON t.operator_id = u.id
        WHERE t.group_id = ? 
        AND DATE(t.created_at) = ? 
        AND t.is_deleted = 0
        ORDER BY t.created_at ASC
    ", [$groupId, $date]);
}

function getDailyStats($groupId, $date) {
    global $db;
    
    $stats = $db->fetch("
        SELECT 
            COUNT(*) as transaction_count,
            SUM(CASE WHEN transaction_type = 'income' THEN original_amount ELSE 0 END) as total_income,
            SUM(CASE WHEN transaction_type = 'distribution' THEN amount ELSE 0 END) as total_distribution,
            AVG(CASE WHEN transaction_type = 'income' THEN fee_rate ELSE NULL END) as avg_fee_rate
        FROM transactions 
        WHERE group_id = ? AND DATE(created_at) = ? AND is_deleted = 0
    ", [$groupId, $date]);
    
    if (!$stats) {
        $stats = [
            'transaction_count' => 0,
            'total_income' => 0,
            'total_distribution' => 0,
            'avg_fee_rate' => 0
        ];
    }
    
    // 重新计算手续费总额（根据原始金额和费率计算）
    $feeCalculation = $db->fetch("
        SELECT 
            SUM(original_amount * fee_rate / 100) as calculated_fee
        FROM transactions 
        WHERE group_id = ? AND DATE(created_at) = ? AND is_deleted = 0 AND transaction_type = 'income'
    ", [$groupId, $date]);
    
    $stats['total_fee'] = $feeCalculation['calculated_fee'] ?: 0;
    
    // 计算应下发和待下发
    $stats['should_distribute'] = $stats['total_fee']; // 应下发 = 手续费总额
    $stats['pending_distribute'] = $stats['should_distribute'] - $stats['total_distribution']; // 待下发 = 应下发 - 已下发
    
    return $stats;
}

function formatAmount($amount) {
    return number_format($amount, 2, '.', ''); // 去掉千位分隔符
}

function getTransactionTypeText($type) {
    switch ($type) {
        case 'income': return '入账';
        case 'expense': return '出账';
        case 'distribution': return '下发';
        default: return $type;
    }
}

function getTransactionTypeClass($type) {
    switch ($type) {
        case 'income': return 'success';
        case 'expense': return 'danger';
        case 'distribution': return 'info';
        default: return 'secondary';
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>账单详情 - <?= $date ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .bill-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .transaction-item {
            border-left: 4px solid #007bff;
            padding: 15px;
            margin-bottom: 10px;
            background: #f8f9fa;
            border-radius: 0 10px 10px 0;
            transition: all 0.3s ease;
        }
        .transaction-item:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .transaction-item.income {
            border-left-color: #28a745;
        }
        .transaction-item.expense {
            border-left-color: #dc3545;
        }
        .transaction-item.distribution {
            border-left-color: #17a2b8;
        }
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .message-link {
            text-decoration: none;
            color: #007bff;
        }
        .message-link:hover {
            color: #0056b3;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid py-4">
        <!-- 页面头部 -->
        <div class="bill-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2><i class="bi bi-receipt"></i> 账单详情</h2>
                    <p class="mb-0">群组：<?= htmlspecialchars($group['group_name']) ?> | 日期：<?= $date ?></p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="admin.php?token=<?= urlencode($groupToken) ?>&group_id=<?= $groupId ?>" 
                       class="btn btn-light">
                        <i class="bi bi-arrow-left"></i> 返回管理
                    </a>
                </div>
            </div>
        </div>
        
        <!-- 总计信息 -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-calculator"></i> 总计</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-3">
                                <h4 class="text-success">¥<?= formatAmount($stats['total_income']) ?></h4>
                                <p class="text-muted mb-0">总入款</p>
                            </div>
                            <div class="col-md-3">
                                <h4 class="text-warning">¥<?= formatAmount($stats['should_distribute']) ?></h4>
                                <p class="text-muted mb-0">应下发</p>
                            </div>
                            <div class="col-md-3">
                                <h4 class="text-info">¥<?= formatAmount($stats['total_distribution']) ?></h4>
                                <p class="text-muted mb-0">已下发</p>
                            </div>
                            <div class="col-md-3">
                                <h4 class="text-danger">¥<?= formatAmount($stats['pending_distribute']) ?></h4>
                                <p class="text-muted mb-0">待下发</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- 交易记录 -->
        <div class="card">
            <div class="card-header">
                <h5><i class="bi bi-list-ul"></i> 交易记录</h5>
            </div>
            <div class="card-body">
                <?php if (empty($transactions)): ?>
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-inbox fs-1"></i>
                        <p>该日期暂无交易记录</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>时间</th>
                                    <th>金额（总金额）</th>
                                    <th>结算（应下发）</th>
                                    <th>操作员</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transactions as $transaction): ?>
                                    <tr class="<?= $transaction['transaction_type'] === 'income' ? 'table-success' : ($transaction['transaction_type'] === 'distribution' ? 'table-info' : 'table-warning') ?>">
                                        <td>
                                            <strong><?= date('H:i', strtotime($transaction['created_at'])) ?></strong>
                                        </td>
                                        <td>
                                            <?php if ($transaction['transaction_type'] === 'income'): ?>
                                                <strong class="text-success">¥<?= formatAmount($transaction['original_amount'] ?: $transaction['amount']) ?></strong>
                                                <?php if ($transaction['message_id']): ?>
                                                    <br><a href="https://t.me/c/<?= str_replace('-100', '', $group['telegram_group_id']) ?>/<?= $transaction['message_id'] ?>" 
                                                           target="_blank" class="btn btn-sm btn-outline-primary message-link">
                                                        <i class="bi bi-telegram"></i> 查看消息
                                                    </a>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <strong class="text-info">¥<?= formatAmount($transaction['original_amount'] ?: $transaction['amount']) ?></strong>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($transaction['transaction_type'] === 'income'): ?>
                                                <?php 
                                                // 重新计算结算金额：原始金额 * 费率 / 100
                                                $calculatedFee = $transaction['original_amount'] * $transaction['fee_rate'] / 100;
                                                ?>
                                                <strong class="text-warning">¥<?= formatAmount($calculatedFee) ?></strong>
                                                <br><small class="text-muted">费率: <?= $transaction['fee_rate'] ?>%</small>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small>
                                                <?= $transaction['first_name'] ?> <?= $transaction['last_name'] ?>
                                                <?php if ($transaction['username']): ?>
                                                    <br>@<?= $transaction['username'] ?>
                                                <?php endif; ?>
                                            </small>
                                        </td>
                                        <td>
                                            <small class="text-muted">ID: <?= $transaction['id'] ?></small>
                                            <?php if ($transaction['note']): ?>
                                                <br><small class="text-muted">
                                                    <i class="bi bi-chat-text"></i> <?= htmlspecialchars($transaction['note']) ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- 日期选择器 -->
        <div class="card mt-4">
            <div class="card-body">
                <h6>查看其他日期</h6>
                <form method="GET" class="d-inline">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($groupToken) ?>">
                    <input type="hidden" name="group_id" value="<?= htmlspecialchars($groupId) ?>">
                    <div class="row">
                        <div class="col-md-4">
                            <input type="date" name="date" value="<?= $date ?>" class="form-control">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary">查看</button>
                        </div>
                        <div class="col-md-6">
                            <a href="?token=<?= urlencode($groupToken) ?>&group_id=<?= $groupId ?>&date=<?= date('Y-m-d') ?>" 
                               class="btn btn-outline-secondary">今天</a>
                            <a href="?token=<?= urlencode($groupToken) ?>&group_id=<?= $groupId ?>&date=<?= date('Y-m-d', strtotime('-1 day')) ?>" 
                               class="btn btn-outline-secondary">昨天</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
