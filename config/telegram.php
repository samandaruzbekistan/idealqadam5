<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Telegram Bot Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Telegram Bot API integration
    |
    */

    'bot_token' => env('TELEGRAM_BOT_TOKEN'),

    'admin_chat_id' => env('TELEGRAM_ADMIN_CHAT_ID'),

    'channel_username' => env('TELEGRAM_CHANNEL_USERNAME'),

    'instagram_link' => env('TELEGRAM_INSTAGRAM_LINK', ''),

    'youtube_link' => env('TELEGRAM_YOUTUBE_LINK', ''),

    'webhook_url' => env('TELEGRAM_WEBHOOK_URL'),
];

