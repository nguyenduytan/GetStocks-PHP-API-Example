<?php
/*
 * GetStock.net Telegram Bot Webhook
 * by Tony Nguyễn
 * Telegram: @nguyenduytan
 *
 * Requirements:
 * 1. Install Composer: https://getcomposer.org/
 * 2. Run `composer require guzzlehttp/guzzle` to install Guzzle HTTP Client.
 * 3. Replace 'YOUR_TOKEN_HERE' with your GetStocks API token received via email.
 * 4. Replace 'YOUR_TELEGRAM_BOT_TOKEN' with your Telegram Bot token from @BotFather.
 * 5. Set this file as a webhook for your Telegram Bot: 
 *    https://api.telegram.org/bot<YOUR_TELEGRAM_BOT_TOKEN>/setWebhook?url=<YOUR_SERVER_URL>/getstock-webhook.php
 * 6. Ensure the server has write permissions for the 'temp' directory to store temporary files.
 */

require_once 'vendor/autoload.php';
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

// Maximum number of input links allowed
define('MAX_INPUT_LINKS', 5); // You can adjust this value as needed

// Telegram Bot Token
define('TELEGRAM_BOT_TOKEN', 'YOUR_TELEGRAM_BOT_TOKEN'); // Replace with your Telegram Bot token

// GetStocks API class
class GetStocksApiExample {
    private $client;
    private $token = 'YOUR_TOKEN_HERE'; // Replace with your GetStocks API token

    public function __construct() {
        $this->client = new Client([
            'base_uri' => 'https://getstocks.net/',
            'timeout'  => 30,
        ]);
    }

    public function getInfo($link, $ispre = 1) {
        if (empty($this->token)) return ['status' => 'error', 'message' => 'No token available'];
        try {
            $response = $this->client->post('/api/v1/getinfo', [
                'query' => ['token' => $this->token],
                'form_params' => ['link' => $link, 'ispre' => $ispre]
            ]);
            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function getLink($link, $ispre = 1, $type = null) {
        if (empty($this->token)) return ['status' => 'error', 'message' => 'No token available'];
        try {
            $response = $this->client->post('/api/v1/getlink', [
                'query' => ['token' => $this->token],
                'form_params' => ['link' => $link, 'ispre' => $ispre, 'type' => $type]
            ]);
            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function checkDownloadStatus($slug, $id, $ispre, $type) {
        if (empty($this->token)) return ['status' => 'error', 'message' => 'No token available'];
        try {
            $response = $this->client->post('/api/v1/download-status', [
                'query' => ['token' => $this->token],
                'form_params' => ['slug' => $slug, 'id' => $id, 'ispre' => $ispre, 'type' => $type]
            ]);
            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function getToken() {
        return $this->token;
    }
}

// Telegram Bot class
class TelegramBot {
    private $token;
    private $api;

    public function __construct($token) {
        $this->token = $token;
        $this->api = new Client(['base_uri' => "https://api.telegram.org/bot{$token}/"]);
    }

    public function sendMessage($chat_id, $text, $reply_markup = null) {
        $params = [
            'chat_id' => $chat_id,
            'text' => $text,
            'parse_mode' => 'HTML'
        ];
        if ($reply_markup) {
            $params['reply_markup'] = json_encode($reply_markup);
        }
        $this->api->post('sendMessage', ['form_params' => $params]);
    }
}

// Handle webhook from Telegram
$input = file_get_contents('php://input');
$update = json_decode($input, true);

$bot = new TelegramBot(TELEGRAM_BOT_TOKEN);
$api = new GetStocksApiExample();

if (isset($update['message'])) {
    $chat_id = $update['message']['chat']['id'];
    $username = $update['message']['chat']['first_name'] ?? 'User';
    $text = trim($update['message']['text']);

    // Create temp directory if it doesn't exist
    if (!file_exists('temp')) {
        mkdir('temp', 0777, true);
    }

    // Process input links
    $lines = array_filter(array_map('trim', explode("\n", $text)));
    $unique_lines = array_unique($lines); // Remove duplicate links
    $valid_lines = array_filter($unique_lines, function($line) {
        return filter_var($line, FILTER_VALIDATE_URL) !== false; // Keep only valid URLs
    });

    $line_count = count($valid_lines);

    // Check if number of links exceeds MAX_INPUT_LINKS
    if ($line_count > MAX_INPUT_LINKS) {
        $bot->sendMessage($chat_id, "Hi <b>{$username}</b>, you only can put <b>" . MAX_INPUT_LINKS . "</b> links here.");
        return;
    }

    if ($line_count === 1) {
        // Case: Single link
        $link = reset($valid_lines);
        $result = $api->getInfo($link);

        if ($result['status'] === 200 && isset($result['result']['support'])) {
            $support = $result['result']['support'];
            $message = "Hi <b>{$username}</b> (<code>{$chat_id}</code>).\n\nYou requesting download for <b>{$support['slug']}</b> with id: <b>{$support['id']}</b>\nPlease choose type to continue...";

            // Save link temporarily to a file
            $temp_file = "temp/{$chat_id}_" . time() . ".txt";
            file_put_contents($temp_file, json_encode(['link' => $link, 'ispre' => $support['ispre']]));

            // Create inline keyboard from support->type
            $inline_keyboard = [];
            foreach ($support['type'] as $type_key => $type_label) {
                $inline_keyboard[] = [['text' => $type_label, 'callback_data' => "type_{$type_key}_{$temp_file}"]];
            }

            $bot->sendMessage($chat_id, $message, ['inline_keyboard' => $inline_keyboard]);
        } else {
            $bot->sendMessage($chat_id, "Sorry, unable to get info for this link: " . ($result['message'] ?? 'Unknown error'));
        }
    } elseif ($line_count > 1) {
        // Case: Multiple links
        foreach ($valid_lines as $line) {
            $result = $api->getLink($line, 1, null); // ispre = 1, type = null
            if ($result['status'] === 200 && isset($result['result'])) {
                $getLinkResult = $result['result'];
                $bot->sendMessage($chat_id, "BOT is processing your download <b>{$getLinkResult['provSlug']}</b> with id: <b>{$getLinkResult['itemID']}</b>. Please wait...");

                // Check download status
                $start_time = time();
                while (true) {
                    if (time() - $start_time > 60) {
                        $bot->sendMessage($chat_id, "Sorry, link with provider <b>{$getLinkResult['provSlug']}</b> with id <b>{$getLinkResult['itemID']}</b> can't download now! (Timeout)");
                        break;
                    }

                    $status = $api->checkDownloadStatus($getLinkResult['provSlug'], $getLinkResult['itemID'], $getLinkResult['isPremium'], $getLinkResult['itemType']);
                    if ($status['status'] === 200 && $status['result']['status'] === 1) {
                        $result = $status['result'];
                        $downloadLink = "https://getstocks.net/api/v1/download/{$result['itemDCode']}?token=" . $api->getToken();
                        $message = "Your file is ready:\n\n- Provider: <b>{$result['provSlug']}</b>\n- ID: <b>{$result['itemID']}</b>\n- Filename: <b>{$result['itemFilename']}</b>\n- Size: <b>{$result['itemSize']}</b>\n\n";
                        $bot->sendMessage($chat_id, $message, [
                            'inline_keyboard' => [[['text' => 'Download', 'url' => $downloadLink]]]
                        ]);
                        break;
                    } elseif ($status['status'] !== 200) {
                        $bot->sendMessage($chat_id, "Sorry, link with provider <b>{$getLinkResult['provSlug']}</b> with id <b>{$getLinkResult['itemID']}</b> can't download now! ({$status['message']})");
                        break;
                    }
                    sleep(10);
                }
            } else {
                $bot->sendMessage($chat_id, "Sorry, unable to process link: {$line} - " . ($result['message'] ?? 'Unknown error'));
            }
        }
    }
}

// Handle callback from inline keyboard
if (isset($update['callback_query'])) {
    $callback = $update['callback_query'];
    $chat_id = $callback['message']['chat']['id'];
    $data = $callback['data'];

    if (strpos($data, 'type_') === 0) {
        list(, $type, $temp_file) = explode('_', $data, 3);
        $temp_data = json_decode(file_get_contents($temp_file), true);
        $link = $temp_data['link'];
        $ispre = $temp_data['ispre'];

        $result = $api->getLink($link, $ispre, $type);
        if ($result['status'] === 200 && isset($result['result'])) {
            $getLinkResult = $result['result'];
            $bot->sendMessage($chat_id, "BOT is processing your download <b>{$getLinkResult['provSlug']}</b> with id: <b>{$getLinkResult['itemID']}</b>. Please wait...");

            // Check download status
            $start_time = time();
            while (true) {
                if (time() - $start_time > 60) {
                    $bot->sendMessage($chat_id, "Sorry, link with provider <b>{$getLinkResult['provSlug']}</b> with id <b>{$getLinkResult['itemID']}</b> can't download now! (Timeout)");
                    break;
                }

                $status = $api->checkDownloadStatus($getLinkResult['provSlug'], $getLinkResult['itemID'], $getLinkResult['isPremium'], $getLinkResult['itemType']);
                if ($status['status'] === 200 && $status['result']['status'] === 1) {
                    $result = $status['result'];
                    $downloadLink = "https://getstocks.net/api/v1/download/{$result['itemDCode']}?token=" . $api->getToken();
                    $message = "Your file is ready:\n\n- Provider: <b>{$result['provSlug']}</b>\n- ID: <b>{$result['itemID']}</b>\n- Filename: <b>{$result['itemFilename']}</b>\n- Size: <b>{$result['itemSize']}</b>\n\n";
                    $bot->sendMessage($chat_id, $message, [
                        'inline_keyboard' => [[['text' => 'Download', 'url' => $downloadLink]]]
                    ]);
                    break;
                } elseif ($status['status'] !== 200) {
                    $bot->sendMessage($chat_id, "Sorry, link with provider <b>{$getLinkResult['provSlug']}</b> with id <b>{$getLinkResult['itemID']}</b> can't download now! ({$status['message']})");
                    break;
                }
                sleep(10);
            }
        } else {
            $bot->sendMessage($chat_id, "Error processing download: " . ($result['message'] ?? 'Unknown error'));
        }

        // Remove temporary file after processing
        if (file_exists($temp_file)) {
            unlink($temp_file);
        }
    }
}
?>