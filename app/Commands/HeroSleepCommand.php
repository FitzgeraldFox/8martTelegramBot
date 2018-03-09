<?php
namespace App\Commands;

use App\Models\Hero;
use Telegram\Bot\Commands\Command;

class HeroSleepCommand extends Command
{
    /**
     * @var string Command Name
     */
    protected $name = "sleep";

    protected $description = "Если не можешь совершать подвиги или отлучился";

    public function handle($arguments)
    {
        $updates = $this->getTelegram()->getWebhookUpdates();
        $hero = Hero::where('chat_id', $updates['message']['chat']['id'])->first();
        if (!empty($hero)) {
            if ($hero->is_busy) {
                $this->replyWithMessage([
                    'text' => 'Ты выполняешь подвиг. Заверши его исполнение, и можешь отдохнуть (Для справки набери /help)'
                ]);
                die;
            }
            Hero::where('chat_id', $updates['message']['chat']['id'])->update([
                'active' => false
            ]);
            $this->replyWithMessage([
                'text' => "Ты отошёл от дел и начал вести спокойную мирную жизнь на ферме где-то в штате Техас. \nКогда-нибудь ты вернёшься, чтобы снова спасти Человечество..."
            ]);
        } else {
            $this->replyWithMessage([
                'text' => 'Сначала зарегайся: /new_hero (Для справки набери /help)'
            ]);
        }
    }
}