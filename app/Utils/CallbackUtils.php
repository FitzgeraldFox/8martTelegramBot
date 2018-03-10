<?php

namespace App\Utils;


use App\Models\Hero;
use App\Models\Wish;
use DateTime;
use Exception;
use Telegram\Bot\Api;

class CallbackUtils
{
    public static function handleCallback($update)
    {
        $telegram = new Api(env('TELEGRAM_BOT_TOKEN'));
        $data = json_decode($update['callback_query']['data'], true);
        $heroChatId = $update['callback_query']['message']['chat']['id'];
        $hero = Hero::where([
            'chat_id' => $heroChatId
        ])->first();

        try {
            if (isset($data['answer']) && $data['answer'] == true) {

                $wish = Wish::find($data['wishId']);
                if (empty($wish)) {
                    $telegram->sendMessage([
                        'chat_id' => $heroChatId,
                        'text' => 'Не повторяется такое никогда...'
                    ]);
                    die;
                }
                $wish->hero_id = Hero::select('id')->where('chat_id', $heroChatId)->first()->id;
                $wish->hero_get_time = new \DateTime();
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

                $telegram->sendPhoto([
                    'chat_id' => $wish->chat_id,
                    'caption' => "Твой герой уже спешит порадовать тебя!))) Твоё желание уже исполнено?",
                    'photo' => 'hero.jpg',
                    'reply_markup' => $reply_markup
                ]);

                $telegram->sendPhoto([
                    'chat_id' => $heroChatId,
                    'caption' => "А ты молодец :) Будешь стараться - получишь +1 в карму :)",
                    'photo' => 'hero.jpg'
                ]);
                die;
            }

            if (isset($data['answer']) && $data['answer'] == false) {
                $wish = Wish::find($data['wishId']);
                if (empty($wish)) {
                    $telegram->sendMessage([
                        'chat_id' => $heroChatId,
                        'text' => 'Не повторяется такое никогда...'
                    ]);
                    die;
                }
                $rejectedHeroesArray = explode(',',
                    Wish::select('rejected_heroes')->where('id', $data['wishId'])->first()->rejected_heroes);
                if (!in_array($hero->id, $rejectedHeroesArray)) {
                    $rejectedHeroesArray[] = $hero->id;
                }

                $wish->rejected_heroes = implode(',', $rejectedHeroesArray);
                $wish->save();

                WishUtils::executeWish($update, $heroChatId, $wish, $rejectedHeroesArray);
                die;
            }

            if (isset($data['isWishHandled']) && $data['isWishHandled'] == true) {
                $wish = Wish::find($data['wishId']);

                if (!empty($wish) && $wish->handled != true) {
                    $wish->handled = true;
                    $wish->save();

                    $hero = Hero::find($wish->hero_id);
                    $hero->is_busy = false;
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
                } else {
                    $telegram->sendMessage([
                        'chat_id' => $wish->chat_id,
                        'text' => 'Не повторяется такое никогда...'
                    ]);
                }
                die;
            }

            if (isset($data['isWishHandled']) && $data['isWishHandled'] == false) {
                $wish = Wish::find($data['wishId']);

                if (!empty($wish) && $wish->handled != true) {
                    $hero_get_time = new DateTime($wish->hero_get_time);
                    $interval = $hero_get_time->diff(new DateTime());
                    if ($interval->format('%i') < 10) {
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
                                            'herChoice' => true,
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
                } else {
                    $telegram->sendMessage([
                        'chat_id' => $wish->chat_id,
                        'text' => 'Не повторяется такое никогда...'
                    ]);
                }
                die;
            }

            if (isset($data['isWishActive']) && $data['isWishActive'] == true) {

                $chatId = $update['callback_query']['message']['chat']['id'];
                $wish = Wish::find($data['wishId']);

                if (!empty($wish) && $wish->handled != true) {
                    $hero = Hero::find($wish->hero_id);
                    $hero->is_busy = false;
                    $hero->karma -= 1;
                    $hero->save();

                    $telegram->sendPhoto([
                        'chat_id' => $hero->chat_id,
                        'photo' => 'https://s00.yaplakal.com/pics/pics_original/1/9/6/10327691.jpg',
                        'caption' => 'Эх,ты! А она так ждала своего героя!)'
                    ]);

                    $telegram->sendPhoto([
                        'chat_id' => $chatId,
                        'photo' => 'http://funkot.ru/wp-content/uploads/2013/10/mein-Kittens-750.jpg',
                        'caption' => "Сейчас мы найдём тебе другого героя...\n А пока полюбуйся на мимимишных котиков :3 Мрррр..."
                    ]);

                    WishUtils::executeWish($update, $chatId, $wish, [$hero->id]);
                } else {
                    $telegram->sendMessage([
                        'chat_id' => $chatId,
                        'text' => 'Не повторяется такое никогда...'
                    ]);
                }
                die;
            }

            if (isset($data['isWishActive']) && $data['isWishActive'] == false) {

                $chatId = $update['callback_query']['message']['chat']['id'];
                $wish = Wish::find($data['wishId']);

                if (!empty($wish) && $wish->handled != true) {
                    $hero = Hero::find($wish->hero_id);
                    $hero->is_busy = false;
                    $hero->save();

                    if ($wish->wish_count > 1) {
                        $wish->wish_count -= 1;
                        $wish->handled = true;
                        $wish->rejected_heroes = '';
                        $wish->save();
                    } else {
                        $wish->delete();
                    }

                    if (!empty($data['herChoice'])) {
                        $telegram->sendPhoto([
                            'chat_id' => $hero->chat_id,
                            'photo' => 'http://memesmix.net/media/created/zr9vya.jpg',
                            'caption' => 'Она сама отменила желание. Почему?'
                        ]);
                    } else {
                        $telegram->sendPhoto([
                            'chat_id' => $hero->chat_id,
                            'photo' => 'https://s00.yaplakal.com/pics/pics_original/1/9/6/10327691.jpg',
                            'caption' => 'Эх,ты! А она так ждала своего героя!)'
                        ]);
                    }

                    $telegram->sendPhoto([
                        'chat_id' => $chatId,
                        'photo' => 'https://kulturologia.ru/files/u8921/cat-ani.jpg',
                        'caption' => "Котик сожалеет об этом :("
                    ]);
                } else {
                    $telegram->sendMessage([
                        'chat_id' => $chatId,
                        'text' => 'Не повторяется такое никогда...'
                    ]);
                }
                die;
            }

            if (isset($data['isWishActual']) && $data['isWishActual'] == true) {
                $chatId = $update['callback_query']['message']['chat']['id'];
                $wish = Wish::find($data['wishId']);

                if (!empty($wish) && $wish->handled != true) {
                    $telegram->sendPhoto([
                        'chat_id' => $chatId,
                        'photo' => 'http://yes.com.ru/image_load/news/news_day/image_562206120516282230782.jpg',
                        'caption' => "Мимимимимимимими :3"
                    ]);
                } else {
                    $telegram->sendMessage([
                        'chat_id' => $chatId,
                        'text' => 'Не повторяется такое никогда...'
                    ]);
                }
                die;
            }
        } catch (Exception $e) {
            $telegram->sendMessage([
                'chat_id' => 244460280,
                'text' => 'Error: ' . $e->getMessage(),
            ]);
            die;
        }
    }
}