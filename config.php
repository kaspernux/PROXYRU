<?php

date_default_timezone_set('Europe/Moscow');
error_reporting(E_ALL ^ E_NOTICE);

$config = ['version' => '2.5', 'domain' => 'https://' . $_SERVER['HTTP_HOST'] . '/' . explode('/', explode('html/', $_SERVER['SCRIPT_FILENAME'])[1])[0], 'token' => '[*TOKEN*]', 'dev' => '[*DEV*]', 'database' => ['db_name' => '[*DB-NAME*]', 'db_username' => '[*DB-USER*]', 'db_password' => '[*DB-PASS*]']];

$sql = new mysqli('localhost', $config['database']['db_username'], $config['database']['db_password'], $config['database']['db_name']);
$sql->set_charset("utf8mb4");

if ($sql->connect_error) {
    die(json_encode(['status' => false, 'msg' => $sql->connect_error, 'error' => 'database'], 423));
}

define('API_KEY', $config['token']);

if (file_exists('texts.json')) $texts = json_decode(file_get_contents('texts.json'), true);
# ----------------- [ <- variables -> ] ----------------- #

$update = json_decode(file_get_contents('php://input'));

if (isset($update->message)) {
    $message_id = $update->message->message_id;
    $first_name = isset($update->message->from->first_name) ? $update->message->from->first_name : '❌';
    $username = isset($update->message->from->username) ? '@' . $update->message->from->username : '❌';
    $from_id = $update->message->from->id;
    $chat_id = $update->message->chat->id;
    $text = $update->message->text;
} elseif (isset($update->callback_query)) {
    $from_id = $update->callback_query->from->id;
    $data = $update->callback_query->data;
    $query_id = $update->callback_query->id;
    $message_id = $update->callback_query->message->message_id;
    $username = isset($update->callback_query->from->username) ? '@' . $update->callback_query->from->username : "нет";
}

# ----------------- [ <- others -> ] ----------------- #

if (!isset($sql->connect_error)) {
    if ($sql->query("SHOW TABLES LIKE 'users'")->num_rows > 0 and $sql->query("SHOW TABLES LIKE 'admins'")->num_rows > 0 and $sql->query("SHOW TABLES LIKE 'test_account_setting'")->num_rows > 0) {
        if (isset($update)) {
            $user = $sql->query("SELECT * FROM `users` WHERE `from_id` = '$from_id' LIMIT 1");
            if ($user->num_rows == 0) {
                $sql->query("INSERT INTO `users`(`from_id`) VALUES ('$from_id')");
            }
            
            $test_account = $sql->query("SELECT * FROM `test_account_setting`");
            $payment_setting = $sql->query("SELECT * FROM `payment_setting`");
            $spam_setting = $sql->query("SELECT * FROM `spam_setting`");
            $auth_setting = $sql->query("SELECT * FROM `auth_setting`");
            $settings = $sql->query("SELECT * FROM `settings`");
            # ------------------------------------------------- #
            $test_account_setting = $test_account->fetch_assoc();
            $payment_setting = $payment_setting->fetch_assoc();
            $spam_setting = $spam_setting->fetch_assoc();
            $auth_setting = $auth_setting->fetch_assoc();
            $settings = $settings->fetch_assoc();
            $user = $user->fetch_assoc();
        }
    }
}


# ----------------- [ <- functions -> ] ----------------- #

function bot($method, $datas = []) {
    $url = "https://api.telegram.org/bot" . API_KEY . "/" . $method;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => $datas
    ]);
    $res = curl_exec($ch);
    if ($res === false) {
        error_log('cURL Error: ' . curl_error($ch));
    } else {
        return json_decode($res);
    }
    curl_close($ch);
}

function sendMessage($chat_id, $text, $keyboard = null, $mrk = 'html') {
    $params = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => $mrk,
        'disable_web_page_preview' => true,
        'reply_markup' => $keyboard
    ];
    return bot('sendMessage', $params);
}

function forwardMessage($from, $to, $message_id, $mrk = 'html') {
    $params = [
        'chat_id' => $to,
        'from_chat_id' => $from,
        'message_id' => $message_id,
        'parse_mode' => $mrk
    ];
    return bot('forwardMessage', $params);
}

function editMessage($chat_id, $text, $message_id, $keyboard = null, $mrk = 'html') {
    $params = [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => '⏳',
    ];
    bot('editMessageText', $params);
    
    $params = [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => $text,
        'parse_mode' => $mrk,
        'disable_web_page_preview' => true,
        'reply_markup' => $keyboard
    ];
    return bot('editMessageText', $params);
}

function deleteMessage($chat_id, $message_id) {
    $params = [
        'chat_id' => $chat_id,
        'message_id' => $message_id
    ];
    return bot('deleteMessage', $params);
}

function alert($text, $show = true) {
    global $query_id;
    $params = [
        'callback_query_id' => $query_id,
        'text' => $text,
        'show_alert' => $show
    ];
    return bot('answerCallbackQuery', $params);
}

function step($step) {
    global $sql, $from_id;
    $sql->query("UPDATE `users` SET `step` = '$step' WHERE `from_id` = '$from_id'");
}

function checkURL($url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => 1, CURLOPT_TIMEOUT => 10]);
    $output = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $httpcode;
}

function Conversion($byte, $one = 'GB') {
    if (isset($one)) {
        if ($one == 'GB') {
            $limit = floor($byte / 1048576);
        } elseif ($one == 'MB') {
            $limit = floor($byte / 1024);
        } elseif ($one == 'KB') {
            $limit = floor($byte);
        }
    }
    return $limit;
}

function convertToBytes($from) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
    $number = substr($from, 0, -2);
    $suffix = strtoupper(substr($from,-2));

    if(is_numeric(substr($suffix, 0, 1))) {
        return preg_replace('/[^\d]/', '', $from);
    }

    $exponent = array_flip($units)[$suffix] ?? null;
    if($exponent === null) {
        return null;
    }

    return $number * (1024 ** $exponent);
}

function isJoin($from_id) {
    global $sql;
    $lockSQL = $sql->query("SELECT `chat_id` FROM `lock`");
    if ($lockSQL->num_rows > 0) {
        $result = [];
        while ($id = $lockSQL->fetch_assoc()) {
            $status = bot('getChatMember', ['chat_id' => $id['chat_id'], 'user_id' => $from_id])->result->status;
            $result[] = $status;
        }
        return !in_array('left', $result);
    }
    return true;
}

function joinSend($from_id){
    global $sql, $texts;
    $lockSQL = $sql->query("SELECT `chat_id`, `name` FROM `lock`");
    $buttons = [];
    while ($row = $lockSQL->fetch_assoc()) {
        $link = $row['chat_id'];
        if ($link) {
            $chat_member = bot('getChatMember', ['chat_id' => $link, 'user_id' => $from_id]);
            if ($chat_member->ok && $chat_member->result->status == 'left') {
                $link = str_replace("@", "", $link);
                $buttons[] = [['text' => $row['name'], 'url' => "https://t.me/$link"]];
            }
        }
    }
    if (count($buttons) > 0) {
        $buttons[] = [['text' => "Я стал участником ✅", 'callback_data' => 'join']];
        sendmessage($from_id, $texts['send_join'], json_encode(['inline_keyboard' => $buttons]));
    }
}

function RubPayGenerator($project_id, $amount, $order_id, $sign2, $user_code, $user_ip, $user_deposits) {
    global $config, $payment_setting;
    // Prepare data for the request
    $data = array(
        'project_id' => $project_id,
        'amount' => $amount,
        'order_id' => $order_id,
        'sign2' => $sign2,
        'user_code' => $user_code,
        'user_ip' => $user_ip,
        'user_deposits' => $user_deposits
    );

    // Generate the sign2 parameter
    $data['sign2'] = md5( $payment_setting['rubpay_token'] . $data['project_id'] . $data['order_id'] . $data['amount'] . 1 /* RUB */ . 1 /* Банки России */ . $data['user_code'] . $data['user_ip'] . $data['user_deposits'] . $ $payment_setting['rubpay_token']);

    // Prepare the query string
    $query_string = http_build_query($data);

    // URL for the request
    $url = 'https://rubpay.io/pay/create?' . $query_string; // Replace 'yourdomain.com' with the actual domain

    return $url;
}

function CheckRubpay($project_id, $order_id) {
	global $payment_setting, $config;

    $url = 'https://rubpay.io/pay/status'; // Replace 'yourdomain.com' with the actual domain

    // Prepare data for the request
    $data = array(
        'project_id' => $project_id,
        'order_id' => $order_id
    );

    // Prepare the query string
    $query_string = http_build_query($data);

    // Append query string to the URL
    $url .= '?' . $query_string;

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => array(
            'x-api-key: ' . $payment_setting['rubpay_token']
        ),
    ));

    $response = curl_exec($curl);
    curl_close($curl);

    return $response;
}


function nowPaymentGenerator($price_amount, $price_currency, $pay_currency, $order_id) {
	global $payment_setting;

    $fields = array(
        "price_amount" => $price_amount,
        "price_currency" => $price_currency,
        "pay_currency" => $pay_currency,
        "order_id" => $order_id,
    );
    $fields = json_encode($fields);
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://api.nowpayments.io/v1/payment',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $fields,
        CURLOPT_HTTPHEADER => array(
            'x-api-key: ' . $payment_setting['nowpayment_token'],
            'Content-Type: application/json'
        ),
    ));
    $response = curl_exec($curl);
    curl_close($curl);
    return $response;
}

function checkNowPayment($payment_id) {
	global $payment_setting;

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://api.nowpayments.io/v1/payment/' . $payment_id,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => array(
            'x-api-key: ' . $payment_setting['nowpayment_token']
        ),
    ));
    $response = curl_exec($curl);
    curl_close($curl);
    return $response;
}

function generateUUID() {
    return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand( 0, 0xffff ),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

function loginPanelSanayi($address, $username, $password) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $address . '/login',
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['username' => $username, 'password' => $password]),
        CURLOPT_COOKIEJAR => 'cookie.txt',
    ]);
    $response = json_decode(curl_exec($ch), true);
    curl_close($ch);
    return $response;
}

function loginPanel($address, $username, $password) {
	$fields = array('username' => $username, 'password' => $password);
    $curl = curl_init($address . '/api/admin/token');
    curl_setopt_array($curl, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($fields),
        CURLOPT_HTTPHEADER => array('Content-Type: application/x-www-form-urlencoded', 'accept: application/json')
    ));
    $response = curl_exec($curl);
    if ($response === false) {
        error_log('cURL Error: ' . curl_error($curl));
    } else {
        return json_decode($response, true);
    }
    curl_close($curl);
}

function createService($username, $limit, $expire_data, $proxies, $inbounds, $token, $url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url . '/api/user');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json', 'Authorization: Bearer ' .  $token, 'Content-Type: application/json'));
    if ($inbounds != 'null') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array('proxies' => $proxies, 'inbounds' => $inbounds, 'expire' => $expire_data, 'data_limit' => $limit, 'username' => $username, 'data_limit_reset_strategy' => 'no_reset')));
    } else {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array('proxies' => $proxies, 'expire' => $expire_data, 'data_limit' => $limit, 'username' => $username, 'data_limit_reset_strategy' => 'no_reset')));
    }
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}

function getUserInfo($username, $token, $url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url . '/api/user/' . $username);
    curl_setopt($ch, CURLOPT_HTTPGET, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json', 'Authorization: Bearer ' . $token));
    $response = json_decode(curl_exec($ch), true);
    curl_close($ch);
    return $response;
}

function resetUserDataUsage($username, $token, $url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url . '/api/user/' . $username . '/reset');
    curl_setopt($ch, CURLOPT_POST , true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json', 'Authorization: Bearer ' . $token));
    $response = json_decode(curl_exec($ch), true);
    curl_close($ch);
    return $response;
}

function getSystemStatus($token, $url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url . '/api/system');
    curl_setopt($ch, CURLOPT_HTTPGET, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json', 'Authorization: Bearer ' . $token));
    $response = json_decode(curl_exec($ch), true);
    curl_close($ch);
    return $response;
}

function removeuser($username, $token, $url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url . '/api/user/' . $username);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    curl_setopt($ch, CURLOPT_HTTPGET, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json', 'Authorization: Bearer ' . $token));
    $response = json_decode(curl_exec($ch), true);
    curl_close($ch);
    return $response;
}

function Modifyuser($username, $data, $token, $url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url . '/api/user/' . $username);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json', 'Authorization: Bearer ' . $token, 'Content-Type: application/json'));
    $response = json_decode(curl_exec($ch), true);
    curl_close($ch);
    return $response;
}

function inbounds($token, $url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url . '/api/inbounds');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json', 'Authorization: Bearer ' . $token, 'Content-Type: application/json'));
    $response = json_decode(curl_exec($ch), true);
    curl_close($ch);
    return $response;
}

function checkInbound($inbounds, $inbound) {
    $inbounds = json_decode($inbounds, true);
    $found_inbound = false;
    foreach ($inbounds as $protocol) {
        foreach ($protocol as $item) {
            if (strtoupper($item['tag']) == strtoupper($inbound)) {
                $found_inbound = true;
                break;
            }
        }
    }
    return $found_inbound ? true : false;
}

# ----------------- [ <- клавиатура -> ] ----------------- #

if ($from_id == $config['dev']) {
    if ($test_account_setting['status'] == 'active' && $user['test_account'] == 'no') {
        $start_key = json_encode(['keyboard' => [
            [['text' => '🔧 Управление']],
            [['text' => '🛍 Мои сервисы'], ['text' => '🛒 Купить сервис']],
            [['text' => '🎁 Бесплатный тестовый сервис']],
            [['text' => '👤 Профиль'], ['text' => '🛒 Тарифы услуг'], ['text' => '💸 Пополнить счет']],
            [['text' => '🔗 Руководство по подключению'], ['text' => '📮 Онлайн поддержка']]
        ], 'resize_keyboard' => true]);
    } else {
        $start_key = json_encode(['keyboard' => [
            [['text' => '🔧 Управление']],
            [['text' => '🛍 Мои сервисы'], ['text' => '🛒 Купить сервис']],
            [['text' => '👤 Профиль'], ['text' => '🛒 Тарифы услуг'], ['text' => '💸 Пополнить счет']],
            [['text' => '🔗 Руководство по подключению'], ['text' => '📮 Онлайн поддержка']]
        ], 'resize_keyboard' => true]);
    }
} else {
    if ($test_account_setting['status'] == 'active' && $user['test_account'] == 'no') {
        $start_key = json_encode(['keyboard' => [
            [['text' => '🛍 Мои сервисы'], ['text' => '🛒 Купить сервис']],
            [['text' => '🎁 Бесплатный тестовый сервис']],
            [['text' => '👤 Профиль'], ['text' => '🛒 Тарифы услуг'], ['text' => '💸 Пополнить счет']],
            [['text' => '🔗 Руководство по подключению'], ['text' => '📮 Онлайн поддержка']]
        ], 'resize_keyboard' => true]);
    } else {
        $start_key = json_encode(['keyboard' => [
            [['text' => '🛍 Мои сервисы'], ['text' => '🛒 Купить сервис']],
            [['text' => '👤 Профиль'], ['text' => '🛒 Тарифы услуг'], ['text' => '💸 Пополнить счет']],
            [['text' => '🔗 Руководство по подключению'], ['text' => '📮 Онлайн поддержка']]
        ], 'resize_keyboard' => true]);
    }
}

$education = json_encode(['inline_keyboard' => [
    [['text' => '🍏 iOS', 'callback_data' => 'edu_ios'], ['text' => '📱 Android', 'callback_data' => 'edu_android']],
    [['text' => '🖥️ Mac', 'callback_data' => 'edu_mac'], ['text' => '💻 Windows', 'callback_data' => 'edu_windows']],
    [['text' => '🐧 Linux', 'callback_data' => 'edu_linux']]
]]);

$back = json_encode(['keyboard' => [
    [['text' => '🔙 Назад']]
], 'resize_keyboard' => true]);

$cancel_copen = json_encode(['inline_keyboard' => [
    [['text' => '❌ Отмена', 'callback_data' => 'cancel_copen']]
]]);

$confirm_service = json_encode(['keyboard' => [
    [['text' => '☑️ Создать сервис']], [['text' => '❌ Отмена']]
], 'resize_keyboard' => true]);

$select_diposet_payment = json_encode(['inline_keyboard' => [
    [['text' => '💳 Оплата в RUB', 'callback_data' => 'rubpay']],
    [['text' => '🪙 Оплата в Крипто', 'callback_data' => 'nowpayment']],
    [['text' => '❌ Отмена операции', 'callback_data' => 'cancel_payment_proccess']]
]]);

$send_phone = json_encode(['keyboard' => [
    [['text' => '🔒 Подтвердить и отправить номер', 'request_contact' => true]],
    [['text' => '🔙 Назад']]
], 'resize_keyboard' => true]);

$panel = json_encode(['keyboard' => [
    [['text' => '📞 Уведомление об обновлении бота']],
    [['text' => '🔑 Система аутентификации']],
    [['text' => '👥 Управление статистикой бота'], ['text' => '🌐 Управление сервером']],
    [['text' => '📤 Управление сообщениями'], ['text' => '👤 Управление пользователями']],
    [['text' => '⚙️ Настройки'], ['text' => '👮‍♂️ Управление админами']],
    [['text' => '🔙 Назад']],
], 'resize_keyboard' => true]);

$manage_statistics = json_encode(['keyboard' => [
    [['text' => '👤 Статистика бота']],
    [['text' => '⬅️ Вернуться к управлению']]
], 'resize_keyboard' => true]);

$manage_server = json_encode(['keyboard' => [
    [['text' => '⏱ Управление тестовым аккаунтом']],
    [['text' => '⚙️ Управление планами'], ['text' => '🎟 Добавить план']],
    [['text' => '⚙️ Список серверов'], ['text' => '➕ Добавить сервер']],
    [['text' => '⬅️ Вернуться к управлению']]
], 'resize_keyboard' => true]);

$select_panel = json_encode(['inline_keyboard' => [
    [['text' => '▫️Sanayi', 'callback_data' => 'sanayi']],
    [['text' => '▫️Hedifay', 'callback_data' => 'hedifay'], ['text' => '▫️Marzban', 'callback_data' => 'marzban']]
]]);

$add_plan_button = json_encode(['inline_keyboard' => [
    [['text' => '➕ План покупки услуги', 'callback_data' => 'add_buy_plan']],
    [['text' => '➕ План времени', 'callback_data' => 'add_date_plan'], ['text' => '➕ План объема', 'callback_data' => 'add_limit_plan']],
]]);

$manage_plans = json_encode(['inline_keyboard' => [
    [['text' => '🔧 План покупки услуги', 'callback_data' => 'manage_main_plan']],
    [['text' => '🔧 План времени', 'callback_data' => 'manage_date_plan'], ['text' => '🔧 План объема', 'callback_data' => 'manage_limit_plan']],
]]);

$end_inbound = json_encode(['keyboard' => [
    [['text' => '✔ Завершить и зарегистрировать']],
], 'resize_keyboard' => true]);

$manage_test_account = json_encode(['inline_keyboard' => [
    [['text' => ($test_account_setting['status'] == 'active') ? '🟢' : '🔴', 'callback_data' => 'change_test_account_status'], ['text' => '▫️Статус :', 'callback_data' => 'null']],
    [['text' => ($test_account_setting['panel'] == 'none') ? '🔴 Не подключен' : '🟢 Подключен', 'callback_data' => 'change_test_account_panel'], ['text' => '▫️Подключен к панели :', 'callback_data' => 'null']],
    [['text' => $sql->query("SELECT * FROM `test_account`")->num_rows, 'callback_data' => 'null'], ['text' => '▫️Количество тестовых аккаунтов :', 'callback_data' => 'null']],
    [['text' => $test_account_setting['volume'] . ' ГБ', 'callback_data' => 'change_test_account_volume'], ['text' => '▫️Объем :', 'callback_data' => 'null']],
    [['text' => $test_account_setting['time'] . ' часов', 'callback_data' => 'change_test_account_time'], ['text' => '▫️Время :', 'callback_data' => 'null']],
]]);

$manage_auth = json_encode(['inline_keyboard' => [
    [['text' => ($auth_setting['status'] == 'active') ? '🟢' : '🔴', 'callback_data' => 'change_status_auth'], ['text' => 'ℹ️ Система аутентификации :', 'callback_data' => 'null']],
    [['text' => ($auth_setting['iran_number'] == 'active') ? '🟢' : '🔴', 'callback_data' => 'change_status_auth_iran'], ['text' => '🇮🇷 Иранский номер :', 'callback_data' => 'null']],
    [['text' => ($auth_setting['virtual_number'] == 'active') ? '🟢' : '🔴', 'callback_data' => 'change_status_auth_virtual'], ['text' => '🏴󠁧󠁢󠁥󠁮󠁧󠁿 Виртуальный номер :', 'callback_data' => 'null']],
    [['text' => ($auth_setting['both_number'] == 'active') ? '🟢' : '🔴', 'callback_data' => 'change_status_auth_all_country'], ['text' => '🌎 Все номера :', 'callback_data' => 'null']],
]]);

$manage_service = json_encode(['keyboard' => [
    [['text' => '#⃣ Список всех услуг']],
    [['text' => '➖ Удалить услугу'], ['text' => '➕ Добавить услугу']],
    [['text' => 'ℹ️ Информация об услуге']],
    [['text' => '⬅️ Назад к управлению']]
], 'resize_keyboard' => true]);

$manage_message = json_encode(['keyboard' => [
    [['text' => '🔎 Состояние универсальной отправки/пересылки']],
    [['text' => '📬 Универсальная пересылка'], ['text' => '📬 Универсальная отправка']],
    [['text' => '📞 Отправить сообщение пользователю']],
    [['text' => '⬅️ Назад к управлению']]
], 'resize_keyboard' => true]);

$manage_user = json_encode(['keyboard' => [
    [['text' => '🔎 Информация о пользователе']],
    [['text' => '➖ Списать с баланса'], ['text' => '➕ Пополнить баланс']],
    [['text' => '❌ Заблокировать'], ['text' => '✅ Разблокировать']],
    [['text' => '📤 Отправить сообщение пользователю']],
    [['text' => '⬅️ Назад к управлению']]
], 'resize_keyboard' => true]);

$manage_admin = json_encode(['keyboard' => [
    [['text' => '➖ Удалить администратора'], ['text' => '➕ Добавить администратора']],
    [['text' => '⚙️ Список администраторов']],
    [['text' => '⬅️ Назад к управлению']]
], 'resize_keyboard' => true]);

$manage_setting = json_encode(['keyboard' => [
    [['text' => '🚫 Управление антиспамом']],
    [['text' => '◽ Каналы'], ['text' => '◽ Секции']],
    [['text' => '◽ Настройка текстов бота'], ['text' => '◽ Настройки платежного шлюза']],
    [['text' => '🎁 Управление кодами скидок']],
    [['text' => '⬅️ Назад к управлению']]
], 'resize_keyboard' => true]);

$manage_copens = json_encode(['inline_keyboard' => [
    [['text' => '➕ Добавить скидку', 'callback_data' => 'add_copen'], ['text' => '✏️ Управление', 'callback_data' => 'manage_copens']]
]]);

$manage_spam = json_encode(['inline_keyboard' => [
    [['text' => ($spam_setting['status'] == 'active') ? '🟢' : '🔴', 'callback_data' => 'change_status_spam'], ['text' => '▫️Статус :', 'callback_data' => 'null']],
    [['text' => ($spam_setting['type'] == 'ban') ? '🚫 Заблокировать' : '⚠️ Предупреждение', 'callback_data' => 'change_type_spam'], ['text' => '▫️Модель воздействия :', 'callback_data' => 'null']],
    [['text' => $spam_setting['time'] . ' секунд', 'callback_data' => 'change_time_spam'], ['text' => '▫️Время : ', 'callback_data' => 'null']],
    [['text' => $spam_setting['count_message'] . ' сообщений', 'callback_data' => 'change_count_spam'], ['text' => '▫️Количество сообщений : ', 'callback_data' => 'null']],
]]);

$manage_payment = json_encode(['keyboard' => [
    [['text' => '✏️ Состояние вкл/выкл платежного шлюза бота']],
    [['text' => '▫️Настройка владельца карты'], ['text' => '▫️Настройка номера карты']],
    [['text' => '▫️Заринпал'], ['text' => '▫️Айди пей']],
    [['text' => '◽ NOWPayments']],
    [['text' => '⬅️ Назад к управлению']]
], 'resize_keyboard' => true]);

$manage_off_on_paymanet = json_encode(['inline_keyboard' => [
    [['text' => ($payment_setting['rubpay_status'] == 'active') ? '🟢' : '🔴', 'callback_data' => 'change_status_rubpay'], ['text' => '💳 RUB:', 'callback_data' => 'null']],
    [['text' => ($payment_setting['nowpayment_status'] == 'active') ? '🟢' : '🔴', 'callback_data' => 'change_status_nowpayment'], ['text' => ': nowpayment ▫️', 'callback_data' => 'null']],
    [['text' => ($payment_setting['card_status'] == 'active') ? '🟢' : '🔴', 'callback_data' => 'change_status_card'], ['text' => '▫ QIWI:', 'callback_data' => 'null']]

]]);


$manage_texts = json_encode(['keyboard' => [
    [['text' => '✏️ Тариф на услуги'], ['text' => '✏️ Стартовый текст']],
    [['text' => '✏️ Инструкция по подключению']],
    [['text' => '⬅️ Вернуться к управлению']]
], 'resize_keyboard' => true]);

$set_text_edu = json_encode(['inline_keyboard' => [
    [['text' => '🍏 iOS', 'callback_data' => 'set_edu_ios'], ['text' => '📱 Android', 'callback_data' => 'set_edu_android']],
    [['text' => '🖥️ macOS', 'callback_data' => 'set_edu_mac'], ['text' => '💻 Windows', 'callback_data' => 'set_edu_windows']],
    [['text' => '🐧 Linux', 'callback_data' => 'set_edu_linux']]
]]);

$cancel = json_encode(['keyboard' => [
    [['text' => '❌ Отмена']]
], 'resize_keyboard' => true]);

$cancel_add_server = json_encode(['keyboard' => [
    [['text' => '❌ Отмена и назад']]
], 'resize_keyboard' => true]);

$back_panel = json_encode(['keyboard' => [
    [['text' => '⬅️ Вернуться к управлению']]
], 'resize_keyboard' => true]);

$back_panellist = json_encode(['inline_keyboard' => [
    [['text' => '🔙 Назад к списку панелей', 'callback_data' => 'back_panellist']],
]]);

$back_services = json_encode(['inline_keyboard' => [
    [['text' => '🔙 Назад', 'callback_data' => 'back_services']]
]]);

$back_account_test = json_encode(['inline_keyboard' => [
    [['text' => '🔙 Назад', 'callback_data' => 'back_account_test']]
]]);

$back_spam = json_encode(['inline_keyboard' => [
    [['text' => '🔙 Назад', 'callback_data' => 'back_spam']]
]]);

$back_copen = json_encode(['inline_keyboard' => [
    [['text' => '🔙 Назад', 'callback_data' => 'back_copen']]
]]);
