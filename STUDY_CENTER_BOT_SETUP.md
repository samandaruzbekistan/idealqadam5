# Ideal Study O'quv Markazi Bot - Setup Guide

## Umumiy ma'lumot

Bu Ideal Study o'quv markazi uchun alohida Telegram bot. Bot foydalanuvchilarni ro'yxatdan o'tkazadi va adminlar bilan bog'lanishadi.

## Bot Flow

1. **/start** - Salom xabari
2. **Telegram kanalga obuna bo'lish** (majburiy)
3. **Ism va familiya** - Foydalanuvchi ismini so'raydi
4. **Fanlar** - Qaysi fanlarni o'qimoqchi yoki o'qiyotganini so'raydi
5. **Telefon raqami** - Telefon raqamini so'raydi
6. **Tugallash** - "Sizga adminlarimiz bog'lanishadi" xabari

## O'rnatish

### 1. Environment Variables

`.env` fayliga quyidagilarni qo'shing:

```env
# Study Center Bot Configuration
TELEGRAM_STUDY_CENTER_BOT_TOKEN=your_study_center_bot_token_here
TELEGRAM_STUDY_CENTER_CHANNEL_USERNAME=your_study_center_channel_username
```

### 2. Database Migration

```bash
php artisan migrate
```

Bu quyidagi jadvalni yaratadi:
- `study_center_registrations` - Ro'yxatdan o'tganlar

### 3. Webhook O'rnatish

Telegram Bot API orqali webhook o'rnating:

```bash
curl -X POST "https://api.telegram.org/bot<STUDY_CENTER_BOT_TOKEN>/setWebhook" \
  -d "url=https://yourdomain.com/api/study-center/webhook"
```

Yoki brauzerda:
```
https://api.telegram.org/bot<STUDY_CENTER_BOT_TOKEN>/setWebhook?url=https://yourdomain.com/api/study-center/webhook
```

## Database Schema

### study_center_registrations

- `id` - Primary key
- `chat_id` - Telegram chat ID (unique)
- `full_name` - Foydalanuvchi ismi
- `subjects` - O'qimoqchi/o'qiyotgan fanlar
- `phone` - Telefon raqami
- `is_subscribed` - Kanalga obuna bo'lganligi
- `state` - Holat (start, subscription, full_name, subjects, phone, completed)
- `created_at` - Yaratilgan vaqt
- `updated_at` - Yangilangan vaqt

## Bot Buyruqlari

- `/start` - Botni boshlash yoki ma'lumotlarni ko'rish
- `/restart` - Qayta ro'yxatdan o'tish

## Fayl Strukturasi

```
app/
├── Http/
│   └── Controllers/
│       └── StudyCenterController.php    # Study Center bot controller
├── Models/
│   └── StudyCenterRegistration.php      # Study Center registration model
└── Services/
    └── StudyCenterService.php           # Study Center business logic

config/
└── telegram.php                         # Study Center bot config

routes/
└── api.php                              # Study Center webhook route

database/
└── migrations/
    └── 2025_12_19_092658_create_study_center_registrations_table.php
```

## Xususiyatlar

- ✅ Majburiy kanal obunasi tekshiruvi
- ✅ Inline keyboard tugmalari
- ✅ State-based conversation flow
- ✅ Telefon raqami validatsiyasi
- ✅ Qayta ro'yxatdan o'tish imkoniyati
- ✅ Ro'yxatdan o'tgan foydalanuvchilarga ma'lumot ko'rsatish

## Testing

1. Bot token va kanal username ni `.env` ga qo'shing
2. Migration ni ishga tushiring
3. Webhook ni o'rnating
4. Botga `/start` yuboring va test qiling

## Eslatmalar

- Bot token va kanal username to'g'ri sozlanishi kerak
- Bot kanalga admin sifatida qo'shilgan bo'lishi kerak (obuna tekshiruvi uchun)
- Webhook URL HTTPS bo'lishi kerak

