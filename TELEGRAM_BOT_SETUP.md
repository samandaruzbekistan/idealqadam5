# Ideal Qadam 5 - Telegram Bot Setup Guide

## Overview

This is a Laravel 10 Telegram Bot for Olympiad registration. The bot uses webhook-based Telegram Bot API and implements a state-based conversation flow.

## Features

- Step-by-step registration flow
- Grade-based subject selection (1-10 grades)
- Automatic subject assignment for grades 1-4
- Channel subscription verification
- Admin panel with Excel export
- Clean architecture (Controllers, Services)

## Requirements

- PHP 8.1 or higher
- Laravel 10
- MySQL database
- Composer
- Telegram Bot Token (from @BotFather)

## Installation

### 1. Install Dependencies

```bash
composer install
composer require maatwebsite/excel
```

### 2. Configure Environment

Copy `.env.example` to `.env` and configure the following variables:

```env
# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=idealqadam5
DB_USERNAME=root
DB_PASSWORD=your_password

# Telegram Bot Configuration
TELEGRAM_BOT_TOKEN=your_bot_token_from_botfather
TELEGRAM_ADMIN_CHAT_ID=your_telegram_chat_id
TELEGRAM_CHANNEL_USERNAME=your_channel_username
TELEGRAM_INSTAGRAM_LINK=https://instagram.com/your_account
TELEGRAM_YOUTUBE_LINK=https://youtube.com/@your_channel
TELEGRAM_WEBHOOK_URL=https://yourdomain.com/api/telegram/webhook
```

### 3. Generate Application Key

```bash
php artisan key:generate
```

### 4. Run Migrations

```bash
php artisan migrate
```

### 5. Publish Excel Configuration (if needed)

```bash
php artisan vendor:publish --provider="Maatwebsite\Excel\ExcelServiceProvider" --tag=config
```

### 6. Set Up Webhook

Set your webhook URL using Telegram Bot API:

```bash
curl -X POST "https://api.telegram.org/bot<YOUR_BOT_TOKEN>/setWebhook" \
  -d "url=https://yourdomain.com/api/telegram/webhook"
```

Or use a browser:
```
https://api.telegram.org/bot<YOUR_BOT_TOKEN>/setWebhook?url=https://yourdomain.com/api/telegram/webhook
```

## Bot Flow

### User Registration Flow

1. **/start** - User starts the bot
   - Bot asks for full name

2. **Full Name** - User enters name
   - Bot shows grade selection (1-10)

3. **Grade Selection** - User selects grade
   - **Grades 1-4**: Automatically assigns subjects (Matematika, Ingliz tili, Mantiq)
   - **Grades 5-6**: Shows 2 options
     - Tabiiy fan - Ingliz tili
     - Matematika - Ingliz tili
   - **Grades 7-10**: Shows 4 options
     - Matematika - Fizika
     - Matematika - Ingliz tili
     - Biologiya - Kimyo
     - Huquq - Ingliz tili

4. **Subject Selection** (if applicable)
   - User selects from available options

5. **Subscription Check**
   - Bot asks user to subscribe to Telegram channel (required)
   - Optional: Instagram and YouTube links
   - User clicks "Tekshirish" button
   - Bot verifies subscription using `getChatMember` API

6. **Completion**
   - If subscribed: Registration completed
   - If not: Ask to subscribe again

### Admin Commands

- `/admin` - Show admin menu with statistics
- `/export` - Export registrations to Excel (via web: `/admin/export`)

## Database Schema

### registrations table

- `id` - Primary key
- `chat_id` - Telegram chat ID (unique)
- `full_name` - User's full name
- `grade` - Selected grade (1-10)
- `subjects` - Selected subjects (string)
- `is_subscribed` - Subscription status (boolean)
- `state` - Current conversation state
- `created_at` - Registration timestamp
- `updated_at` - Last update timestamp

## File Structure

```
app/
├── Exports/
│   └── RegistrationsExport.php    # Excel export class
├── Http/
│   └── Controllers/
│       └── TelegramController.php # Main bot controller
├── Models/
│   └── Registration.php           # Registration model
└── Services/
    ├── RegistrationService.php     # Registration business logic
    └── TelegramService.php        # Telegram API wrapper

config/
└── telegram.php                   # Telegram configuration

routes/
├── api.php                         # Webhook route
└── web.php                         # Export route

database/
└── migrations/
    └── 2025_12_15_083737_create_registrations_table.php
```

## Getting Your Telegram Chat ID

To get your admin chat ID:

1. Start a conversation with your bot
2. Send a message to your bot
3. Visit: `https://api.telegram.org/bot<YOUR_BOT_TOKEN>/getUpdates`
4. Find your chat ID in the response: `"chat":{"id":123456789}`

## Channel Username Format

For the channel username, use the format without `@`:
- If your channel is `@my_channel`, use: `my_channel`
- Or use the channel ID format: `-1001234567890`

## Testing

1. Start your Laravel server:
   ```bash
   php artisan serve
   ```

2. Use ngrok or similar tool to expose your local server:
   ```bash
   ngrok http 8000
   ```

3. Set webhook to your ngrok URL:
   ```
   https://api.telegram.org/bot<TOKEN>/setWebhook?url=https://your-ngrok-url.ngrok.io/api/telegram/webhook
   ```

4. Test the bot by sending `/start` command

## Excel Export

Admin can export registrations via:
- Web: `https://yourdomain.com/admin/export`
- Telegram: `/export` command (sends confirmation message)

The Excel file includes:
- Full Name
- Grade
- Subjects
- Registration Date

## Troubleshooting

### Webhook not receiving updates
- Check if webhook URL is accessible
- Verify SSL certificate is valid
- Check Laravel logs: `storage/logs/laravel.log`

### Subscription check not working
- Ensure bot is added to the channel as administrator
- Verify channel username format (without @)
- Check bot has permission to get chat members

### Excel export not working
- Run: `composer require maatwebsite/excel`
- Check storage permissions
- Verify PHP has required extensions

## Security Notes

- Keep your bot token secure
- Use HTTPS for webhook URL
- Consider adding authentication to export route
- Regularly update dependencies

## Support

For issues or questions, check:
- Laravel documentation: https://laravel.com/docs/10.x
- Telegram Bot API: https://core.telegram.org/bots/api
- Maatwebsite Excel: https://docs.laravel-excel.com/

