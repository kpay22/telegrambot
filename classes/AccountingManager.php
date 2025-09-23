<?php
/**
 * 记账管理类 - 处理所有记账相关操作
 */

require_once 'classes/GroupManager.php';

class AccountingManager {
    private $db;
    private $groupManager;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->groupManager = new GroupManager();
    }
    
    /**
     * 解析记账命令
     */
    public function parseAccountingCommand($text, $groupId) {
        $text = trim($text);
        $result = [
            'success' => false,
            'error' => '',
            'data' => []
        ];
        
        // 获取群组默认配置
        $defaultFeeRate = $this->groupManager->getSetting($groupId, 'fee_rate', DEFAULT_FEE_RATE);
        $defaultExchangeRate = $this->groupManager->getSetting($groupId, 'exchange_rate', DEFAULT_EXCHANGE_RATE);
        
        try {
            // 处理撤销命令
            if ($text === '撤销') {
                $result['success'] = true;
                $result['data'] = [
                    'type' => 'undo',
                    'action' => 'undo_last'
                ];
                return $result;
            }
            
            // 处理下发命令
            if (strpos($text, '下发') === 0) {
                return $this->parseDistributionCommand($text, $groupId, $defaultExchangeRate);
            }
            
            // 处理基础记账命令（+入账，-出账）
            if (preg_match('/^([\+\-])(.+)$/', $text, $matches)) {
                $sign = $matches[1];
                $amountPart = trim($matches[2]);
                
                $transactionType = ($sign === '+') ? 'income' : 'expense';
                
                return $this->parseAmountExpression($amountPart, $transactionType, $groupId, $defaultFeeRate, $defaultExchangeRate);
            }
            
            // 处理分组记账命令（张三+10000）
            if (preg_match('/^(.+?)([\+\-])(.+)$/', $text, $matches)) {
                $category = trim($matches[1]);
                $sign = $matches[2];
                $amountPart = trim($matches[3]);
                
                $transactionType = ($sign === '+') ? 'income' : 'expense';
                
                $parseResult = $this->parseAmountExpression($amountPart, $transactionType, $groupId, $defaultFeeRate, $defaultExchangeRate);
                
                if ($parseResult['success']) {
                    $parseResult['data']['category'] = $category;
                }
                
                return $parseResult;
            }
            
            $result['error'] = '无法识别的记账命令格式';
            
        } catch (Exception $e) {
            $result['error'] = '命令解析错误：' . $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * 解析金额表达式
     */
    private function parseAmountExpression($amountPart, $transactionType, $groupId, $defaultFeeRate, $defaultExchangeRate) {
        $result = [
            'success' => false,
            'error' => '',
            'data' => [
                'type' => $transactionType,
                'amount' => 0,
                'original_amount' => 0,
                'fee_rate' => $defaultFeeRate,
                'exchange_rate' => $defaultExchangeRate,
                'currency' => 'CNY',
                'category' => null,
                'note' => null,
                'is_usdt' => false,
                'custom_fee' => false,
                'custom_rate_name' => null
            ]
        ];
        
        try {
            // 提取备注（空格后的内容）
            $parts = explode(' ', $amountPart, 2);
            $mainPart = $parts[0];
            $note = isset($parts[1]) ? trim($parts[1]) : null;
            
            // 检查是否是快捷费扣格式（-1000*105%）
            if (preg_match('/^([\d\.,]+)\*(\d+(?:\.\d+)?)%$/', $mainPart, $matches)) {
                $amount = $this->parseNumber($matches[1]);
                $feePercent = floatval($matches[2]);
                
                $result['data']['original_amount'] = $amount;
                $result['data']['amount'] = $amount * ($feePercent / 100);
                $result['data']['fee_rate'] = $feePercent - 100; // 转换为费率
                $result['data']['custom_fee'] = true;
                $result['data']['note'] = $note;
                $result['success'] = true;
                
                return $result;
            }
            
            // 检查是否是USDT格式（7777u）
            if (preg_match('/^([\d\.,]+)u$/i', $mainPart, $matches)) {
                $usdtAmount = $this->parseNumber($matches[1]);
                
                // 获取实时汇率
                $currentRate = $this->getCurrentExchangeRate($groupId);
                if ($currentRate) {
                    $defaultExchangeRate = $currentRate;
                }
                
                $result['data']['original_amount'] = $usdtAmount;
                $result['data']['amount'] = $usdtAmount * $defaultExchangeRate;
                $result['data']['exchange_rate'] = $defaultExchangeRate;
                $result['data']['currency'] = 'USDT';
                $result['data']['is_usdt'] = true;
                $result['data']['note'] = $note;
                $result['success'] = true;
                
                return $result;
            }
            
            // 检查是否指定了汇率（10000/7.8）
            if (strpos($mainPart, '/') !== false) {
                $rateParts = explode('/', $mainPart);
                if (count($rateParts) === 2) {
                    $amount = $this->parseNumber($rateParts[0]);
                    $rateOrName = trim($rateParts[1]);
                    
                    // 检查是否是自定义汇率名称（如：欧元、港币）
                    $customRate = $this->getCustomRate($groupId, $rateOrName);
                    if ($customRate) {
                        $result['data']['amount'] = $amount;
                        $result['data']['original_amount'] = $amount;
                        $result['data']['fee_rate'] = $customRate['fee_rate'] ?? $defaultFeeRate;
                        $result['data']['exchange_rate'] = $customRate['exchange_rate'] ?? $defaultExchangeRate;
                        $result['data']['custom_rate_name'] = $rateOrName;
                        $result['data']['note'] = $note;
                        $result['success'] = true;
                        
                        return $result;
                    }
                    
                    // 否则作为数字汇率处理
                    if (is_numeric($rateOrName)) {
                        $exchangeRate = floatval($rateOrName);
                        
                        $result['data']['amount'] = $amount;
                        $result['data']['original_amount'] = $amount;
                        $result['data']['exchange_rate'] = $exchangeRate;
                        $result['data']['note'] = $note;
                        $result['success'] = true;
                        
                        return $result;
                    }
                }
            }
            
            // 基础金额格式
            if (is_numeric(str_replace([',', '.'], ['', '.'], $mainPart))) {
                $amount = $this->parseNumber($mainPart);
                
                $result['data']['amount'] = $amount;
                $result['data']['original_amount'] = $amount;
                $result['data']['note'] = $note;
                $result['success'] = true;
                
                return $result;
            }
            
            $result['error'] = '无法解析金额格式';
            
        } catch (Exception $e) {
            $result['error'] = '金额解析错误：' . $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * 解析下发命令
     */
    private function parseDistributionCommand($text, $groupId, $defaultExchangeRate) {
        $result = [
            'success' => false,
            'error' => '',
            'data' => [
                'type' => 'distribution',
                'amount' => 0,
                'exchange_rate' => $defaultExchangeRate,
                'currency' => 'CNY',
                'category' => null,
                'note' => null
            ]
        ];
        
        // 匹配下发命令格式
        if (preg_match('/^下发([\+\-]?[\d\.,]+)(.*)$/', $text, $matches)) {
            $amountStr = $matches[1];
            $suffix = trim($matches[2]);
            
            $amount = $this->parseNumber($amountStr);
            
            // 检查是否指定了币种（如：1000R表示人民币）
            if (preg_match('/^\/?([\d\.,]+)$/', $suffix, $rateMatches)) {
                // 指定汇率
                $exchangeRate = $this->parseNumber($rateMatches[1]);
                $result['data']['exchange_rate'] = $exchangeRate;
            } elseif ($suffix === 'R' || $suffix === 'r') {
                // 人民币标记
                $result['data']['currency'] = 'CNY';
            }
            
            $result['data']['amount'] = $amount;
            $result['success'] = true;
        } else {
            $result['error'] = '下发命令格式错误';
        }
        
        return $result;
    }
    
    /**
     * 执行交易
     */
    public function executeTransaction($data, $user, $group, $messageId = null) {
        $this->db->beginTransaction();
        
        try {
            // 处理撤销操作
            if ($data['type'] === 'undo') {
                $result = $this->executeUndo($user, $group);
                $this->db->commit();
                return $result;
            }
            
            // 插入交易记录 - 存储原始金额，不应用费率
            $transactionData = [
                'group_id' => $group['id'],
                'user_id' => $user['id'], // 这里应该是被记账的用户，暂时用操作员
                'operator_id' => $user['id'],
                'message_id' => $messageId,
                'transaction_type' => $data['type'],
                'amount' => $data['amount'], // 存储原始金额
                'original_amount' => $data['original_amount'] ?? $data['amount'],
                'fee_rate' => $data['fee_rate'],
                'exchange_rate' => $data['exchange_rate'],
                'currency' => $data['currency'],
                'category' => $data['category'],
                'note' => $data['note'],
                'is_pending' => 0
            ];
            
            $transactionId = $this->db->insert('transactions', $transactionData);
            
            $this->db->commit();
            return $transactionId;
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log('交易执行失败: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 计算最终金额（应用费率）
     */
    private function calculateFinalAmount($data) {
        $amount = $data['amount'];
        $feeRate = $data['fee_rate'];
        
        if ($data['type'] === 'income' && $feeRate > 0) {
            // 入账扣除费率
            return $amount * (1 - $feeRate / 100);
        } elseif ($data['type'] === 'expense' && $feeRate < 0) {
            // 代付可能有负费率
            return $amount * (1 + abs($feeRate) / 100);
        }
        
        return $amount;
    }
    
    /**
     * 执行撤销操作
     */
    private function executeUndo($user, $group) {
        // 获取最后一笔交易
        $lastTransaction = $this->db->fetch(
            "SELECT * FROM transactions 
             WHERE group_id = :group_id AND operator_id = :operator_id AND is_deleted = 0 
             ORDER BY created_at DESC LIMIT 1",
            ['group_id' => $group['id'], 'operator_id' => $user['id']]
        );
        
        if (!$lastTransaction) {
            throw new Exception('没有找到可撤销的交易记录');
        }
        
        // 标记为已删除
        $this->db->update(
            'transactions',
            ['is_deleted' => 1],
            'id = :id',
            ['id' => $lastTransaction['id']]
        );
        
        return $lastTransaction['id'];
    }
    
    /**
     * 格式化交易响应
     */
    public function formatTransactionResponse($data, $transactionId, $user) {
        $response = '';
        
        if ($data['type'] === 'undo') {
            $response = "✅ <b>撤销成功</b>\n";
            $response .= "交易ID: #{$transactionId}";
            return $response;
        }
        
        // 交易类型图标
        $typeIcon = [
            'income' => '💰',
            'expense' => '💸',
            'distribution' => '📤'
        ];
        
        $icon = $typeIcon[$data['type']] ?? '📊';
        $typeName = [
            'income' => '入账',
            'expense' => '出账',
            'distribution' => '下发'
        ][$data['type']] ?? '记账';
        
        $response = "{$icon} <b>{$typeName}成功</b>\n\n";
        
        // 基础信息
        $response .= "💵 金额: " . number_format($data['amount'], 2) . " {$data['currency']}\n";
        
        if ($data['exchange_rate'] && $data['exchange_rate'] != 1) {
            $response .= "💱 汇率: " . $data['exchange_rate'] . "\n";
        }
        
        if ($data['fee_rate'] && $data['fee_rate'] != 0) {
            $response .= "📊 费率: " . $data['fee_rate'] . "%\n";
        }
        
        if ($data['category']) {
            $response .= "🏷 分组: " . $data['category'] . "\n";
        }
        
        if ($data['note']) {
            $response .= "📝 备注: " . $data['note'] . "\n";
        }
        
        $response .= "\n🆔 交易ID: #{$transactionId}\n";
        $response .= "👤 操作员: " . $this->getUserDisplayName($user);
        
        return $response;
    }
    
    /**
     * 获取当前实时汇率
     */
    private function getCurrentExchangeRate($groupId) {
        $rateSource = $this->groupManager->getSetting($groupId, 'rate_source');
        
        if ($rateSource === 'huobi') {
            return $this->getHuobiRate();
        } elseif ($rateSource === 'okx') {
            return $this->getOkxRate();
        }
        
        return null;
    }
    
    /**
     * 获取火币汇率
     */
    private function getHuobiRate() {
        try {
            $response = file_get_contents(HUOBI_API_URL);
            $data = json_decode($response, true);
            
            if ($data && isset($data['tick']['close'])) {
                return floatval($data['tick']['close']);
            }
        } catch (Exception $e) {
            error_log('获取火币汇率失败: ' . $e->getMessage());
        }
        
        return null;
    }
    
    /**
     * 获取欧易汇率
     */
    private function getOkxRate() {
        try {
            $response = file_get_contents(OKX_API_URL);
            $data = json_decode($response, true);
            
            if ($data && isset($data['data'][0]['last'])) {
                return floatval($data['data'][0]['last']);
            }
        } catch (Exception $e) {
            error_log('获取欧易汇率失败: ' . $e->getMessage());
        }
        
        return null;
    }
    
    /**
     * 获取自定义汇率配置
     */
    private function getCustomRate($groupId, $rateName) {
        return $this->db->fetch(
            "SELECT * FROM custom_rates WHERE group_id = :group_id AND rate_name = :rate_name",
            ['group_id' => $groupId, 'rate_name' => $rateName]
        );
    }
    
    /**
     * 解析数字（支持逗号分隔符）
     */
    private function parseNumber($str) {
        $str = str_replace(',', '', $str);
        return floatval($str);
    }
    
    /**
     * 获取用户显示名称
     */
    private function getUserDisplayName($user) {
        if ($user['username']) {
            return '@' . $user['username'];
        }
        
        $name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
        return $name ?: 'User#' . $user['telegram_id'];
    }
    
    /**
     * 获取群组账单
     */
    public function getGroupBill($groupId, $startDate = null, $endDate = null, $category = null) {
        $conditions = ['group_id = :group_id', 'is_deleted = 0'];
        $params = ['group_id' => $groupId];
        
        if ($startDate) {
            $conditions[] = 'created_at >= :start_date';
            $params['start_date'] = $startDate;
        }
        
        if ($endDate) {
            $conditions[] = 'created_at <= :end_date';
            $params['end_date'] = $endDate;
        }
        
        if ($category) {
            $conditions[] = 'category = :category';
            $params['category'] = $category;
        }
        
        $sql = "SELECT t.*, u.username, u.first_name, u.last_name 
                FROM transactions t
                LEFT JOIN users u ON t.user_id = u.id
                WHERE " . implode(' AND ', $conditions) . "
                ORDER BY t.created_at DESC";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * 获取账单统计
     */
    public function getBillSummary($groupId, $startDate = null, $endDate = null) {
        $conditions = ['group_id = :group_id', 'is_deleted = 0'];
        $params = ['group_id' => $groupId];
        
        if ($startDate) {
            $conditions[] = 'created_at >= :start_date';
            $params['start_date'] = $startDate;
        }
        
        if ($endDate) {
            $conditions[] = 'created_at <= :end_date';
            $params['end_date'] = $endDate;
        }
        
        $sql = "SELECT 
                    transaction_type,
                    COUNT(*) as count,
                    SUM(amount) as total_amount,
                    AVG(amount) as avg_amount
                FROM transactions
                WHERE " . implode(' AND ', $conditions) . "
                GROUP BY transaction_type";
        
        return $this->db->fetchAll($sql, $params);
    }
}
