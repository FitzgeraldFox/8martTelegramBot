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
        $updates = $this->getTelegram()->getWebhookUpdates();

        $chatId = $updates['message']['chat']['id'];

        WishUtils::validateWish($chatId);

        $userWish = Wish::where([
            'chat_id' => $updates['message']['from']['id'],
            'wish_type_id' => WishType::WISH_HUGS_ID
        ])->first();

        if (empty($userWish)) {
            $userWish = new Wish;
            $userWish->chat_id = $updates['message']['from']['id'];
            $userWish->wish_type_id = WishType::WISH_HUGS_ID;
            $userWish->save();
        }

        WishUtils::executeWish($updates, $chatId, $userWish);
    }
}