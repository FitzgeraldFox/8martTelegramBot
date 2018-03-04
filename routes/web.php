<?php
$router->post('/' . env('TELEGRAM_BOT_TOKEN') . '/webhook', 'TelegramController@getWebhookUpdates');