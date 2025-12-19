<?php

namespace App\Http\Controllers;

use App\Models\StudyCenterRegistration;
use App\Services\StudyCenterService;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StudyCenterController extends Controller
{
    private TelegramService $telegramService;
    private StudyCenterService $studyCenterService;
    private string $botToken;

    public function __construct(
        TelegramService $telegramService,
        StudyCenterService $studyCenterService
    ) {
        $this->telegramService = $telegramService;
        $this->studyCenterService = $studyCenterService;
        $this->botToken = config('telegram.study_center_bot_token');
    }

    /**
     * Handle webhook from Telegram Study Center Bot
     */
    public function webhook(Request $request)
    {
        try {
            $update = $request->all();
            Log::info('Study Center webhook received', $update);

            // Handle callback queries
            if (isset($update['callback_query'])) {
                $callbackQuery = $update['callback_query'];
                $chatId = $callbackQuery['message']['chat']['id'];
                $data = $callbackQuery['data'] ?? '';

                // Answer callback query
                Http::post("https://api.telegram.org/bot{$this->botToken}/answerCallbackQuery", [
                    'callback_query_id' => $callbackQuery['id'],
                ]);

                $registration = $this->studyCenterService->getOrCreateRegistration($chatId);

                // Subscription check
                if ($data === 'check_subscription') {
                    $this->handleSubscription($registration, 'Tekshirish', $update);
                    return response()->json(['ok' => true]);
                }

                return response()->json(['ok' => true]);
            }

            // Handle regular messages
            if (!isset($update['message'])) {
                return response()->json(['ok' => true]);
            }

            $message = $update['message'];
            $chatId = $message['chat']['id'];
            $text = $message['text'] ?? '';

            // Handle /restart command
            if ($text === '/restart') {
                $registration = $this->studyCenterService->getOrCreateRegistration($chatId);
                $this->studyCenterService->resetRegistration($registration);
                $this->handleStart($registration, '/start');
                return response()->json(['ok' => true]);
            }

            // Handle regular user flow
            $this->handleUserMessage($chatId, $text, $message);

            return response()->json(['ok' => true]);
        } catch (\Exception $e) {
            Log::error('Study Center webhook error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Handle user messages based on state
     */
    private function handleUserMessage(int $chatId, string $text, array $message): void
    {
        $registration = $this->studyCenterService->getOrCreateRegistration($chatId);

        switch ($registration->state) {
            case 'start':
                $this->handleStart($registration, $text);
                break;

            case 'subscription':
                $this->handleSubscription($registration, $text, $message);
                break;

            case 'full_name':
                $this->handleFullName($registration, $text);
                break;

            case 'subjects':
                $this->handleSubjects($registration, $text);
                break;

            case 'phone':
                $this->handlePhone($registration, $text, $message);
                break;

            case 'completed':
                $this->handleCompleted($registration, $text);
                break;

            default:
                $this->sendMessage($chatId, 'Xatolik yuz berdi. /start buyrug\'ini bosing.');
        }
    }

    /**
     * Handle /start command
     */
    private function handleStart(StudyCenterRegistration $registration, string $text): void
    {
        if ($text === '/start') {
            // Check if user is already registered
            if ($registration->state === 'completed' && $registration->phone) {
                $message = "Salom! Siz allaqachon ro'yxatdan o'tgansiz.\n\n";
                $message .= "Ism: {$registration->full_name}\n";
                $message .= "Fanlar: {$registration->subjects}\n";
                $message .= "Telefon: {$registration->phone}\n\n";
                $message .= "Qayta ro'yxatdan o'tish uchun /restart buyrug'ini bosing.";

                $this->sendMessage($registration->chat_id, $message);
                return;
            }

            // Start new registration
            $this->studyCenterService->updateState($registration, 'subscription');
            $this->showSubscriptionStep($registration);
        }
    }

    /**
     * Show subscription step
     */
    private function showSubscriptionStep(StudyCenterRegistration $registration): void
    {
        $channelUsername = config('telegram.study_center_channel_username');

        $message = "👋 Salom! Ideal Study o'quv markaziga xush kelibsiz!\n\n";
        $message .= "Ro'yxatdan o'tish uchun quyidagi Telegram kanalga obuna bo'ling:\n\n";
        $message .= "📢 @ideal_study_lc\n\n";
        $message .= "Obuna bo'lgach, 'Tekshirish' tugmasini bosing.";

        $buttons = [];
        if ($channelUsername) {
            $buttons[] = [
                [
                    'text' => "Telegram kanalga o'tish",
                    'url' => "https://t.me/ideal_study_lc",
                ],
            ];
        }

        $buttons[] = [
            [
                'text' => '✅ Tekshirish',
                'callback_data' => 'check_subscription',
            ],
        ];

        $keyboard = $this->telegramService->createInlineKeyboard($buttons);
        $this->sendMessage($registration->chat_id, $message, $keyboard);
    }

    /**
     * Handle subscription check
     */
    private function handleSubscription(StudyCenterRegistration $registration, string $text, array $message): void
    {
        if ($text === '✅ Tekshirish' || isset($message['callback_query'])) {
            $channelUsername = config('telegram.study_center_channel_username');

            if (!$channelUsername) {
                $this->sendMessage(
                    $registration->chat_id,
                    'Xatolik: Kanal sozlamasi topilmadi.'
                );
                return;
            }

            $isSubscribed = $this->checkChatMember($registration->chat_id, $channelUsername);

            if ($isSubscribed) {
                $this->studyCenterService->markAsSubscribed($registration);
                $this->sendMessage(
                    $registration->chat_id,
                    "✅ Obuna tasdiqlandi!\n\nIsm va familiyangizni kiriting:"
                );
            } else {
                $inlineKeyboard = $this->telegramService->createInlineKeyboard([
                    [
                        [
                            'text' => '✅ Tekshirish',
                            'callback_data' => 'check_subscription',
                        ],
                    ],
                ]);
                $this->sendMessage(
                    $registration->chat_id,
                    "❌ Siz hali Telegram kanalga obuna bo'lmadingiz.\n\nIltimos, @ideal_study_lc kanaliga obuna bo'ling va yana 'Tekshirish' tugmasini bosing.",
                    $inlineKeyboard
                );
            }
        }
    }

    /**
     * Handle full name input
     */
    private function handleFullName(StudyCenterRegistration $registration, string $text): void
    {
        if (empty(trim($text))) {
            $this->sendMessage(
                $registration->chat_id,
                'Iltimos, ism va familiyangizni kiriting.'
            );
            return;
        }

        $this->studyCenterService->updateFullName($registration, trim($text));
        $this->sendMessage(
            $registration->chat_id,
            "Qaysi fanlarni o'qimoqchisiz yoki o'qiyapsiz?\n\n(Masalan: Matematika, Fizika, Ingliz tili)"
        );
    }

    /**
     * Handle subjects input
     */
    private function handleSubjects(StudyCenterRegistration $registration, string $text): void
    {
        if (empty(trim($text))) {
            $this->sendMessage(
                $registration->chat_id,
                'Iltimos, fanlarni kiriting.'
            );
            return;
        }

        $this->studyCenterService->updateSubjects($registration, trim($text));
        $this->sendMessage(
            $registration->chat_id,
            "Telefon raqamingizni kiriting:\n\n(Masalan: +998901234567 yoki 901234567)"
        );
    }

    /**
     * Handle phone input
     */
    private function handlePhone(StudyCenterRegistration $registration, string $text, array $message): void
    {
        // Check if phone is sent via contact button
        if (isset($message['contact'])) {
            $phone = $message['contact']['phone_number'] ?? '';
            if ($phone) {
                $this->completeRegistration($registration, $phone);
                return;
            }
        }

        // Validate phone number
        $phone = trim($text);
        if (empty($phone)) {
            $this->sendMessage(
                $registration->chat_id,
                'Iltimos, telefon raqamingizni kiriting.'
            );
            return;
        }

        // Basic phone validation
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        if (strlen($phone) < 9) {
            $this->sendMessage(
                $registration->chat_id,
                'Iltimos, to\'g\'ri telefon raqam kiriting.'
            );
            return;
        }

        $this->completeRegistration($registration, $phone);
    }

    /**
     * Complete registration
     */
    private function completeRegistration(StudyCenterRegistration $registration, string $phone): void
    {
        $this->studyCenterService->updatePhone($registration, $phone);

        $message = "✅ Ro'yxatdan muvaffaqiyatli o'tdingiz!\n\n";
        $message .= "Ism: {$registration->full_name}\n";
        $message .= "Fanlar: {$registration->subjects}\n";
        $message .= "Telefon: {$registration->phone}\n\n";
        $message .= "📞 Sizga adminlarimiz tez orada bog'lanishadi.\n";
        $message .= "Ideal Study o'quv markazi";

        $this->sendMessage($registration->chat_id, $message);
    }

    /**
     * Handle completed state
     */
    private function handleCompleted(StudyCenterRegistration $registration, string $text): void
    {
        if ($text === '/restart') {
            $this->studyCenterService->resetRegistration($registration);
            $this->handleStart($registration, '/start');
        } elseif ($text === '/start') {
            $message = "Salom! Siz allaqachon ro'yxatdan o'tgansiz.\n\n";
            $message .= "Ism: {$registration->full_name}\n";
            $message .= "Fanlar: {$registration->subjects}\n";
            $message .= "Telefon: {$registration->phone}\n\n";
            $message .= "Qayta ro'yxatdan o'tish uchun /restart buyrug'ini bosing.";
            $this->sendMessage($registration->chat_id, $message);
        } else {
            $this->sendMessage(
                $registration->chat_id,
                'Xatolik yuz berdi. /start buyrug\'ini bosing.'
            );
        }
    }

    /**
     * Check if user is a member of a channel
     */
    private function checkChatMember(int $chatId, string $channelUsername): bool
    {
        try {
            $response = Http::get("https://api.telegram.org/bot{$this->botToken}/getChatMember", [
                'chat_id' => $channelUsername,
                'user_id' => $chatId,
            ]);

            $result = $response->json();

            if ($result['ok'] ?? false) {
                $status = $result['result']['status'] ?? '';
                return in_array($status, ['member', 'administrator', 'creator']);
            }

            return false;
        } catch (\Exception $e) {
            Log::error('Study Center checkChatMember error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send message using Study Center bot token
     */
    private function sendMessage(int $chatId, string $text, array $replyMarkup = null): array
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
            $response = Http::post("https://api.telegram.org/bot{$this->botToken}/sendMessage", $data);
            return $response->json();
        } catch (\Exception $e) {
            Log::error('Study Center sendMessage error: ' . $e->getMessage());
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}

