<?php

namespace App\Utils;

use App\Models\Hero;
use App\Models\Wish;
use App\Models\WishType;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Telegram;

class WishUtils
{
    public static function executeWish(
        $update,
        $wish,
        $rejectedHeroes = []
    ) {
        try {
            $heroes = self::getFreeHeroes($rejectedHeroes);

            if (count($heroes) == 0) {
                Telegram::sendMessage([
                    'chat_id' => $wish->chat_id,
                    'text' => 'Прости, но на данный момент все герои либо заняты, либо не могут прийти к тебе :(',
                ]);

                $wish->delete();
                die;
            }

            Telegram::sendMessage([
                'chat_id' => $wish->chat_id,
                'text' => 'Ищем героя...'
            ]);

            if (!empty($update['callback_query'])) {
                $womanName = $update['callback_query']["message"]["chat"]["first_name"] . ' ' . $update['callback_query']["message"]["chat"]["last_name"];
            } else {
                $womanName = $update["message"]["chat"]["first_name"] . ' ' . $update["message"]["chat"]["last_name"];
            }

            $wishTypeText = WishType::select('wish_text')->where([
                'id' => $wish->wish_type_id
            ])->first()->wish_text;

            $reply_markup = json_encode([
                'inline_keyboard' => [
                    [
                        [
                            'text' => 'Сделаю',
                            'callback_data' => json_encode([
                                'answer' => true,
                                'wishId' => $wish->id
                            ])
                        ],
                        [
                            'text' => 'Не могу сделать',
                            'callback_data' => json_encode([
                                'answer' => false,
                                'wishId' => $wish->id
                            ])
                        ]
                    ]
                ]
            ]);

            Telegram::sendMessage([
                'chat_id' => $heroes[mt_rand(0, count($heroes) - 1)]['chat_id'],
                'text' => "$womanName хочет, чтобы ты принёс ей $wishTypeText. Это твой шанс, парень!",
                'reply_markup' => $reply_markup
            ]);
        } catch (Exception $e) {
            Log::error(self::class . " executeWish Error: {$e->getMessage()}");
        }
    }

    public static function getFreeHeroes($rejectedHeroes = [])
    {
        try {
            if (!empty($rejectedHeroes)) {
                $sql = <<<QUERY
    SELECT *
    FROM heroes
    WHERE active = TRUE AND is_busy = FALSE
    AND ?
QUERY;
                $sqlRejectedHeroes = '';
                foreach ($rejectedHeroes as $rejectedHeroId) {
                    $sqlRejectedHeroes .= 'id != ' . $rejectedHeroId . ' AND ';
                }

                $heroes = json_decode(json_encode(DB::select($sql, [rtrim($sqlRejectedHeroes, ' AND ')])), true);
            } else {
                $heroes = Hero::select('chat_id')->where(['active' => true, 'is_busy' => false])->get()->toArray();
            }
            return $heroes;
        } catch (Exception $e) {
            Log::error(self::class . " getFreeHeroes Error: {$e->getMessage()} (arguments: " . json_encode(func_get_args()) . ")");
        }
    }

    public static function getFunPic()
    {
        $fun_pics = [
            'http://atkritka.com/upload/iblock/d0f/atkritka_1302175977_505.jpg',
            'http://atkritka.com/upload/iblock/188/atkritka_1350571045_877.jpg',
            'http://www.povarenok.ru/data/cache/2013oct/21/16/539581_25547-640x0.jpg'
        ];

        return $fun_pics[mt_rand(0, count($fun_pics) - 1)];
    }

    public static function validateWish($chatId, $typeId)
    {
        try {
            if (!empty(Hero::where('chat_id', $chatId)->first())) {
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => 'Ах ты Шалун!)'
                ]);
                die;
            }

            $wishCount = Wish::where([
                'chat_id' => $chatId,
                'wish_type_id' => $typeId
            ])->get()->count();

            $wishTypeCount = WishType::select('wish_count')->where('id', $typeId)->first()->wish_count;

            if ($wishTypeCount != -1 && $wishCount >= $wishTypeCount) {
                Telegram::sendPhoto([
                    'chat_id' => $chatId,
                    'photo' => WishUtils::getFunPic(),
                ]);
                die;
            } else {
                $wish = new Wish;
                $wish->chat_id = $chatId;
                $wish->wish_type_id = $typeId;
                $wish->save();
                return $wish;
            }
        } catch (Exception $e) {
            Log::error(self::class . " validateWish Error: {$e->getMessage()} (arguments: " . json_encode(func_get_args()) . ")");
        }
    }
}