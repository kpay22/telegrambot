<?php
/**
 * Telegram机器人核心类
 */
class TelegramBot {
    private $token;
    
    public function __construct() {
        $this->token = BOT_TOKEN;
    }
    
    /**
     * 发送消息到Telegram
     */
    public function sendMessage($chatId, $text, $replyMarkup = null, $parseMode = 'HTML') {
        $data = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => $parseMode
        ];
        
        if ($replyMarkup) {
            $data['reply_markup'] = json_encode($replyMarkup);
        }
        
        return $this->makeRequest('sendMessage', $data);
    }
    
    /**
     * 回复消息
     */
    public function replyToMessage($chatId, $messageId, $text, $parseMode = 'HTML') {
        $data = [
            'chat_id' => $chatId,
            'reply_to_message_id' => $messageId,
            'text' => $text,
            'parse_mode' => $parseMode
        ];
        
        return $this->makeRequest('sendMessage', $data);
    }
    
    /**
     * 编辑消息
     */
    public function editMessage($chatId, $messageId, $text, $replyMarkup = null) {
        $data = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'HTML'
        ];
        
        if ($replyMarkup) {
            $data['reply_markup'] = json_encode($replyMarkup);
        }
        
        return $this->makeRequest('editMessageText', $data);
    }
    
    /**
     * 删除消息
     */
    public function deleteMessage($chatId, $messageId) {
        $data = [
            'chat_id' => $chatId,
            'message_id' => $messageId
        ];
        
        return $this->makeRequest('deleteMessage', $data);
    }
    
    /**
     * 获取聊天信息
     */
    public function getChat($chatId) {
        $data = ['chat_id' => $chatId];
        return $this->makeRequest('getChat', $data);
    }
    
    /**
     * 获取聊天成员
     */
    public function getChatMember($chatId, $userId) {
        $data = [
            'chat_id' => $chatId,
            'user_id' => $userId
        ];
        return $this->makeRequest('getChatMember', $data);
    }
    
    /**
     * 禁言用户
     */
    public function restrictChatMember($chatId, $userId, $until = null) {
        $data = [
            'chat_id' => $chatId,
            'user_id' => $userId,
            'permissions' => [
                'can_send_messages' => false
            ]
        ];
        
        if ($until) {
            $data['until_date'] = $until;
        }
        
        return $this->makeRequest('restrictChatMember', $data);
    }
    
    /**
     * 解除禁言
     */
    public function unrestrictChatMember($chatId, $userId) {
        $data = [
            'chat_id' => $chatId,
            'user_id' => $userId,
            'permissions' => [
                'can_send_messages' => true,
                'can_send_media_messages' => true,
                'can_send_polls' => true,
                'can_send_other_messages' => true,
                'can_add_web_page_previews' => true,
                'can_change_info' => false,
                'can_invite_users' => false,
                'can_pin_messages' => false
            ]
        ];
        
        return $this->makeRequest('restrictChatMember', $data);
    }
    
    /**
     * 设置Webhook
     */
    public function setWebhook($url) {
        $data = ['url' => $url];
        return $this->makeRequest('setWebhook', $data);
    }
    
    /**
     * 删除Webhook
     */
    public function deleteWebhook() {
        return $this->makeRequest('deleteWebhook');
    }
    
    /**
     * 发送HTTP请求到Telegram API
     */
    private function makeRequest($method, $data = []) {
        $url = TELEGRAM_API_URL . $method;
        
        $options = [
            'http' => [
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($data)
            ]
        ];
        
        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        
        if ($result === false) {
            error_log("Telegram API请求失败: " . $method);
            return false;
        }
        
        $response = json_decode($result, true);
        
        if (!$response['ok']) {
            error_log("Telegram API错误: " . $response['description']);
            return false;
        }
        
        return $response['result'];
    }
    
    /**
     * 创建内联键盘
     */
    public function createInlineKeyboard($buttons) {
        return [
            'inline_keyboard' => $buttons
        ];
    }
    
    /**
     * 创建回复键盘
     */
    public function createReplyKeyboard($buttons, $oneTime = false, $resize = true) {
        return [
            'keyboard' => $buttons,
            'one_time_keyboard' => $oneTime,
            'resize_keyboard' => $resize
        ];
    }
    
    /**
     * 移除键盘
     */
    public function removeKeyboard() {
        return ['remove_keyboard' => true];
    }
    
    /**
     * 获取机器人ID
     */
    public function getBotId() {
        // 从配置中获取机器人ID，或者从token中解析
        $token = $this->token;
        $parts = explode(':', $token);
        return isset($parts[0]) ? intval($parts[0]) : null;
    }
}
