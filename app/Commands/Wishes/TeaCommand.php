<?php

namespace App\Commands\Wishes;

use App\Models\Hero;
use App\Models\Wish;
use App\Models\WishType;
use App\Utils\WishUtils;
use Telegram\Bot\Commands\Command;

class TeaCommand extends Command
{
    /**
     * @var string Command Name
     */
    protected $name = "tea";

    protected $description = "Принести чай";

    public function handle($arguments)
    {
        $updates = $this->getTelegram()->getWebhookUpdates();
        $wish = WishUtils::validateWish($updates['message']['chat']['id'], WishType::WISH_TEA_ID, $updates);
        WishUtils::executeWish($wish);
    }
}