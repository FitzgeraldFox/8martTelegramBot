<?php
namespace App\Commands\Wishes;

use App\Models\Hero;
use App\Models\Wish;
use App\Models\WishType;
use App\Utils\WishUtils;
use Telegram\Bot\Commands\Command;

class CoffeeCommand extends Command
{
    /**
     * @var string Command Name
     */
    protected $name = "coffee";

    protected $description = "Принести кофе";

    public function handle($arguments)
    {
        $updates = $this->getTelegram()->getWebhookUpdates();
        $wish = WishUtils::validateWish($updates['message']['chat']['id'], WishType::WISH_COFFEE_ID);
        WishUtils::executeWish($updates, $wish);
    }
}