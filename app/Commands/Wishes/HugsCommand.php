<?php

namespace App\Commands\Wishes;

use App\Models\WishType;
use App\Utils\WishUtils;
use Telegram\Bot\Commands\Command;

class HugsCommand extends Command
{
    /**
     * @var string Command Name
     */
    protected $name = "hugs";

    protected $description = "Обнимашки";

    public function handle($arguments)
    {
        $updates = $this->getTelegram()->getWebhookUpdates();
        $wish = WishUtils::validateWish($updates['message']['chat']['id'], WishType::WISH_HUGS_ID, $updates);
        WishUtils::executeWish($wish);
    }
}