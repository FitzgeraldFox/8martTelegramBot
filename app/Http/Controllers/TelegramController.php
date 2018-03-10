<?php

namespace App\Http\Controllers;

use App\Utils\CallbackUtils;
use Telegram;

class TelegramController extends Controller
{
    public function getWebhookUpdates()
    {
        $update = Telegram::commandsHandler(true);
        if (!empty($update['callback_query'])) {
            CallbackUtils::handleCallback($update);
        }
    }
}