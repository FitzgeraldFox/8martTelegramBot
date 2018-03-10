<?php

namespace App\Commands\Wishes;

use App\Models\Hero;
use App\Models\Wish;
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
        try {
            $updates = $this->getTelegram()->getWebhookUpdates();
            $wish = WishUtils::validateWish($updates['message']['chat']['id'], WishType::WISH_HUGS_ID);
            WishUtils::executeWish($updates, $wish);
        } catch (Exception $e) {
            Log::error("Error: {$e->getMessage()} (update: " . json_encode($update) . "; wish: " . json_encode($wish) . "; heroes: )");
        }
    }
}