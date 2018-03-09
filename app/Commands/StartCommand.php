<?php
namespace App\Commands;

use App\Models\WishType;
use Exception;
use Telegram;
use Telegram\Bot\Commands\Command;

class StartCommand extends Command
{
    /**
     * @var string Command Name
     */
    protected $name = "start";

    protected $description = "Start Command to get you started";

    public function handle($arguments)
    {
        try {
            $updates = $this->getTelegram()->getWebhookUpdates();
            $wishTypes = WishType::get();
            $keyboard = [[['text' => "/tea"]], [['text' => "/coffee"]], [['text' => "/hugs"]]];
            $this->replyWithMessage([
                'text' => "Привет, {$updates['message']['from']['first_name']} {$updates['message']['from']['last_name']}! Этот супергеройский бот создан, чтобы сделать тебя немного счастливее и подарить тебе море положительных эмоций!)))\n Доступны три желания: принести чай, кофе или обнимашки :) Чай и кофе можно пожелать один раз каждый. Обнимашки можно желать, сколько душе угодно :) \n/tea: {$wishTypes[0]['wish_text']};\n/coffee: {$wishTypes[1]['wish_text']};\n/hugs: {$wishTypes[2]['wish_text']}.\n Чего пожелаешь?)",
                'reply_markup' => Telegram::replyKeyboardMarkup([
                    'keyboard' => $keyboard,
                    'resize_keyboard' => true,
                    'one_time_keyboard' => false
                ])
            ]);
        } catch (Exception $e) {
            $this->replyWithMessage(['text' => $e->getMessage()]);
        }
    }
}