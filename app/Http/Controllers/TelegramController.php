<?php

namespace App\Http\Controllers;

use App\Models\Hero;
use App\Utils\CallbackUtils;
use Illuminate\Support\Facades\DB;
use Telegram;

class TelegramController extends Controller
{
    public function getWebhookUpdates()
    {
        $update = Telegram::commandsHandler(true);

        if (!empty($update['callback_query'])) {
            CallbackUtils::handleCallback($update);
        }
    }

    public static function executeWish(
        $telegram,
        $requestArray,
        $realWishText,
        $womanChatId,
        $wish,
        $rejectedHeroes = []
    ) {
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
            $telegram->sendMessage([
                'chat_id' => $womanChatId,
                'text' => 'Прости, но на данный момент все герои либо заняты, либо не могут прийти к тебе :(',
            ]);

            if ($wish->wish_type == 'Хочу Обнимашки!)))') {
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

        if (!empty($requestArray['callback_query'])) {
            $womanName = $requestArray['callback_query']["message"]["chat"]["first_name"] . ' ' . $requestArray['callback_query']["message"]["chat"]["last_name"];
        } else {
            $womanName = $requestArray["message"]["chat"]["first_name"] . ' ' . $requestArray["message"]["chat"]["last_name"];
        }

        $telegram->sendMessage([
            'chat_id' => $heroes[$rand_hero_number]['chat_id'],
            'text' => "$womanName хочет, чтобы ты принёс ей $realWishText. Это твой шанс, парень!",
            'reply_markup' => $reply_markup
        ]);
    }
}