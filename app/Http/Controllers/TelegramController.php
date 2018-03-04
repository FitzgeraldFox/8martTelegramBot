<?php
namespace App\Http\Controllers;

use App\Models\Hero;
use App\Models\Wish;
use Exception;
use Telegram\Bot\Api;

class TelegramController extends Controller
{
    public function getWebhookUpdates()
    {
        $telegram = new Api(env('TELEGRAM_BOT_TOKEN'));
        $requestArray = $telegram->getWebhookUpdates();

        $wishes = [
            ['text' => 'Принесите мне Волшебный чай Алладина!. И печенюшек повкусней :)', 'realWishText' => 'Волшебный чай Алладина!. И печенюшек повкусней :)'],
            ['text' => 'Желаю Магический кофе Царицы Клеопатры с привкусом тайн и пирамид', 'realWishText' => 'Магический кофе Царицы Клеопатры с привкусом тайн и пирамид'],
            ['text' => 'Хочу Обнимашки!)))', 'realWishText' => 'Обнимашки!)))']
        ];

        $fun_pics = [
            'http://atkritka.com/upload/iblock/d0f/atkritka_1302175977_505.jpg',
            'http://atkritka.com/upload/iblock/188/atkritka_1350571045_877.jpg',
            'http://www.povarenok.ru/data/cache/2013oct/21/16/539581_25547-640x0.jpg'
        ];

        $chatId = $requestArray["message"]["chat"]["id"]; //Уникальный идентификатор пользователя

        if (!empty($requestArray["message"]["text"])) {
            $wishType = $requestArray["message"]["text"]; //Текст сообщения
        } else {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => 'Ты втираешь какую-то дичь :)))',
            ]);
            return;
        }

        if ((int)time() < 1520424000) {
            $realWishText = '';
            $keyboard = [];
            foreach ($wishes as $wish) {
                $keyboard[] = [$wish['text']];
                if ($wishType == $wish['text']) {
                    $realWishText = $wish['realWishText'];
                }
            }

            $replyKeyboard = $telegram->replyKeyboardMarkup([
                'keyboard' => $keyboard,
                'resize_keyboard' => true,
                'one_time_keyboard' => false
            ]);

            switch ($wishType) {
                case '/статьгероем':
                    if (empty(Hero::where('chat_id', $chatId)->first())) {
                        $hero = new Hero;
                        $hero->chat_id = $chatId;
                        $hero->save();
                        $telegram->sendMessage([
                            'chat_id' => $chatId,
                            'text'=> 'Ты стал героем для наших прекрасных дам. Вперёд, завоёвывать их сердца!)))'
                        ]);
                    } else {
                        $telegram->sendMessage([
                            'chat_id' => $chatId,
                            'text'=> 'Ты уже в отряде супергероев, малец!'
                        ]);
                    }
                    die;
                    break;
                case '/геройспит':
                    Hero::where('chat_id', $chatId)->update([
                        'active' => false
                    ]);
                    $telegram->sendMessage([
                        'chat_id' => $chatId,
                        'text'=> 'Ты отошёл от дел и начал вести спокойную мирную жизнь на ферме где-то в штате Техас. Когда-нибудь ты вернёшься, чтобы снова спасти Человечество...'
                    ]);
                    die;
                    break;
                case '/геройпроснулся':
                    Hero::where('chat_id', $chatId)->update([
                        'active' => true
                    ]);
                    $telegram->sendMessage([
                        'chat_id' => $chatId,
                        'text'=> 'Настал Твой час доставить удовольствие нашим прекрасным дамам! Тададада-тадааа!'
                    ]);
                    die;
                    break;
                case '/start':
                    $telegram->sendMessage([
                        'chat_id' => $chatId,
                        'text' => 'Привет, ' . $requestArray["message"]["from"]["first_name"] . '! Этот супергеройский бот создан, чтобы сделать тебя немного счастливее и подарить тебе море положительных эмоций!))) Чего пожелаешь?)',
                        'reply_markup' => $replyKeyboard
                    ]);
                    die;
                    break;
                default:
                    $userWish = Wish::where([
                        'user_id' => $requestArray['message']['from']['id'],
                        'wish_type' => $wishType
                    ])->first();

                    if (!empty($userWish)) {
                        if ($wishType != $wishes[2]['text']) {
                            $telegram->sendPhoto([
                                'chat_id' => $chatId,
                                'photo' => $fun_pics[mt_rand(0, count($fun_pics))],
                            ]);
                            die;
                        }
                    } else {
                        $userWish = new Wish;
                        $userWish->user_id = $requestArray['message']['from']['id'];
                        $userWish->wish_type = $wishType;
                        $userWish->save();
                    }

                    self::executeWish($telegram, $requestArray, $realWishText, $chatId, $userWish);
            }
        } else {
            $telegram->sendPhoto([
                'chat_id' => $chatId,
                'caption' => 'Даже супергероям нужен отдых :)',
                'photo' => 'https://thefancy-media-ec2.thefancy.com/original/20130720/410793053037532055_66521055a244.jpg'
            ]);
        }
    }
    
    public static function executeWish($telegram, $requestArray, $realWishText, $chatId, $wish)
    {
        try {
            $heroes = Hero::where('active', true)->get()->toArray();
            $rand_hero_number = mt_rand(0, count($heroes) - 1);
            $telegram->sendMessage([
                'chat_id' => $heroes[$rand_hero_number]['chat_id'],
                'text' => "{$requestArray["message"]["from"]["first_name"]} {$requestArray["message"]["from"]["last_name"]} хочет, чтобы ты принёс ей $realWishText. Это твой шанс, парень!",
            ]);

            $wish->hero_id = $heroes[$rand_hero_number]['id'];
            $wish->save();

            $telegram->sendPhoto([
                'chat_id' => $chatId,
                'caption' => "Ваш герой уже спешит к Вам!",
                'photo' => 'https://im0-tub-ru.yandex.net/i?id=26e44b90edeb66d197257a772c907b97&n=13'
            ]);
        } catch (Exception $e) {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => $e->getMessage(),
            ]);
        }
    }
}