<?php
namespace App\Commands;

use App\Models\Hero;
use Telegram;
use Telegram\Bot\Commands\Command;

class NewHeroCommand extends Command
{
    /**
     * @var string Command Name
     */
    protected $name = "new_hero";

    protected $description = "Если хочешь вступить в ряды героев наших дам";

    public function handle($arguments)
    {
        $updates = $this->getTelegram()->getWebhookUpdates();
        if (empty(Hero::where('chat_id', $updates['message']['chat']['id'])->first())) {
            $reply_markup = Telegram::replyKeyboardHide();
            $hero = new Hero;
            $hero->chat_id = $updates['message']['chat']['id'];
            $hero->name = $updates['message']['chat']['first_name'] . ' ' . $updates['message']['chat']['last_name'];
            $hero->save();

            $this->replyWithMessage([
                'text' => "Ты стал героем для наших прекрасных дам! \nВперёд, завоёвывать их сердца!)))",
                'reply_markup' => $reply_markup
            ]);
        } else {
            $this->replyWithMessage([
                'text' => 'Ты уже в отряде супергероев, малец!'
            ]);
        }
    }
}