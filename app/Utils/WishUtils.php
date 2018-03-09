<?php
namespace App\Utils;

use App\Models\Hero;
use App\Models\Wish;
use App\Models\WishType;
use Exception;
use Illuminate\Support\Facades\DB;
use Telegram;

class WishUtils
{
    public static function executeWish(
        $updates,
        $chatId,
        $wish,
        $rejectedHeroes = []
    ) {
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
                $heroes = Hero::where(['active' => true, 'is_busy' => false])->get()->toArray();
            }

            if (count($heroes) == 0) {
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => 'Прости, но на данный момент все герои либо заняты, либо не могут прийти к тебе :(',
                ]);

                if ($wish->wish_type_id == WishType::WISH_HUGS_ID) {
                    if ($wish->wish_count > 1) {
                        $wish->wish_count -= 1;
                        $wish->save();
                    } else {
                        $wish->delete();
                    }
                } else {
                    $wish->delete();
                }
                die;
            }

            $rand_hero_number = mt_rand(0, count($heroes) - 1);

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

            if (!empty($updates['callback_query'])) {
                $womanName = $updates['callback_query']["message"]["chat"]["first_name"] . ' ' . $updates['callback_query']["message"]["chat"]["last_name"];
            } else {
                $womanName = $updates["message"]["chat"]["first_name"] . ' ' . $updates["message"]["chat"]["last_name"];
            }

            $wishTypeText = WishType::select('wish_text')->where([
                'id' => $wish->wish_type_id
            ])->first()->wish_text;

            Telegram::sendMessage([
                'chat_id' => $heroes[$rand_hero_number]['chat_id'],
                'text' => "$womanName хочет, чтобы ты принёс ей $wishTypeText. Это твой шанс, парень!",
                'reply_markup' => $reply_markup
            ]);
        } catch (Exception $e) {
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => $e->getMessage()
            ]);
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

    public static function validateWish($chatId)
    {
        if (!empty(Hero::where('chat_id', $chatId)->first())) {
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' =>  'Ах ты Шалун!)'
            ]);
            die;
        }

        if (!empty(Wish::where([
            'handled' => 0,
            'chat_id' => $chatId
        ])->first())) {
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => "Пожалуйста, подтвердите исполнение предыдущего желания или откажитесь от него"
            ]);
            die;
        }
    }

    public static function getHeroName($chatId, $requestArray)
    {
        $hero = Hero::where('chat_id', $chatId)->first();
        if (!empty($hero) && empty($hero->name)) {
            $hero->name = $requestArray['message']['chat']['first_name'] . ' ' . $requestArray['message']['chat']['last_name'];
            $hero->save();
        }
    }
}