<?php
namespace App\Commands;

use App\Models\WishType;
use Exception;
use Illuminate\Support\Facades\Log;
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

            $keyboard = [];
            $commandDescription = '';
            foreach ($wishTypes as $wishType) {
                $commandDescription .= "{$wishType->command}: {$wishType->description}\n";
                $keyboard[] = [$wishType->command . ' - ' . $wishType->wish_text];
            }

            $reply_markup = Telegram::replyKeyboardMarkup([
                'keyboard' => $keyboard,
                'resize_keyboard' => true,
                'one_time_keyboard' => false
            ]);

            $startText = "Привет, {$updates['message']['from']['first_name']} {$updates['message']['from']['last_name']}! Этот супергеройский бот создан, чтобы сделать тебя немного счастливее и подарить тебе море положительных эмоций!)))\n $commandDescription\n Чего пожелаешь?)";

            $this->replyWithMessage([
                'text' => $startText,
                'reply_markup' => $reply_markup
            ]);
        } catch (Exception $e) {
            Log::error(self::class . " Error: {$e->getMessage()}");
        }
    }
}