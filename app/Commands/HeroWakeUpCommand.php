<?php
namespace App\Commands;

use App\Models\Hero;
use Telegram\Bot\Commands\Command;

class HeroWakeUpCommand extends Command
{
    /**
     * @var string Command Name
     */
    protected $name = "wake_up";

    protected $description = "Если не можешь совершать подвиги или отлучился";

    public function handle($arguments)
    {
        $updates = $this->getTelegram()->getWebhookUpdates();
        $hero = Hero::where('chat_id', $updates['message']['chat']['id'])->first();
        if (!empty($hero)) {
            if ($hero->active) {
                $this->replyWithMessage([
                    'text' => 'Ты уже бодрствуешь :) (Для справки набери /help)'
                ]);
                die;
            }
            Hero::where('chat_id', $updates['message']['chat']['id'])->update([
                'active' => true
            ]);
            $this->replyWithMessage([
                'text' => "Настал Твой час доставить удовольствие нашим прекрасным дамам! Тададада-тадааа! (Для справки набери /help)"
            ]);
        } else {
            $this->replyWithMessage([
                'text' => 'Сначала зарегайся: /new_hero (Для справки набери /help)'
            ]);
        }
    }
}