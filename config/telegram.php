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

    /*
    |--------------------------------------------------------------------------
    | Study Center Bot Configuration
    |--------------------------------------------------------------------------
    */

    'study_center_bot_token' => env('TELEGRAM_STUDY_CENTER_BOT_TOKEN'),

    'study_center_channel_username' => env('TELEGRAM_STUDY_CENTER_CHANNEL_USERNAME'),

    'study_center_admin_chat_id' => env('TELEGRAM_STUDY_CENTER_ADMIN_CHAT_ID'),
];

