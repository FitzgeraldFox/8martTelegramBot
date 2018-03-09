<?php

namespace App\Http\Controllers;

use App\Commands\Wishes\HeroChoiceCommand;
use App\Models\Hero;
use App\Models\Wish;
use App\Utils\CallbackUtils;
use DateTime;
use Exception;
use Illuminate\Support\Facades\DB;
use Telegram;
use Telegram\Bot\Api;

class TelegramController extends Controller
{
    public function getWebhookUpdates()
    {
        $update = Telegram::commandsHandler(true);

        if (!empty($update['callback_query'])) {
            CallbackUtils::handleCallback($update);
        }

        return 'ok';

        $telegram = new Api(env('TELEGRAM_BOT_TOKEN'));
        $requestArray = $telegram->getWebhookUpdates();

        $wishes = [
            [
                'text' => 'Принесите мне Волшебный чай Алладина!. И печенюшек повкусней :)',
                'realWishText' => 'Волшебный чай Алладина!. И печенюшек повкусней :)'
            ],
            [
                'text' => 'Желаю Магический кофе Царицы Клеопатры с привкусом тайн и пирамид',
                'realWishText' => 'Магический кофе Царицы Клеопатры с привкусом тайн и пирамид'
            ],
            ['text' => 'Хочу Обнимашки!)))', 'realWishText' => 'Обнимашки!)))']
        ];

        $commands = [
            '/менюгероя',
            '/статьгероем',
            '/геройспит',
            '/геройпроснулся',
            '/сделал',
            '/менюдам',
            '/гдемойгерой',
            '/start',
        ];

        $fun_pics = [
            'http://atkritka.com/upload/iblock/d0f/atkritka_1302175977_505.jpg',
            'http://atkritka.com/upload/iblock/188/atkritka_1350571045_877.jpg',
            'http://www.povarenok.ru/data/cache/2013oct/21/16/539581_25547-640x0.jpg'
        ];

        if (!empty($requestArray['callback_query'])) {
            $data = json_decode($requestArray['callback_query']['data'], true);
            $heroChatId = $requestArray['callback_query']['message']['chat']['id'];
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
                        'chat_id' => $wish->user_id,
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

                    foreach ($wishes as $wishItem) {
                        if ($wishItem['text'] == $wish->wish_type) {
                            $realWishText = $wishItem['realWishText'];
                            break;
                        }
                    }

                    self::executeWish($telegram, $requestArray, $realWishText, $wish->user_id, $wish,
                        $rejectedHeroesArray);
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
                            'chat_id' => $wish->user_id,
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
                            'chat_id' => $wish->user_id,
                            'text' => 'Не повторяется такое никогда...'
                        ]);
                    }
                    die;
                }
                if (isset($data['isWishHandled']) && $data['isWishHandled'] == false) {
                    $wish = Wish::find($data['wishId']);

                    if (!empty($wish) && $wish->handled != true) {
                        $hero_get_time = new DateTime($wish->hero_get_time);
                        $current_time = new DateTime();
                        $interval = $hero_get_time->diff($current_time);
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
                                'chat_id' => $wish->user_id,
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
                            'chat_id' => $wish->user_id,
                            'text' => "Тысяча извинений, милая леди! Ваше желание ещё в силе?",
                            'reply_markup' => $reply_markup
                        ]);
                    } else {
                        $telegram->sendMessage([
                            'chat_id' => $wish->user_id,
                            'text' => 'Не повторяется такое никогда...'
                        ]);
                    }
                    die;
                }
                if (isset($data['isWishActive']) && $data['isWishActive'] == true) {
                    $chatId = $requestArray['callback_query']['message']['chat']['id'];
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

                        $realWishText = '';
                        foreach ($wishes as $wishArrayItem) {
                            if ($wish->wish_type == $wishArrayItem['text']) {
                                $realWishText = $wishArrayItem['realWishText'];
                                break;
                            }
                        }

                        self::executeWish($telegram, $requestArray, $realWishText, $chatId, $wish, [$hero->id]);
                    } else {
                        $telegram->sendMessage([
                            'chat_id' => $chatId,
                            'text' => 'Не повторяется такое никогда...'
                        ]);
                    }
                    die;
                }
                if (isset($data['isWishActive']) && $data['isWishActive'] == false) {
                    $chatId = $requestArray['callback_query']['message']['chat']['id'];
                    $wish = Wish::find($data['wishId']);

                    if (!empty($wish) && $wish->handled != true) {
                        $wish->delete();
                        $hero = Hero::find($wish->hero_id);
                        $hero->is_busy = false;
                        $hero->save();

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
                    $chatId = $requestArray['callback_query']['message']['chat']['id'];
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
        } else {
            $chatId = $requestArray["message"]["chat"]["id"]; //Уникальный идентификатор пользователя

            self::getHeroName($chatId, $requestArray);

            if ((int)time() < 1520424000) {
                try {
                    if (!in_array($requestArray["message"]["text"], $commands)) {
                        $isWishCorrect = false;
                        if (!empty($requestArray["message"]["text"]) && strlen($requestArray["message"]["text"]) > 1) {
                            foreach ($wishes as $wish) {
                                if ($requestArray["message"]["text"] == $wish['text']) {
                                    $isWishCorrect = true;
                                    $wishType = $requestArray["message"]["text"]; //Текст сообщения
                                    break;
                                }
                            }
                        } else {
                            $telegram->sendMessage([
                                'chat_id' => $chatId,
                                'text' => 'Среди нас телепатов нет :)',
                            ]);
                            die;
                        }

                        if (!$isWishCorrect) {
                            $telegram->sendMessage([
                                'chat_id' => $chatId,
                                'text' => "Твоя фантазия не знает границ! \nТолкиен нервно курит в сторонке :)))",
                            ]);
                            die;
                        }

                        foreach ($wishes as $wish) {
                            if ($wishType == $wish['text']) {
                                $realWishText = $wish['realWishText'];
                            }
                        }
                    } else {
                        $wishType = $requestArray["message"]["text"];
                    }

                    $keyboard = [];
                    foreach ($wishes as $wish) {
                        $keyboard[] = [$wish['text']];
                    }

                    $replyKeyboard = $telegram->replyKeyboardMarkup([
                        'keyboard' => $keyboard,
                        'resize_keyboard' => true,
                        'one_time_keyboard' => false
                    ]);

                    switch ($wishType) {
                        case '/менюгероя':
                            $telegram->sendMessage([
                                'chat_id' => $chatId,
                                'text' => "Ты можешь делать три вещи, Ковбой:\n- Совершать подвиги(/статьгероем);\n- Спать в багажнике(/геройспит)\n- Поднять зад и начать действовать!!1(/геройпроснулся)\nИ помни: \"Большая сила - большая ответственность...\""
                            ]);
                            break;
                        case '/статьгероем':
                            if (empty(Hero::where('chat_id', $chatId)->first())) {
                                $reply_markup = $telegram->replyKeyboardHide();
                                $hero = new Hero;
                                $hero->chat_id = $chatId;
                                $hero->save();
                                $telegram->sendMessage([
                                    'chat_id' => $chatId,
                                    'text' => "Ты стал героем для наших прекрасных дам. \nВперёд, завоёвывать их сердца!)))",
                                    'reply_markup' => $reply_markup
                                ]);
                            } else {
                                $telegram->sendMessage([
                                    'chat_id' => $chatId,
                                    'text' => 'Ты уже в отряде супергероев, малец!'
                                ]);
                            }
                            die;
                            break;
                        case '/геройспит':
                            $hero = Hero::where('chat_id', $chatId)->first();
                            if (!empty($hero)) {
                                if ($hero->is_busy) {
                                    $telegram->sendMessage([
                                        'chat_id' => $chatId,
                                        'text' => 'Ты не можешь уснуть на ходу :) Для справки набери /менюгероя'
                                    ]);
                                    die;
                                }
                                Hero::where('chat_id', $chatId)->update([
                                    'active' => false
                                ]);
                                $telegram->sendMessage([
                                    'chat_id' => $chatId,
                                    'text' => "Ты отошёл от дел и начал вести спокойную мирную жизнь на ферме где-то в штате Техас. \nКогда-нибудь ты вернёшься, чтобы снова спасти Человечество..."
                                ]);
                            } else {
                                $telegram->sendMessage([
                                    'chat_id' => $chatId,
                                    'text' => 'Сначала зарегайся: /статьгероем (Для справки набери /менюгероя)'
                                ]);
                            }
                            die;
                            break;
                        case '/геройпроснулся':
                            $hero = Hero::where('chat_id', $chatId)->first();
                            if (!empty($hero)) {
                                if ($hero->is_busy) {
                                    $telegram->sendMessage([
                                        'chat_id' => $chatId,
                                        'text' => 'Ты уже бодрствуешь :) (Для справки набери /менюгероя)'
                                    ]);
                                    die;
                                }
                                Hero::where('chat_id', $chatId)->update([
                                    'active' => true
                                ]);
                                $telegram->sendMessage([
                                    'chat_id' => $chatId,
                                    'text' => 'Настал Твой час доставить удовольствие нашим прекрасным дамам! Тададада-тадааа! (Для справки набери /менюгероя)'
                                ]);
                            } else {
                                $telegram->sendMessage([
                                    'chat_id' => $chatId,
                                    'text' => 'Сначала зарегайся: /статьгероем. (Для справки набери /менюгероя)'
                                ]);
                            }
                            die;
                            break;
                        case '/менюдам':
                            $telegram->sendMessage([
                                'chat_id' => $chatId,
                                'text' => "Милая дама! \nМы подготовили для тебя восхитительные приятности! Выбери одно из лакомств в главном меню, и наш герой доставит его тебе в течении 10 минут! Если твой герой проспал, то можешь нажать кнопку \"Где мой герой?\", где ты должна определиться, хочешь ли ты другого героя или отменить желание. Надеемся, тебе понравится :) \nИ помни: будешь есть много сладкого... \nОбнимашки и комплименты не в счёт :)))"
                            ]);
                            die;
                            break;
                        case '/start':
                            $telegram->sendMessage([
                                'chat_id' => $chatId,
                                'text' => "Привет, {$requestArray["message"]["from"]["first_name"]}! Этот супергеройский бот создан, чтобы сделать тебя немного счастливее и подарить тебе море положительных эмоций!))) \nЧтобы узнать правила игры, \nнабери /менюдам. Чего пожелаешь?)",
                                'reply_markup' => $replyKeyboard
                            ]);
                            die;
                            break;
                        default:
                            if (empty(Hero::where('chat_id', $chatId)->first())) {
                                if (empty(Wish::where([
                                    'handled' => 0,
                                    'user_id' => $chatId
                                ])->first())) {
                                    $userWish = Wish::where([
                                        'user_id' => $requestArray['message']['from']['id'],
                                        'wish_type' => $wishType
                                    ])->first();

                                    if (!empty($userWish)) {
                                        if ($wishType != $wishes[2]['text']) {
                                            $telegram->sendPhoto([
                                                'chat_id' => $chatId,
                                                'photo' => $fun_pics[mt_rand(0, count($fun_pics) - 1)],
                                            ]);
                                            die;
                                        } else {
                                            $userWish->wish_count += 1;
                                            $userWish->save();
                                        }
                                    } else {
                                        $userWish = new Wish;
                                        $userWish->user_id = $requestArray['message']['from']['id'];
                                        $userWish->wish_type = $wishType;
                                        $userWish->save();
                                    }

                                    $realWishText = '';
                                    foreach ($wishes as $wish) {
                                        if ($wishType == $wish['text']) {
                                            $realWishText = $wish['realWishText'];
                                            break;
                                        }
                                    }

                                    self::executeWish($telegram, $requestArray, $realWishText, $chatId, $userWish);
                                } else {
                                    $telegram->sendMessage([
                                        'chat_id' => $chatId,
                                        'text' => "Сначала чай, потом - кофе ;)"
                                    ]);
                                }
                            } else {
                                $telegram->sendMessage([
                                    'chat_id' => $chatId,
                                    'text' => 'Ах ты Шалун!)'
                                ]);
                            }
                    }
                } catch (Exception $e) {
                    $telegram->sendMessage([
                        'chat_id' => 244460280,
                        'text' => $e->getMessage()
                    ]);
                    die;
                }
            } else {
                $telegram->sendPhoto([
                    'chat_id' => $chatId,
                    'caption' => 'Даже супергероям нужен отдых :)',
                    'photo' => 'https://thefancy-media-ec2.thefancy.com/original/20130720/410793053037532055_66521055a244.jpg'
                ]);
                die;
            }
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

    public static function getHeroName($chatId, $requestArray)
    {
        $hero = Hero::where('chat_id', $chatId)->first();
        if (!empty($hero) && empty($hero->name)) {
            $hero->name = $requestArray['message']['chat']['first_name'] . ' ' . $requestArray['message']['chat']['last_name'];
            $hero->save();
        }
    }
}