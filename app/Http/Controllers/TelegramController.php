<?php

namespace App\Http\Controllers;

use App\Exports\RegistrationsExport;
use App\Models\Registration;
use App\Services\RegistrationService;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TelegramController extends Controller
{
    private TelegramService $telegramService;
    private RegistrationService $registrationService;

    public function __construct(
        TelegramService $telegramService,
        RegistrationService $registrationService
    ) {
        $this->telegramService = $telegramService;
        $this->registrationService = $registrationService;
    }

    /**
     * Handle webhook from Telegram
     */
    public function webhook(Request $request)
    {
        try {
            $update = $request->all();
            Log::info('Telegram webhook received', $update);

            if (isset($update['callback_query'])) {
                $callbackQuery = $update['callback_query'];
                $chatId = $callbackQuery['message']['chat']['id'];
                $data = $callbackQuery['data'] ?? '';

                // Answer callback query to remove loading state
                Http::post("https://api.telegram.org/bot" . config('telegram.bot_token') . "/answerCallbackQuery", [
                    'callback_query_id' => $callbackQuery['id'],
                ]);

                $registration = $this->registrationService->getOrCreateRegistration($chatId);

                // Admin inline actions (if any)
                if ($this->isAdmin($chatId)) {
                    $this->handleAdminCommand($chatId, $data);
                    return response()->json(['ok' => true]);
                }

                // Grade selection via inline button
                if (str_starts_with($data, 'grade:')) {
                    $grade = (int) str_replace('grade:', '', $data);
                    $this->handleGrade($registration, (string) $grade);
                    return response()->json(['ok' => true]);
                }

                // Subject selection via inline button
                if (str_starts_with($data, 'subject:')) {
                    $subject = urldecode(str_replace('subject:', '', $data));
                    $this->handleSubjects($registration, $subject);
                    return response()->json(['ok' => true]);
                }

                // Subscription check via inline button
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

            // Check if admin command
            if ($this->isAdmin($chatId)) {
                $this->handleAdminCommand($chatId, $text);
                return response()->json(['ok' => true]);
            }

            // Handle regular user flow
            $this->handleUserMessage($chatId, $text, $message);

            return response()->json(['ok' => true]);
        } catch (\Exception $e) {
            Log::error('Webhook error: ' . $e->getMessage(), [
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
        $registration = $this->registrationService->getOrCreateRegistration($chatId);

        switch ($registration->state) {
            case 'start':
                $this->handleStart($registration, $text);
                break;

            case 'full_name':
                $this->handleFullName($registration, $text);
                break;

            case 'school':
                $this->handleSchool($registration, $text);
                break;

            case 'grade':
                $this->handleGrade($registration, $text);
                break;

            case 'subjects':
                $this->handleSubjects($registration, $text);
                break;

            case 'subscription':
                $this->handleSubscription($registration, $text, $message);
                break;

            default:
                $this->telegramService->sendMessage(
                    $chatId,
                    'Xatolik yuz berdi. /start buyrug\'ini bosing.'
                );
        }
    }

    /**
     * Handle /start command
     */
    private function handleStart(Registration $registration, string $text): void
    {
        if ($text === '/start') {
            $this->registrationService->updateState($registration, 'full_name');
            $this->telegramService->sendMessage(
                $registration->chat_id,
                'Ism va familiyangizni kiriting'
            );
        }
    }

    /**
     * Handle full name input
     */
    private function handleFullName(Registration $registration, string $text): void
    {
        if (empty(trim($text))) {
            $this->telegramService->sendMessage(
                $registration->chat_id,
                'Iltimos, ism va familiyangizni kiriting.'
            );
            return;
        }

        $this->registrationService->updateFullName($registration, trim($text));

        $this->telegramService->sendMessage(
            $registration->chat_id,
            'Maktab nomini kiriting (masalan: Guliston shahar 10-maktab yoki 23-maktab).'
        );
    }

    /**
     * Handle school input
     */
    private function handleSchool(Registration $registration, string $text): void
    {
        if (empty(trim($text))) {
            $this->telegramService->sendMessage(
                $registration->chat_id,
                'Iltimos, maktab nomini kiriting.'
            );
            return;
        }

        $this->registrationService->updateSchool($registration, trim($text));

        $keyboard = $this->buildGradeInlineKeyboard();
        $this->telegramService->sendMessage(
            $registration->chat_id,
            'Sinfingizni tanlang (1-10)',
            $keyboard
        );
    }

    /**
     * Handle grade selection
     */
    private function handleGrade(Registration $registration, string $text): void
    {
        if (!$this->registrationService->isValidGrade($text)) {
            $this->telegramService->sendMessage(
                $registration->chat_id,
                'Iltimos, 1 dan 10 gacha bo\'lgan sinfni tanlang.'
            );
            return;
        }

        $grade = (int) $text;
        $this->registrationService->updateGrade($registration, $grade);

        if ($this->registrationService->shouldSkipSubjectSelection($grade)) {
            // Skip to subscription for grades 1-4
            $this->showSubscriptionStep($registration);
        } else {
            // Show subject selection
            $this->showSubjectSelection($registration, $grade);
        }
    }

    /**
     * Show subject selection based on grade
     */
    private function showSubjectSelection(Registration $registration, int $grade): void
    {
        $subjects = $this->registrationService->getSubjectsByGrade($grade);

        $buttons = [];
        foreach ($subjects as $subject) {
            $buttons[] = [
                [
                    'text' => $subject,
                    'callback_data' => 'subject:' . urlencode($subject),
                ],
            ];
        }

        $keyboard = $this->telegramService->createInlineKeyboard($buttons);
        $this->telegramService->sendMessage(
            $registration->chat_id,
            'Fanlarni tanlang:',
            $keyboard
        );
    }

    /**
     * Handle subject selection
     */
    private function handleSubjects(Registration $registration, string $text): void
    {
        if (!$registration->grade) {
            $this->telegramService->sendMessage(
                $registration->chat_id,
                'Xatolik yuz berdi. /start buyrug\'ini bosing.'
            );
            return;
        }

        if (!$this->registrationService->isValidSubject($registration->grade, $text)) {
            $availableSubjects = $this->registrationService->getSubjectsByGrade($registration->grade);
            $this->telegramService->sendMessage(
                $registration->chat_id,
                'Iltimos, quyidagi fanlardan birini tanlang: ' . implode(', ', $availableSubjects)
            );
            return;
        }

        $this->registrationService->updateSubjects($registration, $text);
        $this->showSubscriptionStep($registration);
    }

    /**
     * Show subscription step
     */
    private function showSubscriptionStep(Registration $registration): void
    {
        $channelUsername = config('telegram.channel_username');
        $instagramLink = config('telegram.instagram_link', '');
        $youtubeLink = config('telegram.youtube_link', '');

        $message = "Ro'yxatdan o'tishni yakunlash uchun quyidagi kanallarga obuna bo'ling:\n\n";


        $message .= "\nObuna bo'lgach, 'Tekshirish' tugmasini bosing.";

        $buttons = [];
        if ($channelUsername) {
            $buttons[] = [
                [
                    'text' => "Telegram kanalga o'tish",
                    'url' => "https://t.me/{$channelUsername}",
                ],
            ];
        }

        if ($instagramLink) {
            $buttons[] = [
                [
                    'text' => 'Instagram',
                    'url' => $instagramLink,
                ],
            ];
        }

        if ($youtubeLink) {
            $buttons[] = [
                [
                    'text' => 'YouTube',
                    'url' => $youtubeLink,
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

        $this->telegramService->sendMessage(
            $registration->chat_id,
            $message,
            $keyboard
        );
    }

    /**
     * Handle subscription check
     */
    private function handleSubscription(Registration $registration, string $text, array $message): void
    {
        if ($text === '✅ Tekshirish' || isset($message['callback_query'])) {
            $channelUsername = config('telegram.channel_username');

            if (!$channelUsername) {
                $this->telegramService->sendMessage(
                    $registration->chat_id,
                    'Xatolik: Kanal sozlamasi topilmadi.'
                );
                return;
            }

            $isSubscribed = $this->telegramService->checkChatMember(
                $registration->chat_id,
                $channelUsername
            );

            if ($isSubscribed) {
                $this->registrationService->completeRegistration($registration);
                $this->telegramService->removeReplyKeyboard(
                    $registration->chat_id,
                    "✅ Ro'yxatdan muvaffaqiyatli o'tdingiz!\n\n" .
                    "Ism: {$registration->full_name}\n" .
                    "Maktab: {$registration->school}\n" .
                    "Sinf: {$registration->grade}\n" .
                    "Fanlar: {$registration->subjects}\n\n" .
                    "Qo'shimcha ma'lumotlar keyinroq yuboriladi."
                );
            } else {
                $this->telegramService->sendMessage(
                    $registration->chat_id,
                    "❌ Siz hali Telegram kanalga obuna bo'lmadingiz.\n\n" .
                    "Iltimos, @" . $channelUsername . " kanaliga obuna bo'ling va yana 'Tekshirish' tugmasini bosing."
                );
            }
        } else {
            $this->telegramService->sendMessage(
                $registration->chat_id,
                "Iltimos, obuna bo'lgach 'Tekshirish' tugmasini bosing."
            );
        }
    }

    /**
     * Check if user is admin
     */
    private function isAdmin(int $chatId): bool
    {
        $adminChatId = config('telegram.admin_chat_id');
        return $adminChatId && (int) $adminChatId === $chatId;
    }

    /**
     * Handle admin commands
     */
    private function handleAdminCommand(int $chatId, string $text): void
    {
        switch ($text) {
            case '/admin':
                $this->showAdminMenu($chatId);
                break;

            case '/export':
                $this->handleExport($chatId);
                break;

            default:
                $this->telegramService->sendMessage(
                    $chatId,
                    "Admin buyruqlari:\n/admin - Admin menyu\n/export - Excel fayl yuklab olish"
                );
        }
    }

    /**
     * Show admin menu
     */
    private function showAdminMenu(int $chatId): void
    {
        $totalRegistrations = Registration::where('is_subscribed', true)->count();
        $pendingRegistrations = Registration::where('is_subscribed', false)->count();

        $message = "👤 Admin Paneli\n\n";
        $message .= "Jami ro'yxatdan o'tganlar: {$totalRegistrations}\n";
        $message .= "Kutilayotganlar: {$pendingRegistrations}\n\n";
        $message .= "Buyruqlar:\n";
        $message .= "/export - Excel fayl yuklab olish";

        $this->telegramService->sendMessage($chatId, $message);
    }

    /**
     * Handle export command
     */
    private function handleExport(int $chatId): void
    {
        try {
            $this->telegramService->sendMessage(
                $chatId,
                "Excel fayl tayyorlanmoqda..."
            );

            // Note: In a real implementation, you might want to send the file via Telegram
            // For now, we'll just confirm. You can implement file sending via Telegram Bot API
            $this->telegramService->sendMessage(
                $chatId,
                "Excel fayl tayyorlandi. Web interfeys orqali yuklab olish mumkin."
            );
        } catch (\Exception $e) {
            Log::error('Export error: ' . $e->getMessage());
            $this->telegramService->sendMessage(
                $chatId,
                "Xatolik: " . $e->getMessage()
            );
        }
    }

    /**
     * Export registrations as Excel (web route)
     */
    public function export(): BinaryFileResponse
    {
        return Excel::download(new RegistrationsExport, 'registrations.xlsx');
    }

    /**
    * Build inline keyboard for grade selection (rows of 5)
    */
    private function buildGradeInlineKeyboard(): array
    {
        $buttons = [];
        $row = [];

        for ($i = 1; $i <= 10; $i++) {
            $row[] = [
                'text' => (string) $i,
                'callback_data' => 'grade:' . $i,
            ];

            if (count($row) === 5) {
                $buttons[] = $row;
                $row = [];
            }
        }

        if (!empty($row)) {
            $buttons[] = $row;
        }

        return $this->telegramService->createInlineKeyboard($buttons);
    }
}

