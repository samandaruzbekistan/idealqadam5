<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    private string $botToken;
    private string $apiUrl;

    public function __construct()
    {
        $this->botToken = config('telegram.bot_token');
        $this->apiUrl = "https://api.telegram.org/bot{$this->botToken}";
    }

    /**
     * Send a message to a chat
     */
    public function sendMessage(int $chatId, string $text, array $replyMarkup = null): array
    {
        $data = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ];

        if ($replyMarkup) {
            $data['reply_markup'] = json_encode($replyMarkup);
        }

        try {
            $response = Http::post("{$this->apiUrl}/sendMessage", $data);
            return $response->json();
        } catch (\Exception $e) {
            Log::error('Telegram sendMessage error: ' . $e->getMessage());
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Check if user is a member of a channel
     */
    public function checkChatMember(int $chatId, string $channelUsername): bool
    {
        try {
            $response = Http::get("{$this->apiUrl}/getChatMember", [
                'chat_id' => $channelUsername,
                'user_id' => $chatId,
            ]);

            $result = $response->json();

            if ($result['ok'] ?? false) {
                $status = $result['result']['status'] ?? '';
                // User is a member if status is 'member', 'administrator', or 'creator'
                return in_array($status, ['member', 'administrator', 'creator']);
            }

            return false;
        } catch (\Exception $e) {
            Log::error('Telegram checkChatMember error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Create inline keyboard markup
     */
    public function createInlineKeyboard(array $buttons): array
    {
        return [
            'inline_keyboard' => $buttons,
        ];
    }

    /**
     * Create reply keyboard markup
     */
    public function createReplyKeyboard(array $buttons, bool $resizeKeyboard = true, bool $oneTimeKeyboard = false): array
    {
        return [
            'keyboard' => $buttons,
            'resize_keyboard' => $resizeKeyboard,
            'one_time_keyboard' => $oneTimeKeyboard,
        ];
    }

    /**
     * Remove reply keyboard
     */
    public function removeReplyKeyboard(int $chatId, string $text = ''): array
    {
        $data = [
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => json_encode(['remove_keyboard' => true]),
        ];

        try {
            $response = Http::post("{$this->apiUrl}/sendMessage", $data);
            return $response->json();
        } catch (\Exception $e) {
            Log::error('Telegram removeReplyKeyboard error: ' . $e->getMessage());
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Copy any message (text/media) from one chat to another
     */
    public function copyMessage(int $fromChatId, int $messageId, int $targetChatId): array
    {
        try {
            $response = Http::post("{$this->apiUrl}/copyMessage", [
                'chat_id' => $targetChatId,
                'from_chat_id' => $fromChatId,
                'message_id' => $messageId,
            ]);

            return $response->json();
        } catch (\Exception $e) {
            Log::error('Telegram copyMessage error: ' . $e->getMessage());
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}

