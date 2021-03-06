<?php

namespace App\Utils;

use App\Models\Hero;
use App\Models\Wish;
use App\Models\WishType;
use DateInterval;
use DateTime;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Telegram;

class WishUtils
{
    public static function executeWish(
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

            $heroId = $heroes[mt_rand(0, count($heroes) - 1)]['chat_id'];
            $hero = Hero::where('chat_id', $heroId)->first();
            $hero->proposed_wish_id = $wish->id;
            $hero->save();

            $wish->expired_at = date_add(new DateTime(), DateInterval::createFromDateString(env('HERO_COMPLIANCE_TIME') . ' sec'));
            $wish->save();

            Telegram::sendMessage([
                'chat_id' => $heroId,
                'text' => "{$wish->woman_name} хочет, чтобы ты принёс ей $wishTypeText. Это твой шанс, парень!",
                'reply_markup' => $reply_markup
            ]);

            die;
        } catch (Exception $e) {
            Telegram::sendMessage([
                'chat_id' => env('DEVELOPER_CHAT_ID'),
                'text' => $e->getMessage()
            ]);
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

    public static function validateWish($chatId, $typeId, $updates)
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
                $wish->woman_name = $updates["message"]["chat"]["first_name"] . ' ' . $updates["message"]["chat"]["last_name"];
                $wish->save();
                return $wish;
            }
        } catch (Exception $e) {
            Log::error(self::class . " validateWish Error: {$e->getMessage()} (arguments: " . json_encode($updates) . ")");
        }
    }

    public static function updateRejectedHeroes(Wish $wish, Hero $hero)
    {
        try {
            $hero->is_busy = false;
            $hero->proposed_wish_id = null;
            $hero->save();
            $rejectedHeroesArray = explode(',', $wish->rejected_heroes);
            if (!in_array($hero->id, $rejectedHeroesArray)) {
                $rejectedHeroesArray[] = $hero->id;
            }
            $wish->rejected_heroes = implode(',', $rejectedHeroesArray);
            $wish->hero_id = null;
            $wish->save();
            return $rejectedHeroesArray;
        } catch (Exception $e) {
            Telegram::sendMessage([
                'chat_id' => $wish->chat_id,
                'text' => $e->getMessage(),
            ]);
        }
    }
}