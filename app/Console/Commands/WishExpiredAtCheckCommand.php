<?php

namespace App\Console\Commands;

use App\Models\Hero;
use App\Models\Wish;
use App\Utils\WishUtils;
use Exception;
use Illuminate\Console\Command;
use Telegram;

class WishExpiredAtCheckCommand extends Command
{
    protected $signature = 'wish:expired_at_check';

    protected $description = 'Проверка на то, истёк ли срок действия желания. Если желание имеет expired_at и не имеет героя, то значит, что герою отправилось приглашение совершить подвиг, но он его не принял. В этом случае ище другого героя. Если hero_id != null, тогда герой профакапился, и мы спрашиваем, актуально ли ещё желание. Если да, то выбираем другого героя, если нет - отменяем желание';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        try {
            $no_hero_wishes = Wish::where([
                ['handled', '=', false],
                ['expired_at', '>=', date('Y-m-d H:i:s', time())],
                ['hero_id', '=', null]
            ])->get();

            $too_late_hero_wishes = Wish::where([
                ['handled', '=', false],
                ['expired_at', '>=', date('Y-m-d H:i:s', time())],
                ['hero_id', '<>', null]
            ])->get();

            foreach ($no_hero_wishes as $wish) {
                $rejectedHeroesArray = WishUtils::updateRejectedHeroes($wish,
                    Hero::where('proposed_wish_id', '=', $wish->id)->first());
                WishUtils::executeWish($wish, $rejectedHeroesArray);
            }

            foreach ($too_late_hero_wishes as $wish) {
                $reply_markup = json_encode([
                    'inline_keyboard' => [
                        [
                            [
                                'text' => 'Да',
                                'callback_data' => json_encode([
                                    'isWishActive' => true,
                                    'wishId' => $wish->id
                                ])
                            ],
                            [
                                'text' => 'Нет',
                                'callback_data' => json_encode([
                                    'isWishActive' => false,
                                    'wishId' => $wish->id
                                ])
                            ]
                        ]
                    ]
                ]);

                Telegram::sendMessage([
                    'chat_id' => $wish->chat_id,
                    'text' => "Тысяча извинений, милая леди! Ваш герой проспал... Ваше желание ещё в силе?",
                    'reply_markup' => $reply_markup
                ]);
            }
        } catch (Exception $e) {
            Telegram::sendMessage([
                'chat_id' => env('DEVELOPER_CHAT_ID'),
                'text' => $e->getMessage(),
            ]);
        }
    }
}