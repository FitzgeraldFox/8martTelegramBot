<?php

namespace App\Utils;


use App\Models\Hero;
use App\Models\Wish;
use App\Models\WishType;
use DateTime;
use Exception;
use Telegram;
use Telegram\Bot\Api;

class CallbackUtils
{
    public static function handleCallback($update)
    {
        try {
            $telegram = new Api(env('TELEGRAM_BOT_TOKEN'));
            $data = json_decode($update['callback_query']['data'], true);
            $hero = Hero::where([
                'chat_id' => $update['callback_query']['message']['chat']['id']
            ])->first();

            $wish = Wish::where([
                'id' => $data['wishId'],
                'handled' => false
            ])->first();

            if (empty($wish)) {
                $telegram->sendMessage([
                    'chat_id' => $update['callback_query']['message']['chat']['id'],
                    'text' => 'Не повторяется такое никогда...'
                ]);
                die;
            }

            if (empty($hero)) {
                $telegram->sendMessage([
                    'chat_id' => $update['callback_query']['message']['chat']['id'],
                    'text' => 'Герой не найден'
                ]);
                die;
            }

            if (isset($data['answer']) && $data['answer'] == true) {
                $wish->hero_id = $hero->id;
                $wish->hero_get_time = new DateTime();
                $wish->save();

                $hero->is_busy = true;
                $hero->save();

                $reply_markup = json_encode([
                    'inline_keyboard' => [
                        [
                            [
                                'text' => 'Исполнено',
                                'callback_data' => json_encode([
                                    'isWishHandled' => true,
                                    'wishId' => $wish->id
                                ])
                            ],
                            [
                                'text' => 'Где мой герой?',
                                'callback_data' => json_encode([
                                    'isWishHandled' => false,
                                    'wishId' => $wish->id
                                ])
                            ]
                        ]
                    ]
                ]);

                $wishType = WishType::select('wish_text')->where('id', $wish->wish_type_id)->first()->wish_text;

                $telegram->sendPhoto([
                    'chat_id' => $wish->chat_id,
                    'caption' => "Твой герой уже спешит порадовать тебя!))) Твоё желание ($wishType) уже исполнено?",
                    'photo' => 'hero.jpg',
                    'reply_markup' => $reply_markup
                ]);

                $telegram->sendPhoto([
                    'chat_id' => $hero->chat_id,
                    'caption' => "А ты молодец :) Будешь стараться - получишь +1 в карму :)",
                    'photo' => 'hero.jpg'
                ]);
                die;
            }

            if (isset($data['answer']) && $data['answer'] == false) {
                $rejectedHeroesArray = WishUtils::updateRejectedHeroes($wish, $hero);
                WishUtils::executeWish($wish, $rejectedHeroesArray);
                die;
            }

            if (isset($data['isWishHandled']) && $data['isWishHandled'] == true) {
                $wish->handled = true;
                $wish->save();

                $hero->is_busy = false;
                $hero->proposed_wish_id = null;
                $hero->karma += 1;
                $hero->save();

                $telegram->sendPhoto([
                    'chat_id' => $wish->chat_id,
                    'photo' => 'http://grukhina.ru/images/attach/432/disney-mickey-mouse_resize2.jpg',
                    'caption' => 'А он хорош, не правда ли?)'
                ]);

                $telegram->sendPhoto([
                    'chat_id' => $hero->chat_id,
                    'photo' => 'https://24smi.org/public/media/2017/12/27/01_BTz233k.jpg',
                    'caption' => 'Молодец! Микки тобой доволен :)'
                ]);
                die;
            }

            if (isset($data['isWishHandled']) && $data['isWishHandled'] == false) {
                $hero_get_time = new DateTime($wish->hero_get_time);
                $interval = $hero_get_time->diff(new DateTime());
                if ($interval->format('%s') < 1) {
                    $reply_markup = json_encode([
                        'inline_keyboard' => [
                            [
                                [
                                    'text' => 'Оставить желание и ещё немного подождать',
                                    'callback_data' => json_encode([
                                        'isWishActual' => true,
                                        'wishId' => $wish->id
                                    ])
                                ],
                                [
                                    'text' => 'Отклонить желание',
                                    'callback_data' => json_encode([
                                        'isWishActive' => false,
                                        'wishId' => $wish->id
                                    ])
                                ]
                            ]
                        ]
                    ]);

                    $telegram->sendPhoto([
                        'chat_id' => $wish->chat_id,
                        'caption' => "Милые дамы! Просим Вас дать нам немного времени (~ 10 мин.), чтобы доставить вам немного радости :)",
                        'photo' => 'hero.jpg',
                        'reply_markup' => $reply_markup
                    ]);
                    die;
                }

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

                $telegram->sendMessage([
                    'chat_id' => $wish->chat_id,
                    'text' => "Тысяча извинений, милая леди! Ваше желание ещё в силе?",
                    'reply_markup' => $reply_markup
                ]);
                die;
            }

            if (isset($data['isWishActive']) && $data['isWishActive'] == true) {
                $hero->is_busy = false;
                $hero->proposed_wish_id = null;
                $hero->karma -= 1;
                $hero->save();

                $wish->hero_id = null;
                $wish->expired_at = null;
                $wish->save();

                $telegram->sendPhoto([
                    'chat_id' => $hero->chat_id,
                    'photo' => 'https://s00.yaplakal.com/pics/pics_original/1/9/6/10327691.jpg',
                    'caption' => 'Эх,ты! А она так ждала своего героя!)'
                ]);

                $telegram->sendPhoto([
                    'chat_id' => $wish->chat_id,
                    'photo' => 'http://funkot.ru/wp-content/uploads/2013/10/mein-Kittens-750.jpg',
                    'caption' => "Сейчас мы найдём тебе другого героя...\n А пока полюбуйся на мимимишных котиков :3 Мрррр..."
                ]);

                WishUtils::executeWish($wish, [$hero->id]);
                die;
            }

            if (isset($data['isWishActive']) && $data['isWishActive'] == false) {
                $hero->is_busy = false;
                $hero->proposed_wish_id = null;
                $hero->save();

                $wish->delete();

                $telegram->sendPhoto([
                    'chat_id' => $hero->chat_id,
                    'photo' => 'http://memesmix.net/media/created/zr9vya.jpg',
                    'caption' => 'Она сама отменила желание. Почему?'
                ]);

                $telegram->sendPhoto([
                    'chat_id' => $wish->chat_id,
                    'photo' => 'https://kulturologia.ru/files/u8921/cat-ani.jpg',
                    'caption' => "Котик сожалеет об этом :("
                ]);
                die;
            }

            if (isset($data['isWishActual']) && $data['isWishActual'] == true) {
                $telegram->sendPhoto([
                    'chat_id' => $wish->chat_id,
                    'photo' => 'http://yes.com.ru/image_load/news/news_day/image_562206120516282230782.jpg',
                    'caption' => "Мимимимимимимими :3"
                ]);
                die;
            }
        } catch (Exception $e) {
            Telegram::sendMessage([
                'chat_id' => env('DEVELOPER_CHAT_ID'),
                'text' => self::class . " handleCallback Error: {$e->getMessage()} (arguments: " . json_encode(func_get_args()) . ")"
            ]);
        }
    }
}