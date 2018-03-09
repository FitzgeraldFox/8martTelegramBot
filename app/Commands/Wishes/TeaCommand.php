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

        $chatId = $updates['message']['chat']['id'];

        WishUtils::validateWish($chatId);

        $userWish = Wish::where([
            'chat_id' => $updates['message']['from']['id'],
            'wish_type_id' => WishType::WISH_TEA_ID
        ])->first();

        if (!empty($userWish)) {
            if ($userWish->wish_count >= WishType::select('wish_count')->where('id', WishType::WISH_TEA_ID)->first()->wish_count) {
                $this->replyWithPhoto([
                    'photo' => WishUtils::getFunPic(),
                ]);
                die;
            }
            $userWish->wish_count += 1;
            $userWish->save();
            die;
        } else {
            $userWish = new Wish;
            $userWish->chat_id = $updates['message']['from']['id'];
            $userWish->wish_type_id = WishType::WISH_TEA_ID;
            $userWish->save();
        }

        WishUtils::executeWish($updates, $chatId, $userWish);
    }
}