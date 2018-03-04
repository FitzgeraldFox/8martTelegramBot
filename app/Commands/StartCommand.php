<?php
namespace App\Commands;

use Telegram;
use Telegram\Bot\Api;
use Telegram\Bot\Commands\Command;

class StartCommand extends Command
{
    /**
     * @var string Command Name
     */
    protected $name = "start";

    /**
     * @var string Command Description
     */
    protected $description = "Start Command to get you started";

    /**
     * @inheritdoc
     */
    public function handle($arguments)
    {
        $commands = $this->getTelegram()->getCommands();
        $keyboard = [];
        foreach ($commands as $name => $command) {
            $keyboard[] = $name;
        }

        $reply_markup = Telegram::replyKeyboardMarkup([
            'keyboard' => $keyboard,
            'resize_keyboard' => true,
            'one_time_keyboard' => false
        ]);

        $response = Telegram::sendMessage([
            'chat_id' => 'CHAT_ID',
            'text' => 'Привет! Этот бот создан, чтобы сделать тебя немного счастливее!)',
            'reply_markup' => $reply_markup
        ]);
    }
}