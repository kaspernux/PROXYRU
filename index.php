<?php

# -- #
/**
* Project name: Proxygram
* Channel: @Proxygram
* Group: @ProxygramHUB
 * Version: 2.5
**/

include_once 'config.php';
include_once 'api/sanayi.php';
# include_once  'api/hiddify.php';


if ($data == 'join') {
	if (isJoin($from_id)){
		deleteMessage($from_id, $message_id);
		sendMessage($from_id, $texts['success_joined'], $start_key);
	} else {
		alert($texts['not_join']);
	}
}

elseif(isJoin($from_id) == false){
    joinSend($from_id);
}

elseif($user['status'] == 'inactive' and $from_id != $config['dev']){
    sendMessage($from_id, $texts['block']);
}

elseif ($text == '/start' or $text == '🔙 Назад' or $text == '/back') {
        step('none');
    sendMessage($from_id, sprintf($texts['start'], $first_name), $start_key);
}

elseif ($text == '❌  Отмена' && $user['step'] == 'confirm_service') {
        step('none');
    foreach ([$from_id . '-location.txt', $from_id . '-protocol.txt'] as $file) if (file_exists($file)) unlink($file);
	if($sql->query("SELECT * FROM `service_factors` WHERE `from_id` = '$from_id'")->num_rows > 0) $sql->query("DELETE FROM `service_factors` WHERE `from_id` = '$from_id'");
	sendMessage($from_id, sprintf($texts['start'], $first_name), $start_key);
}

elseif ($text == '🛒 Покупка услуги') {
    	$servers = $sql->query("SELECT * FROM `panels` WHERE `status` = 'active'");
	if ($servers->num_rows > 0) {
		step('buy_service');
		if ($sql->query("SELECT * FROM `service_factors` WHERE `from_id` = '$from_id'")->num_rows > 0) $sql->query("DELETE FROM `service_factors` WHERE `from_id` = '$from_id'");
	    while ($row = $servers->fetch_assoc()) {
			$location[] = ['text' => $row['name']];
		}
		$location = array_chunk($location, 2);
        $location[] = [['text' => '🔙 Назад']];
		$location = json_encode(['keyboard' => $location, 'resize_keyboard' => true]);
		sendMessage($from_id, $texts['select_location'], $location);
	} else {
	    sendmessage($from_id, $texts['inactive_buy_service'], $start_key);
	}
}

elseif ($user['step'] == 'buy_service') {
	$response = $sql->query("SELECT `name` FROM `panels` WHERE `name` = '$text'");
	if ($response->num_rows == 0) {
	    step('none');
	    sendMessage($from_id, $texts['choice_error']);
	} else {
    	step('select_plan');
        $plans = $sql->query("SELECT * FROM `category` WHERE `status` = 'active'");
        while ($row = $plans->fetch_assoc()) {
            $plan[] = ['text' => $row['name']];
        }
        $plan = array_chunk($plan, 2);
        $plan[] = [['text' => '🔙 Назад']];
    	$plan = json_encode(['keyboard' => $plan, 'resize_keyboard' => true]);
    	file_put_contents("$from_id-location.txt", $text);
    	sendMessage($from_id, $texts['select_plan'], $plan);
	}
}

elseif ($user['step'] == 'select_plan') {
	$response = $sql->query("SELECT `name` FROM `category` WHERE `name` = '$text'")->num_rows;
	if ($response > 0) {
    	step('confirm_service');
    	sendMessage($from_id, $texts['create_factor'], $confirm_service);
    	$location = file_get_contents("$from_id-location.txt");
    	$plan = $text;
    	$code = rand(111111, 999999);
    	
    	$fetch = $sql->query("SELECT * FROM `category` WHERE `name` = '$text'")->fetch_assoc();
    	$price = $fetch['price'] ?? 0;
    	$limit = $fetch['limit'] ?? 0;
    	$date = $fetch['date'] ?? 0;
    	
    	$sql->query("INSERT INTO `service_factors` (`from_id`, `location`, `protocol`, `plan`, `price`, `code`, `status`) VALUES ('$from_id', '$location', 'null', '$plan', '$price', '$code', 'active')");
        $copen_key = json_encode(['inline_keyboard' => [[['text' => '🎁 Скидочный код', 'callback_data' => 'use_copen-'.$code]]]]);
    	sendMessage($from_id, sprintf($texts['service_factor'], $location, $limit, $date, $code, number_format($price)), $copen_key);
	} else {
	    sendMessage($from_id, $texts['choice_error']);
	}
}

elseif ($data == 'cancel_copen') {
    step('confirm_service');
    deleteMessage($from_id, $message_id);
}

elseif (strpos($data, 'use_copen') !== false and $user['step'] == 'confirm_service') {
    $code = explode('-', $data)[1];
    step('send_copen-'.$code);
    sendMessage($from_id, $texts['send_copen'], $cancel_copen);
}

elseif (strpos($user['step'], 'send_copen-') !== false) {
    $code = explode('-', $user['step'])[1];
    $copen = $sql->query("SELECT * FROM `copens` WHERE `copen` = '$text'");
    $service = $sql->query("SELECT * FROM `service_factors` WHERE `code` = '$code'")->fetch_assoc();
    if ($copen->num_rows > 0) {
        $copen = $copen->fetch_assoc();
        if ($copen['status'] == 'active') {
            if ($copen['count_use'] > 0) {
                step('confirm_service');
                $price =  $service['price'] * (intval($copen['percent']) / 100);
                $sql->query("UPDATE `service_factors` SET `price` = price - $price WHERE `code` = '$code'");
                sendMessage($from_id, sprintf($texts['success_copen'], $copen['percent']), $confirm_service);
            } else {
                sendMessage($from_id, $texts['copen_full'], $cancel_copen);
            }
        } else {
            sendMessage($from_id, $texts['copen_error'], $cancel_copen);
        }
    } else {
        sendMessage($from_id, $texts['copen_error'], $cancel_copen);
    }
}

elseif($user['step'] == 'confirm_service' and $text == '☑️ Создание сервиса'){
    step('none');
    sendMessage($from_id, $texts['create_service_proccess']);
    # ---------------- delete extra files ---------------- #
    foreach ([$from_id . '-location.txt', $from_id . '-protocol.txt'] as $file) if (file_exists($file)) unlink($file);
    # ---------------- get all information for create service ---------------- #
    $select_service = $sql->query("SELECT * FROM `service_factors` WHERE `from_id` = '$from_id'")->fetch_assoc();
    $location = $select_service['location'];
    $plan = $select_service['plan'];
    $price = $select_service['price'];
    $code = $select_service['code'];
    $status = $select_service['status'];
    $name = base64_encode($code) . '_' . $from_id;
    $get_plan = $sql->query("SELECT * FROM `category` WHERE `name` = '$plan'");
    $get_plan_fetch = $get_plan->fetch_assoc();
    $date = $get_plan_fetch['date'] ?? 0;
    $limit = $get_plan_fetch['limit'] ?? 0;
    $info_panel = $sql->query("SELECT * FROM `panels` WHERE `name` = '$location'");
    $panel = $info_panel->fetch_assoc();
    # ---------------- check coin for create service ---------------- #
    if ($user['coin'] < $select_service['price']) {
        sendMessage($from_id, sprintf($texts['not_coin'], number_format($price)), $start_key);
        exit();
    }
    # ---------------- check database ----------------#
    if ($get_plan->num_rows == 0) {
        sendmessage($from_id, sprintf($texts['create_error'], 0), $start_key);
        exit();
    }
    # ---------------- create service proccess ---------------- #
    if ($panel['type'] == 'marzban') {
        # ---------------- set proxies and inbounds proccess for marzban panel ---------------- #
        $protocols = explode('|', $panel['protocols']);
        unset($protocols[count($protocols)-1]);
        if ($protocols[0] == '') unset($protocols[0]);
        $proxies = array();
        foreach ($protocols as $protocol) {
            if ($protocol == 'vless' and $panel['flow'] == 'flowon'){
                $proxies[$protocol] = array('flow' => 'xtls-rprx-vision');
            } else {
                $proxies[$protocol] = array();
            }
        }
        $panel_inbounds = $sql->query("SELECT * FROM `marzban_inbounds` WHERE `panel` = '{$panel['code']}'");
        $inbounds = array();
        foreach ($protocols as $protocol) {
            while ($row = $panel_inbounds->fetch_assoc()) {
                $inbounds[$protocol][] = $row['inbound'];
            }
        }
        # ---------------- create service ---------------- #
        $token = loginPanel($panel['login_link'], $panel['username'], $panel['password'])['access_token'];
        $create_service = createService($name, convertToBytes($limit.'GB'), strtotime("+ $date day"), $proxies, ($panel_inbounds->num_rows > 0) ? $inbounds : 'null', $token, $panel['login_link']);
        $create_status = json_decode($create_service, true);
        # ---------------- check errors ---------------- #
        if (!isset($create_status['username'])) {
            sendMessage($from_id, sprintf($texts['create_error'], 1), $start_key);
            exit();
        }
        # ---------------- get links and subscription_url for send the user ---------------- #
        $links = "";
        foreach ($create_status['links'] as $link) $links .= $link . "\n\n";
        
        if ($info_panel->num_rows > 0) {
            $getMe = json_decode(file_get_contents("https://api.telegram.org/bot{$config['token']}/getMe"), true);
            $subscribe = (strpos($create_status['subscription_url'], 'http') !== false) ? $create_status['subscription_url'] : $panel['login_link'] . $create_status['subscription_url'];
            if ($panel['qr_code'] == 'active') {
                $encode_url = urlencode($subscribe);
                bot('sendPhoto', ['chat_id' => $from_id, 'photo' => "https://api.qrserver.com/v1/create-qr-code/?data=$encode_url&size=800x800", 'caption' => sprintf($texts['success_create_service'], $name, $location, $date, $limit, number_format($price), $subscribe, '@' . $getMe['result']['username']), 'parse_mode' => 'html', 'reply_markup' => $start_key]);
            } else {
                sendmessage($from_id, sprintf($texts['success_create_service'], $name, $location, $date, $limit, number_format($price), $subscribe, '@' . $getMe['result']['username']), $start_key);
            }
            $sql->query("INSERT INTO `orders` (`from_id`, `location`, `protocol`, `date`, `volume`, `link`, `price`, `code`, `status`, `type`) VALUES ('$from_id', '$location', 'null', '$date', '$limit', '$links', '$price', '$code', 'active', 'marzban')");
            // sendmessage($config['dev'], sprintf($texts['success_create_notif']), $first_name, $username, $from_id, $user['count_service'], $user['coin'], $location, $plan, $limit, $date, $code, number_format($price));
        }else{
            sendmessage($from_id, sprintf($texts['create_error'], 2), $start_key);
            exit();
        }

    } elseif ($panel['type'] == 'sanayi') {

        include_once 'api/sanayi.php';
        $xui = new Sanayi($panel['login_link'], $panel['token']);
        $san_setting = $sql->query("SELECT * FROM `sanayi_panel_setting` WHERE `code` = '{$panel['code']}'")->fetch_assoc();
        $create_service = $xui->addClient($name, $san_setting['inbound_id'], $date, $limit);
        $create_status = json_decode($create_service, true);
        # ---------------- check errors ---------------- #
        if ($create_status['status'] == false) {
            sendMessage($from_id, sprintf($texts['create_error'], 1), $start_key);
            exit();
        }
        # ---------------- get links and subscription_url for send the user ---------------- #
        if ($info_panel->num_rows > 0) {
            $getMe = json_decode(file_get_contents("https://api.telegram.org/bot{$config['token']}/getMe"), true);
            $link = str_replace(['%s1', '%s2', '%s3'], [$create_status['results']['id'], str_replace(parse_url($panel['login_link'])['port'], json_decode($xui->getPortById($san_setting['inbound_id']), true)['port'], str_replace(['https://', 'http://'], ['', ''], $panel['login_link'])), $create_status['results']['remark']], $san_setting['example_link']);
            if ($panel['qr_code'] == 'active') {
                $encode_url = urlencode($link);
                bot('sendPhoto', ['chat_id' => $from_id, 'photo' => "https://api.qrserver.com/v1/create-qr-code/?data=$encode_url&size=800x800", 'caption' => sprintf($texts['success_create_service_sanayi'], $name, $location, $date, $limit, number_format($price), $link, $create_status['results']['subscribe'], '@' . $getMe['result']['username']), 'parse_mode' => 'html', 'reply_markup' => $start_key]);
            } else {
                sendMessage($from_id, sprintf($texts['success_create_service_sanayi'], $name, $location, $date, $limit, number_format($price), $link, $create_status['results']['subscribe'], '@' . $getMe['result']['username']), $start_key);
            }
            $sql->query("INSERT INTO `orders` (`from_id`, `location`, `protocol`, `date`, `volume`, `link`, `price`, `code`, `status`, `type`) VALUES ('$from_id', '$location', 'null', '$date', '$limit', '$link', '$price', '$code', 'active', 'sanayi')");
            // sendMessage($config['dev'], sprintf($texts['success_create_notif']), $first_name, $username, $from_id, $user['count_service'], $user['coin'], $location, $plan, $limit, $date, $code, number_format($price));
        }else{
            sendMessage($from_id, sprintf($texts['create_error'], 2), $start_key);
            exit();
        }
    }
    $sql->query("DELETE FROM `service_factors` WHERE `from_id` = '$from_id'");
    $sql->query("UPDATE `users` SET `coin` = coin - $price, `count_service` = count_service + 1 WHERE `from_id` = '$from_id' LIMIT 1");
}

elseif ($text == '🎁 Бесплатный тестовый сервис' && $test_account_setting['status'] == 'active') {
        step('none');
    if ($user['test_account'] == 'no') {
        sendMessage($from_id, '⏳', $start_key);
        
        $panel = $sql->query("SELECT * FROM `panels` WHERE `code` = '{$test_account_setting['panel']}'");
        $panel_fetch = $panel->fetch_assoc();
        
        try {
            if ($panel_fetch['type'] == 'marzban') {
                # ---------------- set proxies and inbounds proccess for marzban panel ---------------- #
                $protocols = explode('|', $panel_fetch['protocols']);
                unset($protocols[count($protocols)-1]);
                if ($protocols[0] == '') unset($protocols[0]);
                $proxies = array();
                foreach ($protocols as $protocol) {
                    if ($protocol == 'vless' and $panel_fetch['flow'] == 'flowon'){
                        $proxies[$protocol] = array('flow' => 'xtls-rprx-vision');
                    } else {
                        $proxies[$protocol] = array();
                    }
                }
                
                $panel_inbounds = $sql->query("SELECT * FROM `marzban_inbounds` WHERE `panel` = '{$panel_fetch['code']}'");
                $inbounds = array();
                foreach ($protocols as $protocol) {
                    while ($row = $panel_inbounds->fetch_assoc()) {
                        $inbounds[$protocol][] = $row['inbound'];
                    }
                }
                # ---------------------------------------------- #
                $code = rand(111111, 999999);
                $name = base64_encode($code) . '_' . $from_id;
                $create_service = createService($name, convertToBytes($test_account_setting['volume'].'GB'), strtotime("+ {$test_account_setting['time']} hour"), $proxies, ($panel_inbounds->num_rows > 0) ? $inbounds : 'null', $panel_fetch['token'], $panel_fetch['login_link']);
                $create_status = json_decode($create_service, true);
                if (isset($create_status['username'])) {
                    $links = "";
                    foreach ($create_status['links'] as $link) $links .= $link . "\n\n";
		    $subscribe = (strpos($create_status['subscription_url'], 'http') !== false) ? $create_status['subscription_url'] : $panel_fetch['login_link'] . $create_status['subscription_url'];
                    $sql->query("UPDATE `users` SET `count_service` = count_service + 1, `test_account` = 'yes' WHERE `from_id` = '$from_id'");
                    $sql->query("INSERT INTO `test_account` (`from_id`, `location`, `date`, `volume`, `link`, `price`, `code`, `status`) VALUES ('$from_id', '{$panel_fetch['name']}', '{$test_account_setting['date']}', '{$test_account_setting['volume']}', '$links', '0', '$code', 'active')");
                    deleteMessage($from_id, $message_id + 1);
                    sendMessage($from_id, sprintf($texts['create_test_account'], $test_account_setting['time'], $subscribe, $panel_fetch['name'], $test_account_setting['time'], $test_account_setting['volume'], base64_encode($code)), $start_key);
                } else {
                    deleteMessage($from_id, $message_id + 1);
                    sendMessage($from_id, sprintf($texts['create_error'], 1), $start_key);
                }
            }

            if ($panel_fetch['type'] == 'sanayi') {
                include_once 'api/sanayi.php';
                $code = rand(111111, 999999);
                $name = base64_encode($code) . '_' . $from_id;
                $xui = new Sanayi($panel_fetch['login_link'], $panel_fetch['token']);
                $san_setting = $sql->query("SELECT * FROM `sanayi_panel_setting` WHERE `code` = '{$panel_fetch['code']}'")->fetch_assoc();
                $create_service = $xui->addClient($name, $san_setting['inbound_id'], $test_account_setting['volume'], ($test_account_setting['time'] / 24));
                $create_status = json_decode($create_service, true);
                $link = str_replace(['%s1', '%s2', '%s3'], [$create_status['results']['id'], str_replace(parse_url($panel_fetch['login_link'])['port'], json_decode($xui->getPortById($san_setting['inbound_id']), true)['port'], str_replace(['https://', 'http://'], ['', ''], $panel_fetch['login_link'])), $create_status['results']['remark']], $san_setting['example_link']);
                # ---------------- check errors ---------------- #
                if ($create_status['status'] == false) {
                    sendMessage($from_id, sprintf($texts['create_error'], 1), $start_key);
                    exit();
                }
                # ---------------------------------------------- #
                $sql->query("UPDATE `users` SET `count_service` = count_service + 1, `test_account` = 'yes' WHERE `from_id` = '$from_id'");
                $sql->query("INSERT INTO `test_account` (`from_id`, `location`, `date`, `volume`, `link`, `price`, `code`, `status`) VALUES ('$from_id', '{$panel_fetch['name']}', '{$test_account_setting['date']}', '{$test_account_setting['volume']}', '$link', '0', '$code', 'active')");
                deleteMessage($from_id, $message_id + 1);
                sendMessage($from_id, sprintf($texts['create_test_account'], $test_account_setting['time'], $link, $panel_fetch['name'], $test_account_setting['time'], $test_account_setting['volume'], base64_encode($code)), $start_key);
            }
        } catch (\Throwable $e) {
            sendMessage($config['dev'], $e);
        }

    } else {
        sendMessage($from_id, $texts['already_test_account'], $start_key);
    }
}

elseif ($text == '🛍 Мои услуги' || $data == 'back_services') {
        $services = $sql->query("SELECT * FROM `orders` WHERE `from_id` = '$from_id'");
    if ($services->num_rows > 0) {
        while ($row = $services->fetch_assoc()) {
            $status = ($row['status'] == 'active') ? '🟢 | ' : '🔴 | ';
            $key[] = ['text' => $status . base64_encode($row['code']) . ' - ' . $row['location'], 'callback_data' => 'service_status-'.$row['code']];
        }
        $key = array_chunk($key, 1);
        $key = json_encode(['inline_keyboard' => $key]);
        if (isset($text)) {
            sendMessage($from_id, sprintf($texts['my_services'], $services->num_rows), $key);
        } else {
        	editMessage($from_id, sprintf($texts['my_services'], $services->num_rows), $message_id, $key);
        }
    } else {
    	if (isset($text)) {
            sendMessage($from_id, $texts['my_services_not_found'], $start_key);
        } else {
        	editMessage($from_id, $texts['my_services_not_found'], $message_id, $start_key);
        }
    }
}

elseif (strpos($data, 'service_status-') !== false) {
    $code = explode('-', $data)[1];
    $getService = $sql->query("SELECT * FROM `orders` WHERE `code` = '$code'")->fetch_assoc();
    $panel = $sql->query("SELECT * FROM `panels` WHERE `name` = '{$getService['location']}'")->fetch_assoc();

    if ($panel['type'] == 'marzban') {

        $getUser = getUserInfo(base64_encode($code) . '_' . $from_id, $panel['token'], $panel['login_link']);
        if (isset($getUser['links']) and $getUser != false) {
            $links = implode("\n\n", $getUser['links']) ?? 'NULL';
            $subscribe = (strpos($getUser['subscription_url'], 'http') !== false) ? $getUser['subscription_url'] : $panel['login_link'] . $getUser['subscription_url'];
            $note = $sql->query("SELECT * FROM `notes` WHERE `code` = '$code'");

            $manage_service_btns = json_encode(['inline_keyboard' => [    
                    [["text" => "Покупка дополнительного объема", "callback_data" => "buy_extra_volume-" . $code . "-marzban"],
                    ["text" => "Увеличение времени", "callback_data" => "buy_extra_time-" . $code . "-marzban"]],
                    [["text" => "Написать заметку", "callback_data" => "write_note-" . $code . "-marzban"],
                    ["text" => "Получить QR-код", "callback_data" => "getQrCode-" . $code . "-marzban"]],
                    [["text" => "🔙 Назад", "callback_data" => "back_services"]]]
               ]);
              
            if ($note->num_rows == 0) {
                editMessage($from_id, sprintf(
                    $texts['your_service'],
                    ($getUser['status'] == 'active') ? '🟢 Активный' : '🔴 Неактивный',
                    $getService['location'],
                    base64_encode($code),
                    Conversion($getUser['used_traffic'], 'GB'),
                    Conversion($getUser['data_limit'], 'GB'),
                    date('Y-d-m H:i:s', $getUser['expire']),
                    $subscribe
                ), $message_id, $manage_service_btns);
            } else {
                $note = $note->fetch_assoc();
                editMessage($from_id, sprintf(
                    $texts['your_service_with_note'],
                    ($getUser['status'] == 'active') ? '🟢 Активный' : '🔴 Неактивный',
                    $note['note'],
                    $getService['location'],
                    base64_encode($code),
                    Conversion($getUser['used_traffic'], 'GB'),
                    Conversion($getUser['data_limit'], 'GB'),
                    date('Y-d-m H:i:s', $getUser['expire']),
                    $subscribe
                ), $message_id, $manage_service_btns);
                }
        } else {
            $sql->query("DELETE FROM `orders` WHERE `code` = '$code'");
            alert($texts['not_found_service']);
        }

    } elseif ($panel['type'] == 'sanayi') {

        include_once 'api/sanayi.php';
        $san_setting = $sql->query("SELECT * FROM `sanayi_panel_setting` WHERE `code` = '{$panel['code']}'")->fetch_assoc();
        $xui = new Sanayi($panel['login_link'], $panel['token']);
        $getUser = $xui->getUserInfo(base64_encode($code) . '_' . $from_id, $san_setting['inbound_id']);
        $getUser = json_decode($getUser, true);
        if ($getUser['status']) {
            $note = $sql->query("SELECT * FROM `notes` WHERE `code` = '$code'");
            $order = $sql->query("SELECT * FROM `orders` WHERE `code` = '$code'")->fetch_assoc();
            $link = $order['link'];

            $manage_service_btns = json_encode(['inline_keyboard' => [    
                // [['text' => 'Настройки доступа', 'callback_data' => 'access_settings-'.$code.'-sanayi']],
                [['text' => 'Покупка дополнительного объема', 'callback_data' => 'buy_extra_volume-'.$code.'-sanayi'], ['text' => 'Увеличение времени', 'callback_data' => 'buy_extra_time-'.$code.'-sanayi']],
                [['text' => 'Написать заметку', 'callback_data' => 'write_note-'.$code.'-sanayi'], ['text' => 'Получить QR-код', 'callback_data' => 'getQrCode-'.$code.'-sanayi']],
                [['text' => '🔙 Назад', 'callback_data' => 'back_services']]
            ]]);            

            if ($note->num_rows == 0) {
                editMessage($from_id, sprintf($texts['your_service'], ($getUser['result']['enable'] == true) ? '🟢 Включено' : '🔴 Отключено', $getService['location'], base64_encode($code), Conversion($getUser['result']['up'] + $getUser['result']['down'], 'GB'), ($getUser['result']['total'] == 0) ? 'Неограниченный' : Conversion($getUser['result']['total'], 'GB') . ' MB', date('Y-d-m H:i:s',  $getUser['result']['expiryTime']), $link), $message_id, $manage_service_btns);
            } else {
                $note = $note->fetch_assoc();
                editMessage($from_id, sprintf($texts['your_service_with_note'], ($getUser['result']['enable'] == true) ? '🟢 Включено' : '🔴 Отключено', $note['note'], $getService['location'], base64_encode($code), Conversion($getUser['result']['up'] + $getUser['result']['down'], 'GB'), ($getUser['result']['total'] == 0) ? 'Неограниченный' : Conversion($getUser['result']['total'], 'GB') . ' MB', date('Y-d-m H:i:s',  $getUser['result']['expiryTime']), $link), $message_id, $manage_service_btns);
            }
        } else {
            $sql->query("DELETE FROM `orders` WHERE `code` = '$code'");
            alert($texts['not_found_service']);
        }

    }
}

elseif (strpos($data, 'getQrCode') !== false) {
    alert($texts['wait']);

    $code = explode('-', $data)[1];
    $type = explode('-', $data)[2];
    $getService = $sql->query("SELECT * FROM `orders` WHERE `code` = '$code'")->fetch_assoc();
    $panel = $sql->query("SELECT * FROM `panels` WHERE `name` = '{$getService['location']}'")->fetch_assoc();

    if ($type == 'marzban') {
        $token = loginPanel($panel['login_link'], $panel['username'], $panel['password'])['access_token'];
        $getUser = getUserInfo(base64_encode($code) . '_' . $from_id, $token, $panel['login_link']);
        if (isset($getUser['links']) and $getUser != false) {
            $subscribe = (strpos($getUser['subscription_url'], 'http') !== false) ? $getUser['subscription_url'] : $panel['login_link'] . $getUser['subscription_url'];
            $encode_url = urldecode($subscribe);
            bot('sendPhoto', ['chat_id' => $from_id, 'photo' => "https://api.qrserver.com/v1/create-qr-code/?data=$encode_url&size=800x800", 'caption' => "<code>$subscribe</code>", 'parse_mode' => 'html']);
        } else {
            alert('❌ Error', true);
        }
    } elseif ($type == 'sanayi') {
        $order = $sql->query("SELECT * FROM `orders` WHERE `code` = '$code'")->fetch_assoc();
        $link = $order['link'];
        $encode_url = urlencode($link);
        bot('sendPhoto', ['chat_id' => $from_id, 'photo' => "https://api.qrserver.com/v1/create-qr-code/?data=$encode_url&size=800x800", 'caption' => "<code>$link</code>", 'parse_mode' => 'html']);
    } else {
        alert('❌ Error -> not found type !', true);
    }
}

elseif (strpos($data, 'write_note') !== false) {
    $code = explode('-', $data)[1];
    $type = explode('-', $data)[2];
    step('set_note-'.$code.'-'.$type);
    deleteMessage($from_id, $message_id);
    sendMessage($from_id, sprintf($texts['send_note'], $code), $back);
}

elseif (strpos($user['step'], 'set_note') !== false) {
    $code = explode('-', $user['step'])[1];
    $type = explode('-', $user['step'])[2];
    if ($sql->query("SELECT `code` FROM `notes` WHERE `code` = '$code'")->num_rows == 0) {
        $sql->query("INSERT INTO `notes` (`note`, `code`, `type`, `status`) VALUES ('$text', '$code', '$type', 'active')");
    } else {
        $sql->query("UPDATE `notes` SET `note` = '$text' WHERE `code` = '$code'");
    }
    sendMessage($from_id, sprintf($texts['set_note_success'], $code), $start_key);
}

elseif (strpos($data, 'buy_extra_time') !== false) {
    $code = explode('-', $data)[1];
    $type = explode('-', $data)[2];
    $category_date = $sql->query("SELECT * FROM `category_date` WHERE `status` = 'active'");

    if ($category_date->num_rows > 0) {
        while ($row = $category_date->fetch_assoc()) {
            $key[] = ['text' => $row['name'], 'callback_data' => 'select_extra_time-'.$row['code'].'-'.$code];
        }
        $key = array_chunk($key, 2);
        $key[] = [['text' => '🔙 Назад', 'callback_data' => 'service_status-'.$code]];
        $key = json_encode(['inline_keyboard' => $key]);
        editMessage($from_id, sprintf($texts['select_extra_time_plan'], $code), $message_id, $key);
    } else {
        alert($texts['not_found_plan_extra_time'], true);
    }
}

elseif (strpos($data, 'buy_extra_volume') !== false) {
    $code = explode('-', $data)[1];
    $type = explode('-', $data)[2];
    $category_limit = $sql->query("SELECT * FROM `category_limit` WHERE `status` = 'active'");

    if ($category_limit->num_rows > 0) {
        while ($row = $category_limit->fetch_assoc()) {
            $key[] = ['text' => $row['name'], 'callback_data' => 'select_extra_volume-'.$row['code'].'-'.$code];
        }
        $key = array_chunk($key, 2);
        $key[] = [['text' => '🔙 Назад', 'callback_data' => 'service_status-'.$code]];
        $key = json_encode(['inline_keyboard' => $key]);
        editMessage($from_id, sprintf($texts['select_extra_volume_plan'], $code), $message_id, $key);
    } else {
        alert($texts['not_found_plan_extra_volume'], true);
    }
}

elseif ($data == 'cancel_buy') {
    step('none');
    deleteMessage($from_id, $message_id);
    sendMessage($from_id, $texts['cancel_extra_factor'], $start_key);
}

elseif (strpos($data, 'select_extra_time') !== false) {
    $service_code = explode('-', $data)[2];
    $plan_code = explode('-', $data)[1];
    $service = $sql->query("SELECT * FROM `orders` WHERE `code` = '$service_code'")->fetch_assoc();
    $plan = $sql->query("SELECT * FROM `category_date` WHERE `code` = '$plan_code'")->fetch_assoc();
    
    $access_key = json_encode(['inline_keyboard' => [
        [['text' => '❌ Отменить', 'callback_data' => 'cancel_buy'], ['text' => '✅ Подтвердить', 'callback_data' => 'confirm_extra_time-'.$service_code.'-'.$plan_code]],
    ]]);
    
    editMessage($from_id, sprintf($texts['create_buy_extra_time_factor'], $service_code, $service_code, $plan['name'], number_format($plan['price']), $service_code), $message_id, $access_key);
}

elseif (strpos($data, 'confirm_extra_time') !== false) {
    alert($texts['wait']);
    $service_code = explode('-', $data)[1];
    $plan_code = explode('-', $data)[2];
    $service = $sql->query("SELECT * FROM `orders` WHERE `code` = '$service_code'")->fetch_assoc();
    $plan = $sql->query("SELECT * FROM `category_date` WHERE `code` = '$plan_code'")->fetch_assoc();
    $getService = $sql->query("SELECT * FROM `orders` WHERE `code` = '$service_code'")->fetch_assoc();
    $panel = $sql->query("SELECT * FROM `panels` WHERE `name` = '{$getService['location']}'")->fetch_assoc();

    if ($user['coin'] >= $plan['price']) {
        if ($service['type'] == 'marzban') {
            $token = loginPanel($panel['login_link'], $panel['username'], $panel['password'])['access_token'];
            $getUser = getUserInfo(base64_encode($service_code) . '_' . $from_id, $token, $panel['login_link']);
            $response = Modifyuser(base64_encode($service_code) . '_' . $from_id, array('expire' => $getUser['expire'] += 86400 * $plan['date']), $token, $panel['login_link']);
        } elseif ($service['type'] == 'sanayi') {
            include_once 'api/sanayi.php';
            $panel_setting = $sql->query("SELECT * FROM `sanayi_panel_setting` WHERE `code` = '{$panel['code']}'")->fetch_assoc();
            $xui = new Sanayi($panel['login_link'], $panel['token']);
            $getUser = $xui->getUserInfo(base64_encode($service_code) . '_' . $from_id, $panel_setting['inbound_id']);
            $getUser = json_decode($getUser, true);
            if ($getUser['status'] == true) {
                $response = $xui->addExpire(base64_encode($service_code) . '_' . $from_id, $plan['date'], $panel_setting['inbound_id']);
                // sendMessage($from_id, $response);
            } else {
                alert('❌ Error --> not found service');
            }
        }

        $sql->query("UPDATE `users` SET `coin` = coin - {$plan['price']} WHERE `from_id` = '$from_id'");
        deleteMessage($from_id, $message_id);
        sendMessage($from_id, sprintf($texts['success_extra_time'], $plan['date'], $plan['name'], number_format($plan['price'])), $start_key);
    } else {
        alert($texts['not_coin_extra'], true);
    }
}

elseif (strpos($data, 'select_extra_volume') !== false) {
    $service_code = explode('-', $data)[2];
    $plan_code = explode('-', $data)[1];
    $service = $sql->query("SELECT * FROM `orders` WHERE `code` = '$service_code'")->fetch_assoc();
    $plan = $sql->query("SELECT * FROM `category_limit` WHERE `code` = '$plan_code'")->fetch_assoc();
    
    $access_key = json_encode(['inline_keyboard' => [
        [['text' => '❌ Отменить', 'callback_data' => 'cancel_buy'], ['text' => '✅ Подтвердить', 'callback_data' => 'confirm_extra_volume-'.$service_code.'-'.$plan_code]],
    ]]);
    
    editMessage($from_id, sprintf($texts['create_buy_extra_volume_factor'], $service_code, $service_code, $plan['name'], number_format($plan['price']), $service_code), $message_id, $access_key);
}

elseif (strpos($data, 'confirm_extra_volume') !== false) {
    alert($texts['wait']);
    $service_code = explode('-', $data)[1];
    $plan_code = explode('-', $data)[2];
    $service = $sql->query("SELECT * FROM `orders` WHERE `code` = '$service_code'")->fetch_assoc();
    $plan = $sql->query("SELECT * FROM `category_limit` WHERE `code` = '$plan_code'")->fetch_assoc();
    $getService = $sql->query("SELECT * FROM `orders` WHERE `code` = '$service_code'")->fetch_assoc();
    $panel = $sql->query("SELECT * FROM `panels` WHERE `name` = '{$getService['location']}'")->fetch_assoc();

    if ($user['coin'] >= $plan['price']) {
        if ($service['type'] == 'marzban') {
            $token = loginPanel($panel['login_link'], $panel['username'], $panel['password'])['access_token'];
            $getUser = getUserInfo(base64_encode($service_code) . '_' . $from_id, $token, $panel['login_link']);
            $response = Modifyuser(base64_encode($service_code) . '_' . $from_id, array('data_limit' => $getUser['data_limit'] += $plan['limit'] * pow(1024, 3)), $token, $panel['login_link']);
        } elseif ($service['type'] == 'sanayi') {
            include_once 'api/sanayi.php';
            $panel_setting = $sql->query("SELECT * FROM `sanayi_panel_setting` WHERE `code` = '{$panel['code']}'")->fetch_assoc();
            $xui = new Sanayi($panel['login_link'], $panel['token']);
            $getUser = $xui->getUserInfo(base64_encode($service_code) . '_' . $from_id, $panel_setting['inbound_id']);
            $getUser = json_decode($getUser, true);
            if ($getUser['status'] == true) {
                $response = $xui->addVolume(base64_encode($service_code) . '_' . $from_id, $plan['limit'], $panel_setting['inbound_id']);
            } else {
                alert('❌ Ошибка --> сервис не найден');
            }
        }

        $sql->query("UPDATE `users` SET `coin` = coin - {$plan['price']} WHERE `from_id` = '$from_id'");
        deleteMessage($from_id, $message_id);
        sendMessage($from_id, sprintf($texts['success_extra_volume'], $plan['limit'], $plan['name'], number_format($plan['price'])), $start_key);
    } else {
        alert($texts['not_coin_extra'], true);
    }
}

elseif ($text == '💸 Пополнение счета') {
    if ($auth_setting['status'] == 'active') {
        if ($auth_setting['iran_number'] == 'active' or $auth_setting['virtual_number'] == 'active' or $auth_setting['both_number'] == 'active') {
            if (is_null($user['phone'])) {
                step('authentication');
                sendMessage($from_id, $texts['send_phone'], $send_phone);
            } else {
                step('diposet');
                sendMessage($from_id, $texts['diposet'], $back);
            }
        } else {
            step('diposet');
            sendMessage($from_id, $texts['diposet'], $back);
        }
    } else {
        step('diposet');
        sendMessage($from_id, $texts['diposet'], $back);
    }
}

elseif ($user['step'] == 'authentication') {
    $contact = $update->message->contact;
    if (isset($contact)) {
        if ($contact->user_id == $from_id) {
            if ($auth_setting['iran_number'] == 'active') {
                if (strpos($contact->phone_number, '+7') !== false) {
                    $sql->query("UPDATE `users` SET `phone` = '{$contact->phone_number}' WHERE `from_id` = '$from_id'");
                    sendMessage($from_id, $texts['send_phone_success'], $start_key);
                } else {
                    sendMessage($from_id, $texts['only_russia'], $back);
                }
            } elseif ($auth_setting['virtual_number'] == 'active') {
                if (strpos($contact->phone_number, '+7') === false) {
                    $sql->query("UPDATE `users` SET `phone` = '{$contact->phone_number}' WHERE `from_id` = '$from_id'");
                    sendMessage($from_id, $texts['send_phone_success'], $start_key);
                } else {
                    sendMessage($from_id, $texts['only_virtual'], $back);
                }
            } elseif ($auth_setting['both_number'] == 'active') {
                $sql->query("UPDATE `users` SET `phone` = '{$contact->phone_number}' WHERE `from_id` = '$from_id'");
                sendMessage($from_id, $texts['send_phone_success'], $start_key);   
            }
        } else {
            sendMessage($from_id, $texts['send_phone_with_below_btn'], $send_phone);    
        }
    } else {
        sendMessage($from_id, $texts['send_phone_with_below_btn'], $send_phone);
    }
}

elseif ($user['step'] == 'diposet') {
    if (is_numeric($text) and $text >= 2000) {
        step('sdp-' . $text);
        sendMessage($from_id, sprintf($texts['select_diposet_payment'], number_format($text)), $select_diposet_payment);
    } else {
        sendMessage($from_id, $texts['diposet_input_invalid'], $back);
    }
}

elseif ($data == 'cancel_payment_proccess') {
    step('none');
    deleteMessage($from_id, $message_id);
    sendMessage($from_id, sprintf($texts['start'], $first_name), $start_key);
}

elseif (in_array($data, ['RubPay']) and strpos($user['step'], 'sdp-') !== false) {
    if ($payment_setting[$data . '_status'] == 'active') {
        $status = $sql->query("SELECT `{$data}_token` FROM `payment_setting`")->fetch_assoc()[$data . '_token'];
        if ($status != 'none') {
            step('none');
            $price = explode('-', $user['step'])[1];
            $code = rand(11111111, 99999999);
            $sql->query("INSERT INTO `factors` (`from_id`, `price`, `code`, `status`) VALUES ('$from_id', '$price', '$code', 'no')");
            $response = ($data == 'RubPay') ? RubPayGenerator($from_id, $price, $code);
            if ($response) $pay = json_encode(['inline_keyboard' => [[['text' => '💵 Оплатить', 'url' => $response]]]]);
            deleteMessage($from_id, $message_id);
            sendMessage($from_id, sprintf($texts['create_diposet_factor'], $code, number_format($price)), $pay);
            sendMessage($from_id, $texts['back_to_menu'], $start_key);
        } else {
            alert($texts['error_choice_pay']);
        }
    } else {
        alert($texts['not_active_payment']);
    }
}

elseif ($data == 'nowpayment' and strpos($user['step'], 'sdp-') !== false) {
    if ($payment_setting[$data . '_status'] == 'active') {
        alert('⏱ Пожалуйста, подождите несколько секунд.');
        if ($payment_setting[$data . '_status'] == 'active') {
            $code = rand(111111, 999999);
            $price = explode('-', $user['step'])[1];
            $dollar = json_decode(file_get_contents($config['domain'] . '/api/arz.php'), true)['price'];
            $response_gen = nowPaymentGenerator((intval($price) / intval($dollar)), 'usd', 'trx', $code);
            if (!is_null($response_gen)) {
                $response = json_decode($response_gen, true);
                $sql->query("INSERT INTO `factors` (`from_id`, `price`, `code`, `status`) VALUES ('$from_id', '$price', '{$response['payment_id']}', 'no')");
                $key = json_encode(['inline_keyboard' => [[['text' => '✅ Я заплатил', 'callback_data' => 'checkpayment-' . $response['payment_id']]]]]);
                deleteMessage($from_id, $message_id);
                sendMessage($from_id, sprintf($texts['create_nowpayment_factor'], $response['payment_id'], number_format($price), number_format($dollar), $response['pay_amount'], $response['pay_address']), $key);
                sendMessage($from_id, $texts['back_to_menu'], $start_key);
            } else {
                deleteMessage($from_id, $message_id);
                sendMessage($from_id, $texts['error_nowpayment'] . "\n◽- <code>USDT: $dollar</code>", $start_key);
            }
        } else {
            alert($texts['not_active_payment']);
        }
    } else {
        alert($texts['not_active_payment']);
    }
}

elseif (strpos($data, 'checkpayment') !== false) {
    $payment_id = explode('-', $data)[1];
    $get = checkNowPayment($payment_id);
    $status = json_decode($get, true)['payment_status'];
    if ($status != 'waiting') {
        $factor = $sql->query("SELECT * FROM `factors` WHERE `code` = '$payment_id'")->fetch_assoc();
        if ($factor['status'] == 'no') {
            $sql->query("UPDATE `users` SET `coin` = coin + {$factor['price']}, `count_charge` = count_charge + 1 WHERE `from_id` = '$from_id'");
            $sql->query("UPDATE `factors` SET `status` = 'yes' WHERE `code` = '$payment_id'");
            deleteMessage($from_id, $message_id);
            sendMessage($from_id, sprintf($texts['success_nowpayment'], number_format($factor['price'])), $start_key);
            // sendMessage($config['dev'], $texts['success_payment_notif']);
        } else {
            alert($texts['not_success_nowpayment']);
        }
    } else {
        alert($texts['not_success_nowpayment']);
    }
}

elseif ($data == 'QIWI') {
	if ($payment_setting['card_status'] == 'active') {
	    $price = explode('-', $user['step'])[1];
	    step('send_fish-'.$price);
	    $code = rand(11111111, 99999999);
	    $card_number = $sql->query("SELECT `card_number` FROM `payment_setting`")->fetch_assoc()['card_number'];
	    $card_number_name = $sql->query("SELECT `card_number_name` FROM `payment_setting`")->fetch_assoc()['card_number_name'];
	    deleteMessage($from_id, $message_id);
	    sendMessage($from_id, sprintf($texts['create_qiwi_factor'], $code, number_format($price), ($card_number != 'none') ? $card_number : '❌ Не настроено', ($card_number_name != 'none') ? $card_number_name : ''), $back);
	} else {
        alert($texts['not_active_payment']);
    }
}

elseif (strpos($user['step'], 'send_fish') !== false) {
    $price = explode('-', $user['step'])[1];
    if (isset($update->message->photo)) {
        step('none');
        $key = json_encode(['inline_keyboard' => [[['text' => '❌', 'callback_data' => 'cancel_fish-'.$from_id], ['text' => '✅', 'callback_data' => 'accept_fish-'.$from_id.'-'.$price]]]]);
        sendMessage($from_id, $texts['success_send_fish'], $start_key);
        sendMessage($config['dev'], sprintf($texts['success_send_fish_notif'], $from_id, $username, $price), $key);
        forwardMessage($from_id, $config['dev'], $message_id);
        if (!is_null($settings['log_channel'])) {
            sendMessage($settings['log_channel'], sprintf($texts['success_send_fish_notif'], $from_id, $username, $price));
            forwardMessage($from_id, $settings['log_channel'], $message_id);
        }
    } else {
        sendMessage($from_id, $texts['error_input_qiwi'], $back);
    }
}

elseif ($text == '🛒 Тарифы услуг') {
    sendMessage($from_id, $texts['service_tariff']);
}

elseif ($text == '👤 Профиль') {
    $count_all = $sql->query("SELECT * FROM `orders` WHERE `from_id` = '$from_id'")->num_rows;
    $count_all_active = $sql->query("SELECT * FROM `orders` WHERE `from_id` = '$from_id' AND `status` = 'active'")->num_rows;
    $count_all_inactive = $sql->query("SELECT * FROM `orders` WHERE `from_id` = '$from_id' AND `status` = 'inactive'")->num_rows;
    sendMessage($from_id, sprintf($texts['my_account'], $from_id, number_format($user['coin']), $count_all, $count_all_active, $count_all_inactive), $start_key);
}

elseif ($text == '📮 Онлайн поддержка') {
    step('support');
    sendMessage($from_id, $texts['support'], $back);
}

elseif ($user['step'] == 'support') {
    step('none');
    sendMessage($from_id, $texts['success_support'], $start_key);
    sendMessage($config['dev'], sprintf($texts['new_support_message'], $from_id, $from_id, $username, $user['coin']), $manage_user);
    forwardMessage($from_id, $config['dev'], $message_id);
}

elseif ($text == '🔗 Руководство по подключению') {
    step('select_sys');
    sendMessage($from_id, $texts['select_sys'], $education);
}

elseif (strpos($data, 'edu') !== false) {
    $sys = explode('_', $data)[1];
    deleteMessage($from_id, $message_id);
    sendMessage($from_id, $texts['edu_'.$sys], $education);
}

# ------------ panel ------------ #

$admins = $sql->query("SELECT * FROM `admins`")->fetch_assoc() ?? [];
if ($from_id == $config['dev'] or in_array($from_id, $admins)) {
    if (in_array($text, ['/panel', 'panel', '🔧 Управление', 'Панель', '⬅️ Назад к управлению'])) {
        step('panel');
        sendMessage($from_id, "👮‍♂️ - Привет, уважаемый администратор [ <b>$first_name</b> ]!\n\n⚡️ Добро пожаловать в панель управления ботом.\n🗃 Текущая версия бота: <code>{$config['version']}</code>\n\n⚙️ Для управления ботом выберите один из следующих вариантов.\n\n🐝 | Чтобы получать обновления и следующие версии бота, подпишитесь на канал Proxygram:↓\n◽️@Proxygram\n🐝 Также присоединяйтесь к группе ProxygramHub для обратной связи по обновлениям или ошибкам:↓\n◽️@ProxygramHUB", $panel);    
    }
    
    elseif($text == '👥 Управление статистикой'){
        sendMessage($from_id, "👋 Добро пожаловать в управление общей статистикой.\n\n👇🏻Выберите один из вариантов:\n\n◽️@Proxygram", $manage_statistics);
    }
    
    elseif($text == '🌐 Управление сервером'){
        sendMessage($from_id, "⚙️ Добро пожаловать в управление планами.\n\n👇🏻Выберите один из вариантов:\n\n◽️@Proxygram", $manage_server);
    }
    
    elseif($text == '👤 Управление пользователями'){
        sendMessage($from_id, "👤 Добро пожаловать в управление пользователями.\n\n👇🏻Выберите один из вариантов:\n\n◽️@Proxygram", $manage_user);
    }
    
    elseif($text == '📤 Управление сообщениями'){
        sendMessage($from_id, "📤 Добро пожаловать в управление сообщениями.\n\n👇🏻Выберите один из вариантов:\n\n◽️@Proxygram", $manage_message);
    }
    
    elseif($text == '👮‍♂️ Управление администраторами'){
        sendMessage($from_id, "👮‍♂️ Добро пожаловать в управление администраторами.\n\n👇🏻Выберите один из вариантов:\n\n◽️@Proxygram", $manage_admin);
    }
    
    elseif($text == '⚙️ Настройки'){
        sendMessage($from_id, "⚙️️ Добро пожаловать в настройки бота.\n\n👇🏻Выберите один из вариантов:\n\n◽️@Proxygram", $manage_setting);
    }
    
    // ----------- do not touch this part ----------- //
    elseif ($text == base64_decode('YmFzZTY0X2RlY29kZQ==')('8J+TniDYp9i32YTYp9i524zZhyDYotm+2K/bjNiqINix2KjYp9iq')) {
        base64_decode('c2VuZE1lc3NhZ2U=')($from_id, base64_decode('8J+QnSB8INio2LHYp9uMINin2LfZhNin2Lkg2KfYsiDYqtmF2KfZhduMINii2b7Yr9uM2Kog2YfYpyDZiCDZhtiz2K7ZhyDZh9in24wg2KjYudiv24wg2LHYqNin2Kog2LLZhtio2YjYsSDZvtmG2YQg2K/YsSDaqdin2YbYp9mEINiy2YbYqNmI2LEg2b7ZhtmEINi52LbZiCDYtNuM2K8gOuKGkwril73vuI9AWmFuYm9yUGFuZWwK8J+QnSB8INmIINmH2YXahtmG24zZhiDYqNix2KfbjCDZhti42LEg2K/Zh9uMINii2b7Yr9uM2Kog24zYpyDYqNin2q8g2YfYpyDYqNmHINqv2LHZiNmHINiy2YbYqNmI2LEg2b7ZhtmEINio2b7bjNmI2YbYr9uM2K8gOuKGkwril73vuI9AWmFuYm9yUGFuZWxHYXAK8J+QnSB8INmG2YXZiNmG2Ycg2LHYqNin2Kog2KLYrtix24zZhiDZhtiz2K7ZhyDYsdio2KfYqiDYstmG2KjZiNixINm+2YbZhCA64oaTCuKXve+4j0BaYW5ib3JQYW5lbEJvdA=='), $panel);
    }
    
    // ----------- manage auth ----------- //
    elseif ($text == '🔑 Система аутентификации' or $data == 'manage_auth') {
        if (isset($text)) {
            sendMessage($from_id, "🀄️ Добро пожаловать в раздел системы аутентификации бота!\n\n📚 Руководство по этому разделу:↓\n\n🟢 : Активен \n🔴 : Неактивен", $manage_auth);
        } else {
            editMessage($from_id, "🀄️ Добро пожаловать в раздел системы аутентификации бота!\n\n📚 Руководство по этому разделу:↓\n\n🟢 : Активен \n🔴 : Неактивен", $message_id, $manage_auth);
        }
    }

    elseif ($data == 'change_status_auth') {
        if ($auth_setting['status'] == 'active') {
            $sql->query("UPDATE `auth_setting` SET `status` = 'inactive'");
        } else {
            $sql->query("UPDATE `auth_setting` SET `status` = 'active'");
        }
        alert('✅ Изменения успешно внесены.', true);
        editMessage($from_id, "🆙 Нажмите на кнопку ниже для обновления изменений!", $message_id, json_encode(['inline_keyboard' => [[['text' => '🔎 Обновить изменения', 'callback_data' => 'manage_auth']]]]));
    }

    elseif ($data == 'change_status_auth_iran') {
        if ($auth_setting['status'] == 'active') {
            if ($auth_setting['virtual_number'] == 'inactive' and $auth_setting['both_number'] == 'inactive') {
                if ($auth_setting['iran_number'] == 'active') {
                    $sql->query("UPDATE `auth_setting` SET `iran_number` = 'inactive'");
                } else {
                    $sql->query("UPDATE `auth_setting` SET `iran_number` = 'active'");
                }
                alert('✅ Изменения успешно внесены.', true);
                editMessage($from_id, "🆙 Нажмите на кнопку ниже для обновления изменений!", $message_id, json_encode(['inline_keyboard' => [[['text' => '🔎 Обновить изменения', 'callback_data' => 'manage_auth']]]]));
            } else {
                alert('⚠️ Для активации иранских номеров необходимо отключить разделы (🏴󠁧󠁢󠁥󠁮󠁧󠁿 Виртуальные номера) и (🌎 Все номера)!', true);
            }
        } else {
            alert('🔴 Чтобы активировать этот раздел, сначала нужно активировать (ℹ️ Система аутентификации)!', true);
        }
    }

    elseif ($data == 'change_status_auth_virtual') {
        if ($auth_setting['status'] == 'active') {
            if ($auth_setting['iran_number'] == 'inactive' and $auth_setting['both_number'] == 'inactive') {
                if ($auth_setting['virtual_number'] == 'active') {
                    $sql->query("UPDATE `auth_setting` SET `virtual_number` = 'inactive'");
                } else {
                    $sql->query("UPDATE `auth_setting` SET `virtual_number` = 'active'");
                }
                alert('✅ Изменения успешно внесены.', true);
                editMessage($from_id, "🆙 Нажмите на кнопку ниже для обновления изменений!", $message_id, json_encode(['inline_keyboard' => [[['text' => '🔎 Обновить изменения', 'callback_data' => 'manage_auth']]]]));
            } else {
                alert('⚠️ Для активации виртуальных номеров необходимо отключить разделы (🇮🇷 Иранские номера) и (🌎 Все номера)!', true);
            }
        } else {
            alert('🔴 Чтобы активировать этот раздел, сначала нужно активировать (ℹ️ Система аутентификации)!', true);
        }
    }
}

elseif ($data == 'change_status_auth_all_country') {
    if ($auth_setting['status'] == 'active') {
        if ($auth_setting['iran_number'] == 'inactive' && $auth_setting['virtual_number'] == 'inactive') {
            if ($auth_setting['both_number'] == 'active') {
                $sql->query("UPDATE `auth_setting` SET `both_number` = 'inactive'");
            } else {
                $sql->query("UPDATE `auth_setting` SET `both_number` = 'active'");
            }
            alert('✅ Изменения успешно выполнены.', true);
            editMessage($from_id, "🆙 Нажмите кнопку ниже, чтобы обновить изменения!", $message_id, json_encode(['inline_keyboard' => [[['text' => '🔎 Обновить изменения', 'callback_data' => 'manage_auth']]]]));
        } else {
            alert('⚠️ Чтобы активировать аутентификацию для всех стран, номер Ирана и виртуальный номер должны быть неактивными!', true);
        }
    } else {
        alert('🔴 Чтобы активировать этот раздел, сначала нужно включить Систему аутентификации!', true);
    }
}

    // ----------- Управление статусом ----------- //
    elseif ($text == '👤 Статистика бота') {
        $state1 = $sql->query("SELECT `status` FROM `users`")->num_rows;
        $state2 = $sql->query("SELECT `status` FROM `users` WHERE `status` = 'inactive'")->num_rows;
        $state3 = $sql->query("SELECT `status` FROM `users` WHERE `status` = 'active'")->num_rows;
        $state4 = $sql->query("SELECT `status` FROM `factors` WHERE `status` = 'yes'")->num_rows;
        sendMessage($from_id, "⚙️ Ваша статистика бота следующая:↓\n\n▫️Всего пользователей бота: <code>$state1</code> пользователей\n▫️Заблокированные пользователи: <code>$state2</code> пользователей\n▫️Активные пользователи: <code>$state3</code> пользователей\n\n🔢 Всего платежей: <code>$state4</code> раз\n\n🤖 @Proxygram", $manage_statistics);
    }

    // ----------- Управление серверами ----------- //
    elseif ($text == '❌ Отменить и вернуться') {
        step('none');
        if (file_exists('add_panel.txt')) unlink('add_panel.txt');
        sendMessage($from_id, "⚙️ Добро пожаловать в Управление серверными планами.\n\n👇🏻 Выберите один из вариантов ниже:\n\n◽️@Proxygram", $manage_server);
    }

    elseif ($data == 'close_panel') {
        step('none');
        editMessage($from_id, "✅ Панель сервера успешно закрыта!", $message_id);
    }

    elseif ($text == '⏱ Управление тестовым аккаунтом' || $data == 'back_account_test') {
        step('none');
        if (isset($text)) {
            sendMessage($from_id, "⏱ Добро пожаловать в настройки тестового аккаунта.\n\n🟢 Отправьте объем в GB боту | Например, 200 MB: 0.2\n🟢 Отправьте время в часах | Например, 5 часов: 5\n\n👇🏻 Выберите один из вариантов ниже:\n◽️@Proxygram", $manage_test_account);
        } else {
            editMessage($from_id, "⏱ Добро пожаловать в настройки тестового аккаунта.\n\n🟢 Отправьте объем в GB боту | Например, 200 MB: 0.2\n🟢 Отправьте время в часах | Например, 5 часов: 5\n\n👇🏻 Выберите один из вариантов ниже:\n◽️@Proxygram", $message_id, $manage_test_account);
        }
    }

    elseif ($data == 'null') {
        alert('#️⃣ Эта кнопка только для отображения!');
    }

    elseif ($user['step'] == 'change_test_account_volume') {
        if (isset($text)) {
            if (is_numeric($text)) {
                step('none');
                $sql->query("UPDATE `test_account_setting` SET `volume` = '$text'");
                $manage_test_account = json_encode(['inline_keyboard' => [
                    [['text' => ($status == 'active') ? '🔴' : '🟢', 'callback_data' => 'change_test_account_status'], ['text' => '▫️Статус:', 'callback_data' => 'null']],
                    // ... (остальные кнопки клавиатуры)
                ]]);
                sendMessage($from_id, "✅ Изменения успешно выполнены.\n\n👇🏻 Выберите один из вариантов ниже.\n◽️@Proxygram", $manage_test_account);
            } else {
                sendMessage($from_id, "❌ Неверный ввод!", $back_account_test);
            }
        }
    }
    
    elseif ($data == 'change_test_account_time') {
        step('change_test_account_time');
        editMessage($from_id, "🆕 Отправьте новое значение в виде целого числа:", $message_id, $back_account_test);
    }
    
    elseif ($user['step'] == 'change_test_account_time') {
        if (isset($text)) {
            if (is_numeric($text)) {
                step('none');
                $sql->query("UPDATE `test_account_setting` SET `time` = '$text'");
                $manage_test_account = json_encode(['inline_keyboard' => [
                    [['text' => ($status == 'active') ? '🔴' : '🟢', 'callback_data' => 'change_test_account_status'], ['text' => '▫️Статус:', 'callback_data' => 'null']],
                    // ... (остальные кнопки клавиатуры)
                ]]);
                sendMessage($from_id, "✅ Изменения успешно выполнены.\n\n👇🏻 Выберите один из вариантов ниже.\n◽️@Proxygram", $manage_test_account);
            } else {
                sendMessage($from_id, "❌ Неверный ввод!", $back_account_test);
            }
        }
    }
    
    // ... (остальной код)
    
    
    elseif ($data == 'change_test_account_panel') {
        $panels = $sql->query("SELECT * FROM `panels`");
        if ($panels->num_rows > 0) {
            step('change_test_account_panel');
            while ($row = $panels->fetch_assoc()) {
                $key[] = [['text' => $row['name'], 'callback_data' => 'select_test_panel-'.$row['code']]];
            }
            $key[] = [['text' => '🔙 Назад', 'callback_data' => 'back_account_test']];
            $key = json_encode(['inline_keyboard' => $key]);
            editMessage($from_id, "🔧 Выберите одну из нижеперечисленных панелей для тестирования учетной записи:", $message_id, $key);
        } else {
            alert('❌ Нет зарегистрированных панелей в боте!');
        }
    }
    
    elseif (strpos($data, 'select_test_panel-') !== false) {
        $code = explode('-', $data)[1];
        $panel = $sql->query("SELECT * FROM `panels` WHERE `code` = '$code'");
        if ($panel->num_rows > 0) {
            $sql->query("UPDATE `test_account_setting` SET `panel` = '$code'");
            $panel = $panel->fetch_assoc();
            $manage_test_account = json_encode(['inline_keyboard' => [
                [['text' => ($test_account_setting['status'] == 'active') ? '🟢' : '🔴', 'callback_data' => 'change_test_account_status'], ['text' => '▫️Статус :', 'callback_data' => 'null']],
                [['text' => $panel['name'], 'callback_data' => 'change_test_account_panel'], ['text' => '▫️Подключен к панели :', 'callback_data' => 'null']],
                [['text' => $sql->query("SELECT * FROM `test_account`")->num_rows, 'callback_data' => 'null'], ['text' => '▫️Количество тестовых аккаунтов :', 'callback_data' => 'null']],
                [['text' => $test_account_setting['volume'] . ' GB', 'callback_data' => 'change_test_account_volume'], ['text' => '▫️Объем :', 'callback_data' => 'null']],
                [['text' => $test_account_setting['time'] . ' часов', 'callback_data' => 'change_test_account_time'], ['text' => '▫️Время :', 'callback_data' => 'null']],
            ]]);
            editMessage($from_id, "✅ Операция изменений успешно выполнена.\n\n👇🏻 Выберите один из вариантов ниже.\n◽️@Proxygram", $message_id, $manage_test_account);
        } else {
            alert('❌ Указанная панель не найдена!');
        }
    }
    
    elseif ($text == '➕ Добавить сервер') {
        step('add_server_select');
        sendMessage($from_id, "ℹ️ Какую из нижеперечисленных панелей вы хотите добавить?", $select_panel);
    }
    

    # ------------- sanayi ------------- #

    elseif ($data == 'sanayi') {
        step('add_server_sanayi');
        deleteMessage($from_id, $message_id);
        sendMessage($from_id, "‌👈🏻⁩ Отправьте название своей панели по вашему усмотрению :↓\n\nПример названия: 🇳🇱 - Голландия\n• Это имя будет отображаться для пользователей.", $cancel_add_server);
    }
    
    elseif ($user['step'] == 'add_server_sanayi') {
        if ($sql->query("SELECT `name` FROM `panels` WHERE `name` = '$text'")->num_rows == 0) {
            step('send_address_sanayi');
            file_put_contents('add_panel.txt', "$text\n", FILE_APPEND);
            sendMessage($from_id, "🌐 Отправьте ссылку для входа в панель.\n\n- Пример:\n\n<code>http://1.1.1.1:8000\n http://1.1.1.1:8000/vrshop\n http://domain.com:8000</code>", $cancel_add_server);
        } else {
            sendMessage($from_id, "❌ Панель с именем [ <b>$text</b> ] уже зарегистрирована в боте!", $cancel_add_server);
        }
    }
    
    elseif ($user['step'] == 'send_address_sanayi') {
        if (preg_match("/^(http|https):\/\/(\d+\.\d+\.\d+\.\d+|.*)\:.*$/", $text)) {
            if ($sql->query("SELECT `login_link` FROM `panels` WHERE `login_link` = '$text'")->num_rows == 0) {
                step('send_username_sanayi');
                file_put_contents('add_panel.txt', "$text\n", FILE_APPEND);
                sendMessage($from_id, "🔎 - Отправьте имя пользователя ( <b>username</b> ) для вашей панели :", $cancel_add_server);
            } else {
                sendMessage($from_id, "❌ Панель с адресом [ <b>$text</b> ] уже зарегистрирована в боте!", $cancel_add_server);
            }
        } else {
            sendMessage($from_id, "🚫 Ошибка в отправленной вами ссылке!", $cancel_add_server);
        }
    }
    
    elseif ($user['step'] == 'send_username_sanayi') {
        step('send_password_sanayi');
        file_put_contents('add_panel.txt', "$text\n", FILE_APPEND);
        sendMessage($from_id, "🔎 - Отправьте пароль ( <b>password</b> ) для вашего сервера:", $cancel_add_server);
    }
    
    elseif ($user['step'] == 'send_password_sanayi') {
        step('none');
        $info = explode("\n", file_get_contents('add_panel.txt'));
        $response = loginPanelSanayi($info[1], $info[2], $text);
        if ($response['success']) {
            $code = rand(11111111, 99999999);
            $session = str_replace([" ", "\n", "\t"], ['', '', ''], explode('session	', file_get_contents('cookie.txt'))[1]);
            $sql->query("INSERT INTO `panels` (`name`, `login_link`, `username`, `password`, `token`, `code`, `status`, `type`) VALUES ('{$info[0]}', '{$info[1]}', '{$info[2]}', '$text', '$session', '$code', 'inactive', 'sanayi')");
            $sql->query("INSERT INTO `sanayi_panel_setting` (`code`, `inbound_id`, `example_link`, `flow`) VALUES ('$code', 'none', 'none', 'offflow')");
            sendMessage($from_id, "✅ Бот успешно паркован !\n\n▫️Имя пользователя : <code>{$info[2]}</code>\n▫️Пароль : <code>{$text}</code>\n▫️Код отслеживания : <code>$code</code>", $manage_server);
        } else {
            sendMessage($from_id, "❌ Ошибка входа в панель, попробуйте снова через несколько минут!\n\n🎯 Возможные причины неудачного подключения робота к вашей панели:↓\n\n◽ Порт недоступен\n◽ Неправильный адрес\n◽ Неправильный адрес\n◽ Неправильный логин или пароль\n◽ IP-адрес заблокирован\n◽ Отсутствие доступа к CURL\n◽ Общие проблемы с хостом", $manage_server);
        }
        foreach (['add_panel.txt', 'cookie.txt'] as $file) if (file_exists($file)) unlink($file);
    }
    
    
    # ------------- marzban ------------- #
    
    elseif ($data == 'marzban') {
        step('add_server');
        deleteMessage($from_id, $message_id);
        sendMessage($from_id, "‌👈🏻⁩ Отправьте название своей панели на ваше усмотрение :↓\n\nПример названия: 🇳🇱 - Голландия\n• Это имя будет отображаться для пользователей.", $cancel_add_server);
    }
    
    elseif ($user['step'] == 'add_server') {
        if ($sql->query("SELECT `name` FROM `panels` WHERE `name` = '$text'")->num_rows == 0) {
            step('send_address');
            file_put_contents('add_panel.txt', "$text\n", FILE_APPEND);
            sendMessage($from_id, "🌐 Отправьте ссылку для входа в панель.\n\n- Пример : http://1.1.1.1:8000", $cancel_add_server);
        } else {
            sendMessage($from_id, "❌ Панель с именем [ <b>$text</b> ] уже зарегистрирована в боте!", $cancel_add_server);
        }
    }
    
    elseif ($user['step'] == 'send_address') {
        if (preg_match("/^(http|https):\/\/(\d+\.\d+\.\d+\.\d+|.*)\:\d+$/", $text)) {
            if ($sql->query("SELECT `login_link` FROM `panels` WHERE `login_link` = '$text'")->num_rows == 0) {
                step('send_username');
                file_put_contents('add_panel.txt', "$text\n", FILE_APPEND);
                sendMessage($from_id, "🔎 - Отправьте имя пользователя ( <b>username</b> ) для вашей панели :", $cancel_add_server);
            } else {
                sendMessage($from_id, "❌ Панель с адресом [ <b>$text</b> ] уже зарегистрирована в боте!", $cancel_add_server);
            }
        } else {
            sendMessage($from_id, "🚫 Ошибка в отправленной вами ссылке!", $cancel_add_server);
        }
    }
    
    elseif ($user['step'] == 'send_username') {
        step('send_password');
        file_put_contents('add_panel.txt', "$text\n", FILE_APPEND);
        sendMessage($from_id, "🔎 - Отправьте пароль ( <b>password</b> ) для вашего сервера:", $cancel_add_server);
    }
    
    elseif ($user['step'] == 'send_password') {
        step('none');
        $info = explode("\n", file_get_contents('add_panel.txt'));
        $response = loginPanel($info[1], $info[2], $text);
        if (isset($response['access_token'])) {
            $code = rand(11111111, 99999999);
            $sql->query("INSERT INTO `panels` (`name`, `login_link`, `username`, `password`, `token`, `code`, `type`) VALUES ('{$info[0]}', '{$info[1]}', '{$info[2]}', '$text', '{$response['access_token']}', '$code', 'marzban')");
            sendMessage($from_id, "✅ Бот успешно паркован!\n\n▫️Имя пользователя : <code>{$info[2]}</code>\n▫️Пароль : <code>{$text}</code>\n▫️Код отслеживания : <code>$code</code>", $manage_server);
        } else {
            sendMessage($from_id, "❌ Ошибка входа в панель, попробуйте снова через несколько минут!\n\n🎯 Возможные причины неудачного подключения робота к вашей панели:↓\n\n◽ Порт недоступен\n◽ Неправильный адрес\n◽ Неправильный логин или пароль\n◽ IP-адрес заблокирован\n◽ Отсутствие доступа к CURL\n◽ Общие проблемы с хостом", $manage_server);
        }
        if (file_exists('add_panel.txt')) unlink('add_panel.txt');
    }
    
    
    # ------------------------------------ #
    
    elseif ($text == '🎟 Добавить тариф') {
        step('none');
        sendMessage($from_id, "ℹ️ Какой тип тарифа вы хотите добавить?\n\n👇🏻 Выберите один из вариантов:", $add_plan_button);
    }
    
    elseif ($data == 'add_buy_plan') { 
        step('add_name');
        deleteMessage($from_id, $message_id);
        sendMessage($from_id, "👇🏻Отправьте название этой категории:↓", $back_panel);
    }
    
    elseif ($user['step'] == 'add_name' and $text != '⬅️ Назад к управлению') {
        step('add_limit');
        file_put_contents('add_plan.txt', "$text\n", FILE_APPEND);
        sendMessage($from_id, "👇🏻Отправьте размер в ГБ целым числом:↓\n\n◽ Пример: <code>50</code>", $back_panel);
    }
    
    elseif ($user['step'] == 'add_limit' and $text != '⬅️ Назад к управлению') {
        step('add_date');
        file_put_contents('add_plan.txt', "$text\n", FILE_APPEND);
        sendMessage($from_id, "👇🏻Отправьте срок в днях целым числом:↓\n\n◽ Пример: <code>30</code>", $back_panel);
    }
    
    elseif ($user['step'] == 'add_date' and $text != '⬅️ Назад к управлению') {
        step('add_price');
        file_put_contents('add_plan.txt', "$text\n", FILE_APPEND);
        sendMessage($from_id, "💸Отправьте цену за этот объем целым числом:↓\n\n◽ Пример: <code>60000</code>", $back_panel);
    }
    
    
    elseif ($user['step'] == 'add_price' and $text != '⬅️ Вернуться к управлению') {
        step('none');
        $info = explode("\n", file_get_contents('add_plan.txt'));
        $code = rand(1111111, 9999999);
        $sql->query("INSERT INTO `category` (`limit`, `date`, `name`, `price`, `code`, `status`) VALUES ('{$info[1]}', '{$info[2]}', '{$info[0]}', '$text', '$code', 'active')");
        sendmessage($from_id, "✅ Ваши данные успешно зарегистрированы и добавлены в список.\n\n◽ Отправленный объем : <code>{$info[1]}</code>\n◽ Отправленная цена : <code>$text</code>", $manage_server);
        if (file_exists('add_plan.txt')) unlink('add_plan.txt');
    }
    
    elseif ($data == 'add_limit_plan') { 
        step('add_name_limit');
        deleteMessage($from_id, $message_id);
        sendMessage($from_id, "👇🏻Отправьте название этой категории:↓", $back_panel);
    }
    
    elseif ($user['step'] == 'add_name_limit' and $text != '⬅️ Вернуться к управлению') {
        step('add_limit_limit');
        file_put_contents('add_plan_limit.txt', "$text\n", FILE_APPEND);
        sendMessage($from_id, "👇🏻Отправьте размер в ГБ целым латинским числом:↓\n\n◽ Пример: <code>50</code>", $back_panel);
    }
    
    elseif ($user['step'] == 'add_limit_limit' and $text != '⬅️ Вернуться к управлению') {
        step('add_price_limit');
        file_put_contents('add_plan_limit.txt', "$text\n", FILE_APPEND);
        sendMessage($from_id, "💸 Отправьте цену за этот объем целым латинским числом:↓\n\n◽ Пример: <code>60000</code>", $back_panel);
    }
    
    elseif ($user['step'] == 'add_price_limit' and $text != '⬅️ Вернуться к управлению') {
        step('none');
        $info = explode("\n", file_get_contents('add_plan_limit.txt'));
        $code = rand(1111111, 9999999);
        $sql->query("INSERT INTO `category_limit` (`limit`, `name`, `price`, `code`, `status`) VALUES ('{$info[1]}', '{$info[0]}', '$text', '$code', 'active')");
        sendmessage($from_id, "✅ Ваши данные успешно зарегистрированы и добавлены в список.\n\n◽ Отправленный объем : <code>{$info[1]}</code>\n◽ Отправленная цена : <code>$text</code>", $manage_server);
        if (file_exists('add_plan_limit.txt')) unlink('add_plan_limit.txt');
    }    

    elseif ($data == 'add_date_plan') { 
        step('add_name_date');
        deleteMessage($from_id, $message_id);
        sendMessage($from_id, "👇🏻Пожалуйста, отправьте название этой категории:↓", $back_panel);
    }
    
    elseif ($user['step'] == 'add_name_date' and $text != '⬅️ Вернуться к управлению') {
        step('add_date_date');
        file_put_contents('add_plan_date.txt', "$text\n", FILE_APPEND);
        sendMessage($from_id, "👇🏻Пожалуйста, отправьте дату в виде целого числа:↓\n\n◽ Пример: <code>30</code>", $back_panel);
    }
    
    elseif ($user['step'] == 'add_date_date' and $text != '⬅️ Вернуться к управлению') {
        step('add_price_date');
        file_put_contents('add_plan_date.txt', "$text\n", FILE_APPEND);
        sendMessage($from_id, "💸 Пожалуйста, отправьте сумму этого объема в виде целого числа:↓\n\n◽ Пример: <code>60000</code>", $back_panel);
    }
    
    elseif ($user['step'] == 'add_price_date' and $text != '⬅️ Вернуться к управлению') {
        step('none');
        $info = explode("\n", file_get_contents('add_plan_date.txt'));
        $code = rand(1111111, 9999999);
        $sql->query("INSERT INTO `category_date` (`date`, `name`, `price`, `code`, `status`) VALUES ('{$info[1]}', '{$info[0]}', '$text', '$code', 'active')");
        sendmessage($from_id, "✅ Ваши данные успешно сохранены и добавлены в список.\n\n◽ Отправленный объем: <code>{$info[1]}</code>\n◽ Отправленная сумма: <code>$text</code>", $manage_server);
        if (file_exists('add_plan_date.txt')) unlink('add_plan_date.txt');
    }
    
    elseif ($text == '⚙️ Список серверов' or $data == 'back_panellist') {
        step('none');
        $info_servers = $sql->query("SELECT * FROM `panels`");
        if($info_servers->num_rows == 0){
            if(!isset($data)){
                sendMessage($from_id, "❌ Ни один сервер не зарегистрирован в боте.");
            }else{
                editMessage($from_id, "❌ Ни один сервер не зарегистрирован в боте.", $message_id);
            }
            exit();
        }
        $key[] = [['text' => '▫️Статус', 'callback_data' => 'null'], ['text' => '▫️Имя', 'callback_data' => 'null'], ['text' => '▫️Код отслеживания', 'callback_data' => 'null']];
        while($row = $info_servers->fetch_array()){
            $name = $row['name'];
            $code = $row['code'];
            if($row['status'] == 'active') $status = '✅ Активный'; else $status = '❌ Неактивный';
            $key[] = [['text' => $status, 'callback_data' => 'change_status_panel-'.$code], ['text' => $name, 'callback_data' => 'status_panel-'.$code], ['text' => $code, 'callback_data' => 'status_panel-'.$code]];
        }
        $key[] = [['text' => '❌ Закрыть панель | Закрыть панель', 'callback_data' => 'close_panel']];
        $key = json_encode(['inline_keyboard' => $key]);
        if(!isset($data)){
            sendMessage($from_id, "🔎 Список зарегистрированных вами серверов :\n\n⚙️ Вы можете перейти в раздел управления сервером, щелкнув по коду отслеживания сервера.\n\nℹ️ Для управления каждым из них нажмите на него.", $key);
        }else{
            editMessage($from_id, "🔎 Список зарегистрированных вами серверов :\n\n⚙️ Вы можете перейти в раздел управления сервером, щелкнув по коду отслеживания сервера.\n\nℹ️ Для управления каждым из них нажмите на него.", $message_id, $key);
        }
    }    
    
    elseif (strpos($data, 'change_status_panel-') !== false) {
        $code = explode('-', $data)[1];
        $info_panel = $sql->query("SELECT * FROM `panels` WHERE `code` = '$code'")->fetch_assoc();
        if ($info_panel['type'] == 'sanayi') {
            $sanayi_setting = $sql->query("SELECT * FROM `sanayi_panel_setting` WHERE `code` = '{$info_panel['code']}'")->fetch_assoc();
            if ($sanayi_setting['example_link'] == 'none') {
                alert('🔴 Чтобы включить панель Sanayi, сначала нужно установить идентификатор входящей связи (inbound ID) и пример сервиса!');
                exit;
            } elseif ($sanayi_setting['inbound_id'] == 'none') {
                alert('🔴 Чтобы включить панель Sanayi, сначала нужно установить идентификатор входящей связи (inbound ID) и пример сервиса!');
                exit;
            }
        }
        $status = $info_panel['status'];
        if($status == 'active'){
            $sql->query("UPDATE `panels` SET `status` = 'inactive' WHERE `code` = '$code'");
        }else{
            $sql->query("UPDATE `panels` SET `status` = 'active' WHERE `code` = '$code'");
        }
        $key[] = [['text' => '▫️ Статус', 'callback_data' => 'null'], ['text' => '▫️ Название', 'callback_data' => 'null'], ['text' => '▫️ Код отслеживания', 'callback_data' => 'null']];
        $result = $sql->query("SELECT * FROM `panels`");
        while($row = $result->fetch_array()){
            $name = $row['name'];
            $code = $row['code'];
            if($row['status'] == 'active') $status = '✅ Активно'; else $status = '❌ Неактивно';
            $key[] = [['text' => $status, 'callback_data' => 'change_status_panel-'.$code], ['text' => $name, 'callback_data' => 'status_panel-'.$code], ['text' => $code, 'callback_data' => 'status_panel-'.$code]];
        }
        $key[] = [['text' => '❌ Закрыть панель | Закрыть панель', 'callback_data' => 'close_panel']];
        $key = json_encode(['inline_keyboard' => $key]);
        editMessage($from_id, "🔎 Список ваших зарегистрированных серверов:\n\nℹ️ Чтобы управлять каждым из них, нажмите на него.", $message_id, $key);
    }
    
    
    elseif (strpos($data, 'status_panel-') !== false or strpos($data, 'update_panel-') !== false) {
        alert('🔄 - Пожалуйста, подождите несколько секунд, идет получение информации...', false);
    
        $code = explode('-', $data)[1];
        $info_server = $sql->query("SELECT * FROM `panels` WHERE `code` = '$code'")->fetch_assoc();
    
        if ($info_server['status'] == 'active') $status = '✅ Активный'; else $status = '❌ Неактивный';
        if (strpos($info_server['login_link'], 'https://') !== false) $status_ssl = '✅ Активен'; else $status_ssl = '❌ Неактивен';
    
        $info = [
            'ip' => explode(':', str_replace(['http://', 'https://'], '', $info_server['login_link']))[0] ?? '⚠️',
            'port' => explode(':', str_replace(['http://', 'https://'], '', $info_server['login_link']))[1] ?? '⚠️',
            'type' => ($info_server['type'] == 'marzban') ? 'Марзбан' : 'Санайи',
        ];
    
        $txt = "Информация о панели [ <b>{$info_server['name']}</b> ] успешно получена.\n\n🔎 Текущий статус в боте: <b>$status</b>\nℹ️ Код сервера (для информации): <code>$code</code>\n\n◽️ Тип панели: <b>{$info['type']}</b>\n◽️ Локация: <b>{$info_server['name']}</b>\n◽️ IP-адрес: <code>{$info['ip']}</code>\n◽️ Порт: <code>{$info['port']}</code>\n◽️ Статус SSL: <b>$status_ssl</b>\n\n🔑 Имя пользователя панели: <code>{$info_server['username']}</code>\n🔑 Пароль панели: <code>{$info_server['password']}</code>";
    
        $protocols = explode('|', $info_server['protocols']);
        unset($protocols[count($protocols)-1]);
        if (in_array('vmess', $protocols)) $vmess_status = '✅'; else $vmess_status = '❌';
        if (in_array('trojan', $protocols)) $trojan_status = '✅'; else $trojan_status = '❌';
        if (in_array('vless', $protocols)) $vless_status = '✅'; else $vless_status = '❌';
        if (in_array('shadowsocks', $protocols)) $shadowsocks_status = '✅'; else $shadowsocks_status = '❌';
    
        if ($info_server['type'] == 'marzban') {
            $back_panellist = json_encode(['inline_keyboard' => [
                [['text' => '🆙 Обновить информацию', 'callback_data' => 'update_panel-' . $code]],
                [['text' => '🔎 - Статус :', 'callback_data' => 'null'], ['text' => $info_server['status'] == 'active' ? '✅' : '❌', 'callback_data' => 'change_status_panel-' . $code]],
                [['text' => '🎯 - Поток :', 'callback_data' => 'null'], ['text' => $info_server['flow'] == 'flowon' ? '✅' : '❌', 'callback_data' => 'change_status_flow-' . $code]],
                [['text' => '🗑 Удалить панель', 'callback_data' => 'delete_panel-' . $code], ['text' => '✍️ Изменить имя', 'callback_data' => 'change_name_panel-' . $code]],
                [['text' => 'vmess - [' . $vmess_status . ']', 'callback_data' => 'change_protocol|vmess-' . $code], ['text' => 'trojan [' . $trojan_status . ']', 'callback_data' => 'change_protocol|trojan-' . $code], ['text' => 'vless [' . $vless_status . ']', 'callback_data' => 'change_protocol|vless-' . $code]],
                [['text' => 'shadowsocks [' . $shadowsocks_status . ']', 'callback_data' => 'change_protocol|shadowsocks-' . $code]],
                [['text' => 'ℹ️ Управление исходящими', 'callback_data' => 'manage_marzban_inbound-'.$code], ['text' => '⏺ Настроить исходящий', 'callback_data' => 'set_inbound_marzban-'.$code]],
                [['text' => '🔙 Вернуться к списку панелей', 'callback_data' => 'back_panellist']],
            ]]);
        } elseif ($info_server['type'] == 'sanayi') {
            $back_panellist = json_encode(['inline_keyboard' => [
                [['text' => '🆙 Обновить информацию', 'callback_data' => 'update_panel-' . $code]],
                [['text' => '🔎 - Статус :', 'callback_data' => 'null'], ['text' => $info_server['status'] == 'active' ? '✅' : '❌', 'callback_data' => 'change_status_panel-' . $code]],
                [['text' => '🗑 Удалить панель', 'callback_data' => 'delete_panel-' . $code], ['text' => '✍️ Изменить имя', 'callback_data' => 'change_name_panel-' . $code]],
                [['text' => '🆔 Настроить исходящий для создания сервиса', 'callback_data' => 'set_inbound_sanayi-'.$code]],
                [['text' => '🌐 Настроить пример ссылки (сервис) для доставки', 'callback_data' => 'set_example_link_sanayi-'.$code]],
                [['text' => 'vmess - [' . $vmess_status . ']', 'callback_data' => 'change_protocol|vmess-' . $code], ['text' => 'trojan [' . $trojan_status . ']', 'callback_data' => 'change_protocol|trojan-' . $code], ['text' => 'vless [' . $vless_status . ']', 'callback_data' => 'change_protocol|vless-' . $code]],
                [['text' => 'shadowsocks [' . $shadowsocks_status . ']', 'callback_data' => 'change_protocol|shadowsocks-' . $code]],
                [['text' => '🔙 Вернуться к списку панелей', 'callback_data' => 'back_panellist']],
            ]]);
        }
        editMessage($from_id, $txt, $message_id, $back_panellist);
    }
    
    elseif (strpos($data, 'set_inbound_marzban') !== false) {
        $code = explode('-', $data)[1];
        step('send_inbound_marzban-'.$code);
        sendMessage($from_id, "🆕 Пожалуйста, отправьте желаемое имя исходящего:\n\n❌ Обратите внимание, если вы введете неверное имя исходящего, это может вызвать ошибку при создании сервиса. Также, введенный вами исходящий должен соответствовать протоколу, который вы активировали для этой панели в боте.", $back_panel);
    }
    
    elseif (strpos($user['step'], 'send_inbound_marzban') !== false and $text != '✔ Завершить и сохранить') {
        $code = explode('-', $user['step'])[1];
        $rand_code = rand(111111, 999999);
        $panel_fetch = $sql->query("SELECT * FROM `panels` WHERE `code` = '$code'")->fetch_assoc();
        $token = loginPanel($panel_fetch['login_link'], $panel_fetch['username'], $panel_fetch['password'])['access_token'];
        $inbounds = inbounds($token, $panel_fetch['login_link']);
        $status = checkInbound(json_encode($inbounds), $text);
        if ($status) {
            $res = $sql->query("INSERT INTO `marzban_inbounds` (`panel`, `inbound`, `code`, `status`) VALUES ('$code', '$text', '$rand_code', 'active')");
            sendMessage($from_id, "✅ Ваш исходящий успешно настроен.\n\n#️⃣ Если вы хотите отправить новый исходящий, отправьте его, иначе отправьте команду /end_inbound или нажмите на кнопку ниже.", $end_inbound);
        } else {
            sendMessage($from_id, "🔴 Ваш исходящий не найден!", $end_inbound);
        }
    }
    

    elseif (($text == '✔ Завершить и сохранить' or $text == '/end_inbound') and strpos($user['step'], 'send_inbound_marzban') !== false) {
        step('none');
        sendMessage($from_id, "✅ Все ваши отправленные исходящие сохранены.", $manage_server);
    }
    
    elseif (strpos($data, 'manage_marzban_inbound') !== false) {
        $panel_code = explode('-', $data)[1];
        $fetch_inbounds = $sql->query("SELECT * FROM `marzban_inbounds` WHERE `panel` = '$panel_code'");
        if ($fetch_inbounds->num_rows > 0) {
            while ($row = $fetch_inbounds->fetch_assoc()) {
                $key[] = [['text' => $row['inbound'], 'callback_data' => 'null'], ['text' => '🗑', 'callback_data' => 'delete_marzban_inbound-'.$row['code'].'-'.$panel_code]];
            }
            $key[] = [['text' => '🔙 Назад', 'callback_data' => 'status_panel-'.$panel_code]];
            $key = json_encode(['inline_keyboard' => $key]);
            editMessage($from_id, "🔎 Список всех зарегистрированных исходящих для этой панели, отправленных вами, приведен ниже!", $message_id, $key);
        } else {
            alert('❌ Для этой панели не настроен ни один исходящий!', true);
        }
    }
    
    elseif (strpos($data, 'delete_marzban_inbound') !== false) {
        $panel_code = explode('-', $data)[2];
        $inbound_code = explode('-', $data)[1];
        $fetch = $sql->query("SELECT * FROM `marzban_inbounds` WHERE `panel` = '$panel_code'");
        if ($fetch->num_rows > 0) {
            alert('✅ Вы успешно удалили выбранный исходящий из базы данных бота.', true);
            $sql->query("DELETE FROM `marzban_inbounds` WHERE `panel` = '$panel_code' AND `code` = '$inbound_code'");
            $key = json_encode(['inline_keyboard' => [[['text' => '🔎', 'callback_data' => 'manage_marzban_inbound-'.$panel_code]]]]);
            editMessage($from_id, "⬅️ Чтобы вернуться к списку исходящих, нажмите кнопку ниже!", $message_id, $key);
        } else {
            alert('❌ Такой исходящий не найден в базе данных бота!', true);
        }
    }
        

    elseif (strpos($data, 'set_inbound_sanayi') !== false) {
        $code = explode('-', $data)[1];
        step('send_inbound_id-'.$code);
        sendMessage($from_id, "👇 Please send the parent service ID where clients will be added: (ID)", $back_panel);
    }
    
    elseif (strpos($user['step'], 'send_inbound_id') !== false) {
        if (is_numeric($text)) {
            $code = explode('-', $user['step'])[1];
            $info_panel = $sql->query("SELECT * FROM `panels` WHERE `code` = '$code'")->fetch_assoc();
            include_once 'api/sanayi.php';
            $xui = new Sanayi($info_panel['login_link'], $info_panel['token']);
            $id_status = json_decode($xui->checkId($text), true)['status'];
            if ($id_status == true) {
                step('none');
                if ($sql->query("SELECT * FROM `sanayi_panel_setting` WHERE `code` = '$code'")->num_rows > 0) {
                    $sql->query("UPDATE `sanayi_panel_setting` SET `inbound_id` = '$text' WHERE `code` = '$code'");
                } else {
                    $sql->query("INSERT INTO `sanayi_panel_setting` (`code`, `inbound_id`, `example_link`, `flow`) VALUES ('$code', '$text', 'none', 'offflow')");
                }
                sendMessage($from_id, "✅ Successfully configured!", $manage_server);
            } else {
                sendMessage($from_id, "❌ Inbound ID <code>$text</code> not found!", $back_panel);
            }
        } else {
            sendMessage($from_id, "❌ Input value must be numeric only!", $back_panel);
        }
    }
    
    
    elseif (strpos($data, 'set_example_link_sanayi') !== false) {
        $code = explode('-', $data)[1];
        step('set_example_link_sanayi-'.$code);
        sendMessage($from_id, "✏️ Отправьте пример вашей услуги, учитывая следующие инструкции:\n\n▫️ Замените значения s1, %s2 и ...% в отправленной ссылке на услугу.\n\nНапример, полученная ссылка:\n\n<code>vless://a8eff4a8-226d3343bbf-9e9d-a35f362c4cb4@1.1.1.1:2053?security=reality&type=grpc&host=&headerType=&serviceName=xyz&sni=cdn.discordapp.com&fp=chrome&pbk=SbVKOEMjK0sIlbwg4akyBg5mL5KZwwB-ed4eEE7YnRc&sid=&spx=#Proxygram</code>\n\nА ваша отправленная ссылка боту должна выглядеть следующим образом (Примерно):\n\n<code>vless://%s1@%s2?security=reality&type=grpc&host=&headerType=&serviceName=xyz&sni=cdn.discordapp.com&fp=chrome&pbk=SbVKOEMjK0sIlbwg4akyBg5mL5KZwwB-ed4eEE7YnRc&sid=&spx=#%s3</code>\n\n⚠️ Пожалуйста, отправьте это правильно, в противном случае бот столкнется с ошибкой при покупке услуги.", $back_panel);
    }
    
    elseif (strpos($user['step'], 'set_example_link_sanayi') !== false) {
        if (strpos($text, '%s1') !== false and strpos($text, '%s3') !== false) {
            step('none');
            $code = explode('-', $user['step'])[1];
            if ($sql->query("SELECT * FROM `sanayi_panel_setting` WHERE `code` = '$code'")->num_rows > 0) {
                $sql->query("UPDATE `sanayi_panel_setting` SET `example_link` = '$text' WHERE `code` = '$code'");
            } else {
                $sql->query("INSERT INTO `sanayi_panel_setting` (`code`, `inbound_id`, `example_link`, `flow`) VALUES ('$code', 'none', '$text', 'offflow')");
            }
            sendMessage($from_id, "✅ Успешно настроено!", $manage_server);
        } else {
            sendMessage($from_id, "❌ Пример ссылки, который вы отправили, неверен!", $back_panel);
        }
    }
    
    
    elseif (strpos($data, 'change_status_flow-') !== false) {
        $code = explode('-', $data)[1];
        $info_panel = $sql->query("SELECT * FROM `panels` WHERE `code` = '$code'");
        $status = $info_panel->fetch_assoc()['flow'];
        if ($status == 'flowon') {
            $sql->query("UPDATE `panels` SET `flow` = 'flowoff' WHERE `code` = '$code'");
        } else {
            $sql->query("UPDATE `panels` SET `flow` = 'flowon' WHERE `code` = '$code'");
        }
        $back = json_encode(['inline_keyboard' => [[['text' => '🆙 Обновить информацию', 'callback_data' => 'update_panel-'.$code]]]]);
        editmessage($from_id, '✅ Изменения успешно выполнены.', $message_id, $back);
    }
    
    elseif (strpos($data, 'change_protocol|') !== false) {
        $code = explode('-', $data)[1];
        $protocol = explode('-', explode('|', $data)[1])[0];
        $panel = $sql->query("SELECT * FROM `panels` WHERE `code` = '$code' LIMIT 1")->fetch_assoc();
        $protocols = explode('|', $panel['protocols']);
        unset($protocols[count($protocols) - 1]);
        
        if ($protocol == 'vless' || $protocol == 'vmess' || $protocol == 'trojan' || $protocol == 'shadowsocks') {
            if (in_array($protocol, $protocols)) {
                unset($protocols[array_search($protocol, $protocols)]);
            } else {
                array_push($protocols, $protocol);
            }
        }
        
        $protocols = join('|', $protocols) . '|';
        $sql->query("UPDATE `panels` SET `protocols` = '$protocols' WHERE `code` = '$code' LIMIT 1");
        
        $back = json_encode(['inline_keyboard' => [[['text' => '🆙 Обновить информацию', 'callback_data' => 'update_panel-'.$code]]]]);
        editmessage($from_id, '✅ Статус протокола успешно изменен.', $message_id, $back);
    }
    
    
    elseif (strpos($data, 'change_name_panel-') !== false) {
        $code = explode('-', $data)[1];
        step('change_name-'.$code);
        sendMessage($from_id, "🔰Введите новое имя для панели:", $back_panel);
    }
    
    elseif (strpos($user['step'], 'change_name-') !== false) {
        $code = explode('-', $user['step'])[1];
        step('none');
        $sql->query("UPDATE `panels` SET `name` = '$text' WHERE `code` = '$code'");
        sendMessage($from_id, "✅ Имя панели успешно изменено на [ <b>$text</b> ].", $back_panellist);
    }
    
    elseif (strpos($data, 'delete_panel-') !== false) {
        step('none');
        $code = explode('-', $data)[1];
        $sql->query("DELETE FROM `panels` WHERE `code` = '$code'");
        $info_servers = $sql->query("SELECT * FROM `panels`");
        if ($info_servers->num_rows == 0) {
            if (!isset($data)) {
                sendMessage($from_id, "❌ Нет зарегистрированных серверов в боте.");
            } else {
                editMessage($from_id, "❌ Нет зарегистрированных серверов в боте.", $message_id);
            }
            exit();
        }
        $key[] = [['text' => '▫️Статус', 'callback_data' => 'null'], ['text' => '▫️Имя', 'callback_data' => 'null'], ['text' => '▫️Код отслеживания', 'callback_data' => 'null']];
        while ($row = $info_servers->fetch_array()) {
            $name = $row['name'];
            $code = $row['code'];
            if ($row['status'] == 'active') $status = '✅ Активный'; else $status = '❌ Неактивный';
            $key[] = [['text' => $status, 'callback_data' => 'change_status_panel-'.$code], ['text' => $name, 'callback_data' => 'status_panel-'.$code], ['text' => $code, 'callback_data' => 'status_panel-'.$code]];
        }
        $key[] = [['text' => '❌ Закрыть панель', 'callback_data' => 'close_panel']];
        $key = json_encode(['inline_keyboard' => $key]);
        if (!isset($data)) {
            sendMessage($from_id, "🔎 Список ваших зарегистрированных серверов:\n\n⚙️ Нажмите на код отслеживания сервера, чтобы перейти в раздел управления сервером.\n\nℹ️ Для управления каждым из них щелкните по нему.", $key);
        } else {
            editMessage($from_id, "🔎 Список ваших зарегистрированных серверов:\n\n⚙️ Нажмите на код отслеживания сервера, чтобы перейти в раздел управления сервером.\n\nℹ️ Для управления каждым из них щелкните по нему.", $message_id, $key);
        }
    }
    
    
    elseif ($text == '⚙️ Управление планами' or $data == 'back_cat') {
        step('manage_plans');
        if ($text) {
            sendMessage($from_id, "ℹ️ Какую категорию вы хотите управлять?\n\n👇🏻 Выберите один из вариантов:", $manage_plans);
        } else {
            editMessage($from_id, "ℹ️ Какую категорию вы хотите управлять?\n\n👇🏻 Выберите один из вариантов:", $message_id, $manage_plans);
        }
    }
    
    elseif ($data == 'manage_main_plan') {
        step('manage_main_plan');
        $count = $sql->query("SELECT * FROM `category`")->num_rows;
        if ($count == 0) {
            if (isset($data)) {
                editmessage($from_id, "❌ Список категорий пуст.", $message_id);
                exit();
            } else {
                sendmessage($from_id, "❌ Список категорий пуст.", $manage_server);
                exit();
            }
        }
        $result = $sql->query("SELECT * FROM `category`");
        $button[] = [['text' => 'Удалить', 'callback_data' => 'null'], ['text' => 'Статус', 'callback_data' => 'null'], ['text' => 'Имя', 'callback_data' => 'null'], ['text' => 'Информация', 'callback_data' => 'null']];
        while ($row = $result->fetch_array()) {
            $status = $row['status'] == 'active' ? '✅' : '❌';
            $button[] = [['text' => '🗑', 'callback_data' => 'delete_limit-'.$row['code']], ['text' => $status, 'callback_data' => 'change_status_cat-'.$row['code']], ['text' => $row['name'], 'callback_data' => 'manage_list-'.$row['code']], ['text' => '👁', 'callback_data' => 'manage_cat-'.$row['code']]];
        }
        $button = json_encode(['inline_keyboard' => $button]);
        $count = $result->num_rows;
        $count_active = $sql->query("SELECT * FROM `category` WHERE `status` = 'active'")->num_rows;
        if (isset($data)) {
            editmessage($from_id, "🔰Вот список ваших категорий:\n\n🔢 Всего: <code>$count</code>\n🔢 Активных: <code>$count_active</code>", $message_id, $button);
        } else {
            sendMessage($from_id, "🔰Вот список ваших категорий:\n\n🔢 Всего: <code>$count</code>\n🔢 Активных: <code>$count_active</code>", $button);
        }
    }
    

    elseif ($data == 'manage_limit_plan') {
    step('manage_limit_plan');
    $count = $sql->query("SELECT * FROM `category_limit`")->num_rows;
    if ($count == 0) {
        if (isset($data)) {
            editmessage($from_id, "❌ Список планов пуст.", $message_id);
            exit();
        } else {
            sendmessage($from_id, "❌ Список планов пуст.", $manage_server);
            exit();
        }
    }
    $result = $sql->query("SELECT * FROM `category_limit`");
    $button[] = [['text' => 'Удалить', 'callback_data' => 'null'], ['text' => 'Статус', 'callback_data' => 'null'], ['text' => 'Имя', 'callback_data' => 'null'], ['text' => 'Информация', 'callback_data' => 'null']];
    while ($row = $result->fetch_array()) {
        $status = $row['status'] == 'active' ? '✅' : '❌';
        $button[] = [['text' => '🗑', 'callback_data' => 'delete_limit_limit-'.$row['code']], ['text' => $status, 'callback_data' => 'change_status_cat_limit-'.$row['code']], ['text' => $row['name'], 'callback_data' => 'manage_list_limit-'.$row['code']], ['text' => '👁', 'callback_data' => 'manage_cat_limit-'.$row['code']]];
    }
    $button = json_encode(['inline_keyboard' => $button]);
    $count = $result->num_rows;
    $count_active = $sql->query("SELECT * FROM `category_limit` WHERE `status` = 'active'")->num_rows;
    if (isset($data)) {
        editmessage($from_id, "🔰Вот список ваших категорий:\n\n🔢 Всего: <code>$count</code>\n🔢 Активных: <code>$count_active</code>", $message_id, $button);
    } else {
        sendMessage($from_id, "🔰Вот список ваших категорий:\n\n🔢 Всего: <code>$count</code>\n🔢 Активных: <code>$count_active</code>", $button);
    }
    }

    elseif ($data == 'manage_date_plan') {
        step('manage_date_plan');
        $count = $sql->query("SELECT * FROM `category_date`")->num_rows;
        if ($count == 0) {
            if (isset($data)) {
                editmessage($from_id, "❌ Список планов пуст.", $message_id);
                exit();
            } else {
                sendmessage($from_id, "❌ Список планов пуст.", $manage_server);
                exit();
            }
        }
        $result = $sql->query("SELECT * FROM `category_date`");
        $button[] = [['text' => 'Удалить', 'callback_data' => 'null'], ['text' => 'Статус', 'callback_data' => 'null'], ['text' => 'Имя', 'callback_data' => 'null'], ['text' => 'Информация', 'callback_data' => 'null']];
        while ($row = $result->fetch_array()) {
            $status = $row['status'] == 'active' ? '✅' : '❌';
            $button[] = [['text' => '🗑', 'callback_data' => 'delete_limit_date-'.$row['code']], ['text' => $status, 'callback_data' => 'change_status_cat_date-'.$row['code']], ['text' => $row['name'], 'callback_data' => 'manage_list_date-'.$row['code']], ['text' => '👁', 'callback_data' => 'manage_cat_date-'.$row['code']]];
        }
        $button = json_encode(['inline_keyboard' => $button]);
        $count = $result->num_rows;
        $count_active = $sql->query("SELECT * FROM `category_date` WHERE `status` = 'active'")->num_rows;
        if (isset($data)) {
            editmessage($from_id, "🔰Вот список ваших категорий:\n\n🔢 Всего: <code>$count</code> \n🔢 Активных: <code>$count_active</code>", $message_id, $button);
        } else {
            sendMessage($from_id, "🔰Вот список ваших категорий:\n\n🔢 Всего: <code>$count</code> \n🔢 Активных: <code>$count_active</code>", $button);
        }
    }

    elseif (strpos($data, 'change_status_cat-') !== false) {
        $code = explode('-', $data)[1];
        $info_cat = $sql->query("SELECT * FROM `category` WHERE `code` = '$code' LIMIT 1");
        $status = $info_cat->fetch_assoc()['status'];
        if ($status == 'active') {
            $sql->query("UPDATE `category` SET `status` = 'inactive' WHERE `code` = '$code'");
        } else {
            $sql->query("UPDATE `category` SET `status` = 'active' WHERE `code` = '$code'");
        }
        $button[] = [['text' => 'Удалить', 'callback_data' => 'null'], ['text' => 'Статус', 'callback_data' => 'null'], ['text' => 'Имя', 'callback_data' => 'null'], ['text' => 'Информация', 'callback_data' => 'null']];
        $result = $sql->query("SELECT * FROM `category`");
        while ($row = $result->fetch_array()) {
            $status = $row['status'] == 'active' ? '✅' : '❌';
            $button[] = [['text' => '🗑', 'callback_data' => 'delete_limit-'.$row['code']], ['text' => $status, 'callback_data' => 'change_status_cat-'.$row['code']], ['text' => $row['name'], 'callback_data' => 'manage_list-'.$row['code']], ['text' => '👁', 'callback_data' => 'manage_cat-'.$row['code']]];
        }
        $button = json_encode(['inline_keyboard' => $button]);
        $count = $result->num_rows;
        $count_active = $sql->query("SELECT * FROM `category` WHERE `status` = 'active'")->num_rows;
        if (isset($data)) {
            editmessage($from_id, "🔰Вот список ваших категорий:\n\n🔢 Всего: <code>$count</code> \n🔢 Активных: <code>$count_active</code>", $message_id, $button);
        } else {
            sendMessage($from_id, "🔰Вот список ваших категорий:\n\n🔢 Всего: <code>$count</code> \n🔢 Активных: <code>$count_active</code>", $button);
        }
    }


    elseif (strpos($data, 'change_status_cat_limit-') !== false) {
        $code = explode('-', $data)[1];
        $info_cat = $sql->query("SELECT * FROM `category_limit` WHERE `code` = '$code' LIMIT 1");
        $status = $info_cat->fetch_assoc()['status'];
        if ($status == 'active') {
            $sql->query("UPDATE `category_limit` SET `status` = 'inactive' WHERE `code` = '$code'");
        } else {
            $sql->query("UPDATE `category_limit` SET `status` = 'active' WHERE `code` = '$code'");
        }
        $button[] = [['text' => 'حذف', 'callback_data' => 'null'], ['text' => 'وضعیت', 'callback_data' => 'null'], ['text' => 'نام', 'callback_data' => 'null'], ['text' => 'اطلاعات', 'callback_data' => 'null']];
        $result = $sql->query("SELECT * FROM `category_limit`");
        while ($row = $result->fetch_array()) {
            $status = $row['status'] == 'active' ? '✅' : '❌';
            $button[] = [['text' => '🗑', 'callback_data' => 'delete_limit_limit-'.$row['code']], ['text' => $status, 'callback_data' => 'change_status_cat_limit-'.$row['code']], ['text' => $row['name'], 'callback_data' => 'manage_list_limit-'.$row['code']], ['text' => '👁', 'callback_data' => 'manage_cat_limit-'.$row['code']]];
        }
        $button = json_encode(['inline_keyboard' => $button]);
        $count = $result->num_rows;
        $count_active = $sql->query("SELECT * FROM `category_limit` WHERE `status` = 'active'")->num_rows;
        if (isset($data)) {
            editmessage($from_id, "🔰Список ваших категорий следующий:\n\n🔢 Всего: <code>$count</code> категорий\n🔢 Активных: <code>$count_active</code> категорий", $message_id, $button);
        } else {
            sendMessage($from_id, "🔰Список ваших категорий следующий:\n\n🔢 Всего: <code>$count</code> категорий\n🔢 Активных: <code>$count_active</code> категорий", $button);
        }
    }


    elseif (strpos($data, 'change_status_cat_date-') !== false) {
        $code = explode('-', $data)[1];
        $info_cat = $sql->query("SELECT * FROM `category_date` WHERE `code` = '$code' LIMIT 1");
        $status = $info_cat->fetch_assoc()['status'];
        if ($status == 'active') {
            $sql->query("UPDATE `category_date` SET `status` = 'inactive' WHERE `code` = '$code'");
        } else {
            $sql->query("UPDATE `category_date` SET `status` = 'active' WHERE `code` = '$code'");
        }
        $button[] = [['text' => 'حذف', 'callback_data' => 'null'], ['text' => 'وضعیت', 'callback_data' => 'null'], ['text' => 'نام', 'callback_data' => 'null'], ['text' => 'اطلاعات', 'callback_data' => 'null']];
        $result = $sql->query("SELECT * FROM `category_date`");
        while ($row = $result->fetch_array()) {
            $status = $row['status'] == 'active' ? '✅' : '❌';
            $button[] = [['text' => '🗑', 'callback_data' => 'delete_limit_date-'.$row['code']], ['text' => $status, 'callback_data' => 'change_status_cat_date-'.$row['code']], ['text' => $row['name'], 'callback_data' => 'manage_list_date-'.$row['code']], ['text' => '👁', 'callback_data' => 'manage_cat_date-'.$row['code']]];
        }
        $button = json_encode(['inline_keyboard' => $button]);
        $count = $result->num_rows;
        $count_active = $sql->query("SELECT * FROM `category_date` WHERE `status` = 'active'")->num_rows;
        if (isset($data)) {
            editmessage($from_id, "🔰Список ваших категорий следующий:\n\n🔢 Всего: <code>$count</code> категорий\n🔢 Активных: <code>$count_active</code> категорий", $message_id, $button);
        } else {
            sendMessage($from_id, "🔰Список ваших категорий следующий:\n\n🔢 Всего: <code>$count</code> категорий\n🔢 Активных: <code>$count_active</code> категорий", $button);
        }
    }
    
    
    elseif (strpos($data, 'delete_limit-') !== false) {
        $code = explode('-', $data)[1];
        $sql->query("DELETE FROM `category` WHERE `code` = '$code' LIMIT 1");
        $count = $sql->query("SELECT * FROM `category`")->num_rows;
        if ($count == 0) {
            editmessage($from_id, "❌ Список планов пуст.", $message_id);
            exit();
        }
        $result = $sql->query("SELECT * FROM `category`");
        while ($row = $result->fetch_array()) {
            $button[] = [['text' => '🗑', 'callback_data' => 'delete_limit-'.$code], ['text' => $row['name'], 'callback_data' => 'manage_list-'.$row['code']]];
        }
        $button = json_encode(['inline_keyboard' => $button]);
        $count = $result->num_rows;
        editmessage($from_id, "🔰Ваш список категорий следующий:\n\n🔢 Всего: <code>$count</code> категорий\n🔢 Активных: <code>$count_active</code> категорий", $message_id, $button);
    }
    
    elseif (strpos($data, 'delete_limit_limit-') !== false) {
        $code = explode('-', $data)[1];
        $sql->query("DELETE FROM `category_limit` WHERE `code` = '$code' LIMIT 1");
        $count = $sql->query("SELECT * FROM `category_limit`")->num_rows;
        if ($count == 0) {
            editmessage($from_id, "❌ Список планов пуст.", $message_id);
            exit();
        }
        $result = $sql->query("SELECT * FROM `category_limit`");
        while ($row = $result->fetch_array()) {
            $button[] = [['text' => '🗑', 'callback_data' => 'delete_limit_limit-'.$row['code']], ['text' => $status, 'callback_data' => 'change_status_cat_limit-'.$row['code']], ['text' => $row['name'], 'callback_data' => 'manage_list_limit-'.$row['code']], ['text' => '👁', 'callback_data' => 'manage_cat_limit-'.$row['code']]];
        }
        $button = json_encode(['inline_keyboard' => $button]);
        $count = $result->num_rows;
        editmessage($from_id, "🔰Ваш список категорий следующий:\n\n🔢 Всего: <code>$count</code> категорий\n🔢 Активных: <code>$count_active</code> категорий", $message_id, $button);
    }
    
    elseif (strpos($data, 'delete_limit_date-') !== false) {
        $code = explode('-', $data)[1];
        $sql->query("DELETE FROM `category_date` WHERE `code` = '$code' LIMIT 1");
        $count = $sql->query("SELECT * FROM `category_date`")->num_rows;
        if ($count == 0) {
            editmessage($from_id, "❌ Список планов пуст.", $message_id);
            exit();
        }
        $result = $sql->query("SELECT * FROM `category_date`");
        while ($row = $result->fetch_array()) {
            $button[] = [['text' => '🗑', 'callback_data' => 'delete_limit_date-'.$row['code']], ['text' => $status, 'callback_data' => 'change_status_cat_date-'.$row['code']], ['text' => $row['name'], 'callback_data' => 'manage_list_date-'.$row['code']], ['text' => '👁', 'callback_data' => 'manage_cat_date-'.$row['code']]];
        }
        $button = json_encode(['inline_keyboard' => $button]);
        $count = $result->num_rows;
        editmessage($from_id, "🔰Ваш список категорий следующий:\n\n🔢 Всего: <code>$count</code> категорий\n🔢 Активных: <code>$count_active</code> категорий", $message_id, $button);
    }
    
    elseif (strpos($data, 'manage_list-') !== false) {
        $code = explode('-', $data)[1];
        $res = $sql->query("SELECT * FROM `category` WHERE `code` = '$code'")->fetch_assoc();
        alert($res['name']);
    }
    
    elseif (strpos($data, 'manage_list_limit-') !== false) {
        $code = explode('-', $data)[1];
        $res = $sql->query("SELECT * FROM `category_limit` WHERE `code` = '$code'")->fetch_assoc();
        alert($res['name']);
    }    

    elseif (strpos($data, 'manage_list_date-') !== false) {
        $code = explode('-', $data)[1];
        $res = $sql->query("SELECT * FROM `category_date` WHERE `code` = '$code'")->fetch_assoc();
        alert($res['name']);
    }
    
    elseif (strpos($data, 'manage_cat-') !== false) {
        $code = explode('-', $data)[1];
        $res = $sql->query("SELECT * FROM `category` WHERE `code` = '$code'")->fetch_assoc();
        $key = json_encode(['inline_keyboard' => [
            [['text' => 'Дата', 'callback_data' => 'null'], ['text' => 'Лимит', 'callback_data' => 'null'], ['text' => 'Цена', 'callback_data' => 'null'], ['text' => 'Имя', 'callback_data' => 'null']],
            [['text' => $res['date'], 'callback_data' => 'change_date-'.$res['code']], ['text' => $res['limit'], 'callback_data' => 'change_limit-'.$res['code']], ['text' => $res['price'], 'callback_data' => 'change_price-'.$res['code']], ['text' => '✏️', 'callback_data' => 'change_name-'.$res['code']]],
            [['text' => '⬅️ Назад', 'callback_data' => 'back_cat']],
        ]]);
        editmessage($from_id, "🌐 Информация о плане успешно получена.\n\n▫️Название плана: <b>{$res['name']}</b>\n▫️Лимит: <code>{$res['limit']}</code>\n▫️Дата: <code>{$res['date']}</code>\n▫️Цена: <code>{$res['price']}</code>\n\n📎 Выберите опцию для изменения!", $message_id, $key);
    }
    
    elseif (strpos($data, 'manage_cat_date-') !== false) {
        $code = explode('-', $data)[1];
        $res = $sql->query("SELECT * FROM `category_date` WHERE `code` = '$code'")->fetch_assoc();
        $key = json_encode(['inline_keyboard' => [
            [['text' => 'Дата', 'callback_data' => 'null'], ['text' => 'Цена', 'callback_data' => 'null'], ['text' => 'Имя', 'callback_data' => 'null']],
            [['text' => $res['date'], 'callback_data' => 'change_date_date-'.$res['code']], ['text' => $res['price'], 'callback_data' => 'change_price_date-'.$res['code']], ['text' => '✏️', 'callback_data' => 'change_name_date-'.$res['code']]],
            [['text' => '⬅️ Назад', 'callback_data' => 'back_cat']],
        ]]);
        editmessage($from_id, "🌐 Информация о плане успешно получена.\n\n▫️Название плана: <b>{$res['name']}</b>\n▫️Дата: <code>{$res['date']}</code>\n▫️Цена: <code>{$res['price']}</code>\n\n📎 Выберите опцию для изменения!", $message_id, $key);
    }
    
    elseif (strpos($data, 'manage_cat_limit-') !== false) {
        $code = explode('-', $data)[1];
        $res = $sql->query("SELECT * FROM `category_limit` WHERE `code` = '$code'")->fetch_assoc();
        $key = json_encode(['inline_keyboard' => [
            [['text' => 'Лимит', 'callback_data' => 'null'], ['text' => 'Цена', 'callback_data' => 'null'], ['text' => 'Имя', 'callback_data' => 'null']],
            [['text' => $res['limit'], 'callback_data' => 'change_limit_limit-'.$res['code']], ['text' => $res['price'], 'callback_data' => 'change_price_limit-'.$res['code']], ['text' => '✏️', 'callback_data' => 'change_name_limit-'.$res['code']]],
            [['text' => '⬅️ Назад', 'callback_data' => 'back_cat']],
        ]]);
        editmessage($from_id, "🌐 Информация о плане успешно получена.\n\n▫️Название плана: <b>{$res['name']}</b>\n▫️Лимит: <code>{$res['limit']}</code>\n▫️Цена: <code>{$res['price']}</code>\n\n📎 Выберите опцию для изменения!", $message_id, $key);
    }
    
    elseif (strpos($data, 'change_date-') !== false) {
        $code = explode('-', $data)[1];
        step('change_date-'.$code);
        sendMessage($from_id, "🔰Введите новое значение в виде целого числа:", $back_panel);
    }
    
    elseif (strpos($data, 'change_date_date-') !== false) {
        $code = explode('-', $data)[1];
        step('change_date_date-'.$code);
        sendMessage($from_id, "🔰Введите новое значение в виде целого числа:", $back_panel);
    }
    
    elseif (strpos($data, 'change_limit-') !== false) {
        $code = explode('-', $data)[1];
        step('change_limit-'.$code);
        sendMessage($from_id, "🔰Введите новое значение в виде целого числа:", $back_panel);
    }
    

    elseif (strpos($data, 'change_limit_limit-') !== false) {
        $code = explode('-', $data)[1];
        step('change_limit_limit-'.$code);
        sendMessage($from_id, "🔰Введите новое значение в виде целого числа:", $back_panel);
    }
    
    elseif (strpos($data, 'change_price-') !== false) {
        $code = explode('-', $data)[1];
        step('change_price-'.$code);
        sendMessage($from_id, "🔰Введите новое значение в виде целого числа:", $back_panel);
    }

    elseif (strpos($data, 'change_price_date-') !== false) {
        $code = explode('-', $data)[1];
        step('change_price_date-'.$code);
        sendMessage($from_id, "🔰Введите новое значение в виде целого числа::", $back_panel);
    }

    elseif (strpos($data, 'change_price_limit-') !== false) {
        $code = explode('-', $data)[1];
        step('change_price_limit-'.$code);
        sendMessage($from_id, "🔰Введите новое значение в виде целого числа:", $back_panel);
    }
    
    // Если пользователь запросил изменить имя в различных категориях
    elseif (strpos($data, 'change_name-') !== false) {
        $code = explode('-', $data)[1];
        step('change_namee-'.$code);
        sendMessage($from_id, "🔰Отправьте новое имя:", $back_panel);
    }

    elseif (strpos($data, 'change_name_date-') !== false) {
        $code = explode('-', $data)[1];
        step('change_name_date-'.$code);
        sendMessage($from_id, "🔰Отправьте новое имя:", $back_panel);
    }

    elseif (strpos($data, 'change_name_limit-') !== false) {
        $code = explode('-', $data)[1];
        step('change_name_limit-'.$code);
        sendMessage($from_id, "🔰Отправьте новое имя:", $back_panel);
    }

    // Обработка изменения даты для категорий, если пользователь не выбрал "⬅️ Назад к управлению"
    elseif (strpos($user['step'], 'change_date-') !== false and $text != '⬅️ Назад к управлению') {
        $code = explode('-', $user['step'])[1];
        step('none');
        $sql->query("UPDATE `category` SET `date` = '$text' WHERE `code` = '$code' LIMIT 1");
        sendMessage($from_id, "✅ Ваши данные успешно отправлены.", $manage_server);
    }


    // Если пользователь запросил изменение даты для категорий, но не выбрал "⬅️ Назад к управлению"
    elseif (strpos($user['step'], 'change_date_date-') !== false and $text != '⬅️ Назад к управлению') {
        $code = explode('-', $user['step'])[1];
        step('none');
        $sql->query("UPDATE `category_date` SET `date` = '$text' WHERE `code` = '$code' LIMIT 1");
        sendMessage($from_id, "✅ Ваши данные успешно отправлены.", $manage_server);
    }

    // Если пользователь запросил изменение ограничения, но не выбрал "⬅️ Назад к управлению"
    elseif (strpos($user['step'], 'change_limit-') !== false and $text != '⬅️ Назад к управлению') {
        $code = explode('-', $user['step'])[1];
        step('none');
        $sql->query("UPDATE `category` SET `limit` = '$text' WHERE `code` = '$code' LIMIT 1");
        sendMessage($from_id, "✅ Ваши данные успешно отправлены.", $manage_server);
    }

    // Если пользователь запросил изменение ограничения для определенной категории, но не выбрал "⬅️ Назад к управлению"
    elseif (strpos($user['step'], 'change_limit_limit-') !== false and $text != '⬅️ Назад к управлению') {
        $code = explode('-', $user['step'])[1];
        step('none');
        $sql->query("UPDATE `category_limit` SET `limit` = '$text' WHERE `code` = '$code' LIMIT 1");
        sendMessage($from_id, "✅ Ваши данные успешно отправлены.", $manage_server);
    }

    // Если пользователь запросил изменение цены для определенной категории, но не выбрал "⬅️ Назад к управлению"
    elseif (strpos($user['step'], 'change_price-') !== false and $text != '⬅️ Назад к управлению') {
        $code = explode('-', $user['step'])[1];
        step('none');
        $sql->query("UPDATE `category` SET `price` = '$text' WHERE `code` = '$code' LIMIT 1");
        sendMessage($from_id, "✅ Ваши данные успешно отправлены.", $manage_server);
    }

    // Если пользователь запросил изменение цены для категории по дате, но не выбрал "⬅️ Назад к управлению"
    elseif (strpos($user['step'], 'change_price_date-') !== false and $text != '⬅️ Назад к управлению') {
        $code = explode('-', $user['step'])[1];
        step('none');
        $sql->query("UPDATE `category_date` SET `price` = '$text' WHERE `code` = '$code' LIMIT 1");
        sendMessage($from_id, "✅ Ваши данные успешно отправлены.", $manage_server);
    }


    // Если пользователь находится в процессе изменения цены для категории по ограничению, и он не выбрал "⬅️ Назад к управлению"
    elseif (strpos($user['step'], 'change_price_limit-') !== false and $text != '⬅️ Назад к управлению') {
        $code = explode('-', $user['step'])[1];
        step('none');
        $sql->query("UPDATE `category_limit` SET `price` = '$text' WHERE `code` = '$code' LIMIT 1");
        sendMessage($from_id, "✅ Ваши данные успешно отправлены.", $manage_server);
    }

// Если пользователь находится в процессе изменения названия категории, и он не выбрал "⬅️ Назад к управлению"
    elseif (strpos($user['step'], 'change_namee-') !== false and $text != '⬅️ Назад к управлению') {
        $code = explode('-', $user['step'])[1];
        step('none');
        $sql->query("UPDATE `category` SET `name` = '$text' WHERE `code` = '$code' LIMIT 1");
        sendMessage($from_id, "✅ Ваши данные успешно отправлены.", $manage_server);
    }

    // Если пользователь находится в процессе изменения названия категории по дате, и он не выбрал "⬅️ Назад к управлению"
    elseif (strpos($user['step'], 'change_name_date-') !== false and $text != '⬅️ Назад к управлению') {
        $code = explode('-', $user['step'])[1];
        step('none');
        $sql->query("UPDATE `category_date` SET `name` = '$text' WHERE `code` = '$code' LIMIT 1");
        sendMessage($from_id, "✅ Ваши данные успешно отправлены.", $manage_server);
    }

    // Если пользователь находится в процессе изменения названия категории по ограничению, и он не выбрал "⬅️ Назад к управлению"
    elseif (strpos($user['step'], 'change_name_limit-') !== false and $text != '⬅️ Назад к управлению') {
        $code = explode('-', $user['step'])[1];
        step('none');
        $sql->query("UPDATE `category_limit` SET `name` = '$text' WHERE `code` = '$code' LIMIT 1");
        sendMessage($from_id, "✅ Ваши данные успешно отправлены.", $manage_server);
    }

    
    // ----------- Управление сообщениями ----------- //

    // Отображение статуса отправки/пересылки всем пользователям
    elseif ($text == '🔎 Статус отправки / Рассылки всем') {
        $info_send = $sql->query("SELECT * FROM `sends`")->fetch_assoc();
        if ($info_send['send'] == 'yes') $send_status = '✅'; else $send_status = '❌';
        if ($info_send['step'] == 'send') $status_send = '✅'; else $status_send = '❌';
        if ($info_send['step'] == 'forward') $status_forward = '✅'; else $status_forward = '❌';
        sendMessage($from_id, "👇🏻 Статус ваших отправок выглядит следующим образом:\n\nℹ️ В очереди отправки/пересылки: <b>$send_status</b>\n⬅️ Общая отправка: <b>$status_send</b>\n⬅️ Общий пересыл: <b>$status_forward</b>\n\n🟥 Чтобы отменить отправку/пересылку, отправьте команду /cancel_send.", $manage_message);
    }

    // Отмена отправки/пересылки всем
    elseif ($text == '/cancel_send') {
        $sql->query("UPDATE `sends` SET `send` = 'no', `text` = 'null', `type` = 'null', `step` = 'null'");
        sendMessage($from_id, "✅ Отправка/пересылка всем успешно отменена.", $manage_message);
    }

    // Начало процесса отправки сообщения всем
    elseif ($text == '📬 Общая отправка') {
        step('send_all');
        sendMessage($from_id, "👇 Отправьте свой текст в виде одного сообщения:", $back_panel);
    }

    // Если пользователь находится в процессе отправки сообщения всем
    elseif ($user['step'] == 'send_all') {
        step('none');
        if (isset($update->message->text)) {
            $type = 'text';
        } else {
            $type = $update->message->photo[count($update->message->photo) - 1]->file_id;
            $text = $update->message->caption;
        }
        $sql->query("UPDATE `sends` SET `send` = 'yes', `text` = '$text', `type` = '$type', `step` = 'send'");
        sendMessage($from_id, "✅ Ваше сообщение успешно добавлено в очередь для отправки всем!", $manage_message);
    }

    // Начало процесса пересылки сообщения всем
    elseif ($text == '📬 Общая пересылка') {
        step('for_all');
        sendMessage($from_id, "‌‌👈🏻 Перешлите свой текст:", $back_panel);
    }

    // Если пользователь находится в процессе пересылки сообщения всем
    elseif ($user['step'] == 'for_all') {
        step('none');
        sendMessage($from_id, "✅ Ваше сообщение успешно добавлено в очередь для общей пересылки!", $panel);
        $sql->query("UPDATE `sends` SET `send` = 'yes', `text` = '$message_id', `type` = '$from_id', `step` = 'forward'");
    }

    // Отправка сообщения конкретному пользователю
    elseif ($text == '📞 Отправить сообщение пользователю' or $text == '📤 Отправить сообщение пользователю') {
        step('sendmessage_user1');
        sendMessage($from_id, "🔢 Отправьте числовой ID желаемого пользователя:", $back_panel);
    }

    
    elseif ($user['step'] == 'sendmessage_user1' && $text != '⬅️ Назад к управлению') {
        if ($sql->query("SELECT `from_id` FROM `users` WHERE `from_id` = '$text'")->num_rows > 0) {
            step('sendmessage_user2');
            file_put_contents('id.txt', $text);
            sendMessage($from_id, "👇 Отправьте ваше сообщение в текстовом формате:", $back_panel);
        } else {
            step('sendmessage_user1');
            sendMessage($from_id, "❌ Номер ID, который вы отправили, не является участником бота!", $back_panel);
        }
    }
    
    elseif ($user['step'] == 'sendmessage_user2' && $text != '⬅️ Назад к управлению') {
        step('none');
        $id = file_get_contents('id.txt');
        sendMessage($from_id, "✅ Ваше сообщение успешно отправлено пользователю <code>$id</code>.", $manage_message);
        if (isset($update->message->text)){
            sendmessage($id, $text);
        } else {
            $file_id = $update->message->photo[count($update->message->photo)-1]->file_id;
            $caption = $update->message->caption;
            bot('sendphoto', ['chat_id' => $id, 'photo' => $file_id, 'caption' => $caption]);
        }
        unlink('id.txt');
    }
    
    
    // ----------- управление пользователями ----------- //
    elseif ($text == '🔎 Информация о пользователе') {
        step('info_user');
        sendMessage($from_id, "🔰Введите числовой ID интересующего вас пользователя:", $back_panel);
    }

    elseif ($user['step'] == 'info_user') {
        $info = $sql->query("SELECT * FROM `users` WHERE `from_id` = '$text'");
        if ($info->num_rows > 0) {
            step('none');
            $res_get = bot('getchatmember', ['user_id' => $text, 'chat_id' => $text]);
            $first_name = $res_get->result->user->first_name;
            $username = '@' . $res_get->result->user->username;
            $coin = number_format($info->fetch_assoc()['coin']) ?? 0;
            $count_service = $info->fetch_assoc()['count_service'] ?? 0;
            $count_payment = $info->fetch_assoc()['count_charge'] ?? 0;   
            sendMessage($from_id, "⭕️ Информация о пользователе [ <code>$text</code> ] успешно получена.\n\n▫️Пользовательский никнейм: $username\n▫️Имя пользователя: <b>$first_name</b>\n▫️Баланс пользователя: <code>$coin</code> томан\n▫️Количество услуг пользователя: <code>$count_service</code> штук\n▫️Количество платежей пользователя: <code>$count_payment</code> штук", $manage_user);
        } else {
            sendMessage($from_id, "‼ Пользователь с ID <code>$text</code> не является участником бота!", $back_panel);
        }
    }

    elseif ($text == '➕ Пополнить баланс') {
        step('add_coin');
        sendMessage($from_id, "🔰Введите числовой ID пользователя для увеличения баланса:", $back_panel);
    }

    elseif ($user['step'] == 'add_coin') {
        $user = $sql->query("SELECT * FROM `users` WHERE `from_id` = '$text'");
        if ($user->num_rows > 0) {
            step('add_coin2');
            file_put_contents('id.txt', $text);
            sendMessage($from_id, "🔎Введите сумму, на которую вы хотите пополнить баланс пользователя:", $back_panel);
        } else {
            sendMessage($from_id, "‼ Пользователь с ID <code>$text</code> не является участником бота!", $back_panel);
        }
    }

    elseif ($user['step'] == 'add_coin2') {
        step('none');
        $id = file_get_contents('id.txt');
        $sql->query("UPDATE `users` SET `coin` = coin + $text WHERE `from_id` = '$id'");
        sendMessage($from_id, "✅ Операция выполнена успешно.", $manage_user);
        sendMessage($id, "✅ Ваш счет был пополнен администрацией на сумму <code>$text</code> томан.");
        unlink('id.txt');
    }

    
    elseif ($text == '➖ Списать с баланса') {
        step('rem_coin');
        sendMessage($from_id, "🔰Введите числовой ID пользователя для уменьшения баланса:", $back_panel);
    }
    
    elseif ($user['step'] == 'rem_coin' and $text != '⬅️ Назад к управлению') {
        $user = $sql->query("SELECT * FROM `users` WHERE `from_id` = '$text'");
        if($user->num_rows > 0){
            step('rem_coin2');
            file_put_contents('id.txt', $text);
            sendMessage($from_id, "🔎Введите сумму, которую вы хотите списать с баланса пользователя:", $back_panel);
        } else {
            sendMessage($from_id, "‼ Пользователь с ID <code>$text</code> не является участником бота!", $back_panel);
        }
    }
    
    elseif ($user['step'] == 'rem_coin2' and $text != '⬅️ Назад к управлению') {  
        step('none');
        $id = file_get_contents('id.txt');
        $sql->query("UPDATE `users` SET `coin` = coin - $text WHERE `from_id` = '$id'");
        sendMessage($from_id, "✅ Операция выполнена успешно.", $manage_user);
        sendMessage($id, "✅ Со счета вас списано <code>$text</code> томан.");
        unlink('id.txt');
    }
    
    elseif (strpos($data, 'cancel_fish') !== false) {
        $id = explode('-', $data)[1];
        editMessage($from_id, "✅ Операция выполнена успешно!", $message_id);
        sendMessage($id, "❌ Отправленный вами платеж отменен администрацией из-за ошибки и не был проведен!");
    }
    
    elseif (strpos($data, 'accept_fish') !== false) {
        $id = explode('-', $data)[1];
        $price = explode('-', $data)[2];
        $sql->query("UPDATE `users` SET `coin` = coin + $price WHERE `from_id` = '$id'");
        editMessage($from_id, "✅ Операция выполнена успешно!", $message_id);
        sendMessage($id, "✅ Ваш счет пополнен на <code>$price</code> томан.");
    }
    
    elseif ($text == '❌ Заблокировать') {
        step('block');
        sendMessage($from_id, "🔢 Введите числовой ID пользователя, которого хотите заблокировать:", $back_panel);
    }
    
    
    elseif ($user['step'] == 'block' and $text != '⬅️ Назад к управлению') {
        $user = $sql->query("SELECT * FROM `users` WHERE `from_id` = '$text'");
        if ($user->num_rows > 0) {
            step('none');
            $sql->query("UPDATE `users` SET `status` = 'inactive' WHERE `from_id` = '$text'");
            sendMessage($from_id, "✅ Пользователь успешно заблокирован.", $manage_user);
        } else {
            sendMessage($from_id, "‼ Пользователь с ID <code>$text</code> не является участником бота!", $back_panel);
        }
    }
    
    elseif ($text == '✅ Разблокировать') {
        step('unblock');
        sendMessage($from_id, "🔢 Введите числовой ID пользователя, которого хотите разблокировать:", $back_panel);
    }
    
    elseif ($user['step'] == 'unblock' and $text != '⬅️ Назад к управлению') {
        $user = $sql->query("SELECT * FROM `users` WHERE `from_id` = '$text'");
        if ($user->num_rows > 0) {
            step('none');
            $sql->query("UPDATE `users` SET `status` = 'active' WHERE `from_id` = '$text'");
            sendMessage($from_id, "✅ Пользователь успешно разблокирован.", $manage_user);
        } else {
            sendMessage($from_id, "‼ Пользователь с ID <code>$text</code> не является участником бота!", $back_panel);
        }
    }
    
    
    elseif ($text == '◽ Секции') {
        sendMessage($from_id, "🔰Этот раздел еще не завершен!");
    }
    
    elseif ($text == '🚫 Управление антиспамом' or $data == 'back_spam') {
        if (isset($text)) {
            sendMessage($from_id, "🚫 Добро пожаловать в раздел управления антиспамом!\n\n✏️ Нажимая на любую из кнопок слева, вы можете изменить текущее значение.\n\n👇🏻Выберите один из нижеперечисленных вариантов:\n◽️@Proxygram", $manage_spam);
        } else {
            editMessage($from_id, "🚫 Добро пожаловать в раздел управления антиспамом!\n\n✏️ Нажимая на любую из кнопок слева, вы можете изменить текущее значение.\n\n👇🏻Выберите один из нижеперечисленных вариантов:\n◽️@Proxygram", $message_id, $manage_spam);
        }
    }
    
    elseif ($data == 'change_status_spam') {
        $status = $sql->query("SELECT * FROM `spam_setting`")->fetch_assoc()['status'];
        if ($status == 'active') {
            $sql->query("UPDATE `spam_setting` SET `status` = 'inactive'");
        } elseif ($status == 'inactive') {
            $sql->query("UPDATE `spam_setting` SET `status` = 'active'");
        }
        $manage_spam = json_encode(['inline_keyboard' => [
            [['text' => ($status == 'active') ? '🔴' : '🟢', 'callback_data' => 'change_status_spam'], ['text' => '▫️Состояние:', 'callback_data' => 'null']],
            [['text' => ($spam_setting['status'] == 'ban') ? '🚫 Заблокирован' : '⚠️ Предупреждение', 'callback_data' => 'change_type_spam'], ['text' => '▫️Тип обработки:', 'callback_data' => 'null']],
            [['text' => $spam_setting['time'] . ' секунд', 'callback_data' => 'change_time_spam'], ['text' => '▫️Время:', 'callback_data' => 'null']],
            [['text' => $spam_setting['count_message'] . ' штук', 'callback_data' => 'change_count_spam'], ['text' => '▫️Количество сообщений:', 'callback_data' => 'null']],
        ]]);
        editMessage($from_id, "🚫 Добро пожаловать в раздел управления антиспамом!\n\n✏️ Нажимая на любую из кнопок слева, вы можете изменить текущее значение.\n\n👇🏻Выберите один из нижеперечисленных вариантов:\n◽️@Proxygram", $message_id, $manage_spam);
    }
    
    elseif ($data == 'change_type_spam') {
        $type = $sql->query("SELECT * FROM `spam_setting`")->fetch_assoc()['type'];
        if ($type == 'ban') {
            $sql->query("UPDATE `spam_setting` SET `type` = 'warn'");
        } elseif ($type == 'warn') {
            $sql->query("UPDATE `spam_setting` SET `type` = 'ban'");
        }
        $manage_spam = json_encode(['inline_keyboard' => [
            [['text' => ($spam_setting['status'] == 'active') ? '🟢' : '🔴', 'callback_data' => 'change_status_spam'], ['text' => '▫️Состояние:', 'callback_data' => 'null']],
            [['text' => ($type == 'ban') ? '⚠️ Предупреждение' : '🚫 Заблокирован', 'callback_data' => 'change_type_spam'], ['text' => '▫️Тип обработки:', 'callback_data' => 'null']],
            [['text' => $spam_setting['time'] . ' секунд', 'callback_data' => 'change_time_spam'], ['text' => '▫️Время:', 'callback_data' => 'null']],
            [['text' => $spam_setting['count_message'] . ' штук', 'callback_data' => 'change_count_spam'], ['text' => '▫️Количество сообщений:', 'callback_data' => 'null']],
        ]]);
        editMessage($from_id, "🚫 Добро пожаловать в раздел управления антиспамом!\n\n✏️ Нажимая на любую из кнопок слева, вы можете изменить текущее значение.\n\n👇🏻Выберите один из нижеперечисленных вариантов:\n◽️@Proxygram", $message_id, $manage_spam);
    }
    
    elseif ($data == 'change_count_spam') {
        step('change_count_spam');
        editMessage($from_id, "🆙 Введите новое значение целым числом:", $message_id, $back_spam);
    }
    
    
    elseif ($user['step'] == 'change_count_spam') {
        if (is_numeric($text)) {
            step('none');
            $sql->query("UPDATE `spam_setting` SET `count_message` = '$text'");
            $manage_spam = json_encode(['inline_keyboard' => [
                [['text' => ($spam_setting['status'] == 'active') ? '🟢' : '🔴', 'callback_data' => 'change_status_spam'], ['text' => '▫️Статус:', 'callback_data' => 'null']],
                [['text' => ($spam_setting['type'] == 'ban') ? '🚫 Бан' : '⚠️ Предупреждение', 'callback_data' => 'change_type_spam'], ['text' => '▫️Тип поведения:', 'callback_data' => 'null']],
                [['text' => $spam_setting['time'] . ' секунд', 'callback_data' => 'change_time_spam'], ['text' => '▫️Время:', 'callback_data' => 'null']],
                [['text' => $text . ' сообщений', 'callback_data' => 'change_count_spam'], ['text' => '▫️Количество сообщений:', 'callback_data' => 'null']],
            ]]);
            sendMessage($from_id, "✅ Изменения успешно внесены!\n🚫 Добро пожаловать в раздел управления антиспамом!\n\n✏️ Нажимая на любую из кнопок слева, вы можете изменить текущее значение.\n\n👇🏻Выберите один из нижеперечисленных вариантов:\n◽️@Proxygram", $manage_spam);
        } else {
            sendMessage($from_id, "❌ Вы ввели неверное число!", $back_spam);
        }
    }
    
    elseif ($data == 'change_time_spam') {
        step('change_time_spam');
        editMessage($from_id, "🆙 Введите новое значение целым числом:", $message_id, $back_spam);
    }
    
    elseif ($user['step'] == 'change_time_spam') {
        if (is_numeric($text)) {
            step('none');
            $sql->query("UPDATE `spam_setting` SET `time` = '$text'");
            $manage_spam = json_encode(['inline_keyboard' => [
                [['text' => ($spam_setting['status'] == 'active') ? '🟢' : '🔴', 'callback_data' => 'change_status_spam'], ['text' => '▫️Статус:', 'callback_data' => 'null']],
                [['text' => ($spam_setting['type'] == 'ban') ? '🚫 Бан' : '⚠️ Предупреждение', 'callback_data' => 'change_type_spam'], ['text' => '▫️Тип поведения:', 'callback_data' => 'null']],
                [['text' => $text . ' секунд', 'callback_data' => 'change_time_spam'], ['text' => '▫️Время:', 'callback_data' => 'null']],
                [['text' => $spam_setting['count_message'] . ' сообщений', 'callback_data' => 'change_count_spam'], ['text' => '▫️Количество сообщений:', 'callback_data' => 'null']],
            ]]);
            sendMessage($from_id, "✅ Изменения успешно внесены!\n🚫 Добро пожаловать в раздел управления антиспамом!\n\n✏️ Нажимая на любую из кнопок слева, вы можете изменить текущее значение.\n\n👇🏻Выберите один из нижеперечисленных вариантов:\n◽️@Proxygram", $manage_spam);
        } else {
            sendMessage($from_id, "❌ Вы ввели неверное число!", $back_spam);
        }
    }
    
    
    elseif ($text == '◽Каналы') {
        $lockSQL = $sql->query("SELECT `chat_id`, `name` FROM `lock`");
        if (mysqli_num_rows($lockSQL) > 0) {
            $locksText = "☑️ Добро пожаловать в раздел (🔒 Заблокированные разделы)\n\n🚦 Инструкция:\n1 - 👁 Для просмотра каждого из них нажмите на его имя.\n2 - Для удаления нажмите кнопку ( 🗑 ) рядом с ним.\n3 - Для добавления блокировки нажмите кнопку ( ➕ Добавить блокировку).";
            $button[] = [['text' => '🗝 Название блокировки', 'callback_data' => 'none'], ['text' => '🗑 Удалить', 'callback_data' => 'none']];
            while ($row = $lockSQL->fetch_assoc()) {
                $name = $row['name'];
                $link = str_replace("@", "", $row['chat_id']);
                $button[] = [['text' => $name, 'url' => "https://t.me/$link"], ['text' => '🗑', 'callback_data' => "remove_lock-{$row['chat_id']}"]];
            }
        } else $locksText = '❌ У вас нет блокировок для удаления или просмотра. Пожалуйста, добавьте через кнопку ( ➕ Добавить блокировку).';
        $button[] = [['text' => '➕ Добавить блокировку', 'callback_data' => 'addLock']];
        if ($data) editmessage($from_id, $locksText, $message_id, json_encode(['inline_keyboard' => $button]));
        else sendMessage($from_id, $locksText, json_encode(['inline_keyboard' => $button]));
    }
    
    elseif ($data == 'addLock') {
        step('add_channel');
        deleteMessage($from_id, $message_id);
        sendMessage($from_id, "✔ Отправьте имя вашего канала с символом @ :", $back_panel);
    }
    
    elseif ($user['step'] == 'add_channel' and $data != 'back_look' and $text != '⬅️ Назад к управлению') {
        if (strpos($text, "@") !== false) {
            if ($sql->query("SELECT * FROM `lock` WHERE `chat_id` = '$text'")->num_rows == 0) {
                $info_channel = bot('getChatMember', ['chat_id' => $text, 'user_id' => bot('getMe')->result->id]);
                if ($info_channel->result->status == 'administrator') {
                    step('none');
                    $channel_name = bot('getChat', ['chat_id' => $text])->result->title ?? 'Без названия';
                    $sql->query("INSERT INTO `lock`(`name`, `chat_id`) VALUES ('$channel_name', '$text')");
                    $txt = "✅ Ваш канал успешно добавлен в список обязательных подключений.\n\n🆔 - $text";
                    sendmessage($from_id, $txt, $panel);
                } else {
                    sendMessage($from_id, "❌ Робот не является администратором в канале $text!", $back_panel);
                }
            } else {
                sendMessage($from_id, "❌ Этот канал уже зарегистрирован в боте!", $back_panel);
            }
        } else {
            sendmessage($from_id, "❌ Отправленное вами имя пользователя должно начинаться с @!", $back_panel);
        }
    }
    
    
    elseif (strpos($data, "remove_lock-") !== false) {
        $link = explode("-", $data)[1];
        $sql->query("DELETE FROM `lock` WHERE `chat_id` = '$link' LIMIT 1");
        $lockSQL = $sql->query("SELECT `chat_id`, `name` FROM `lock`");
        if (mysqli_num_rows($lockSQL) > 0) {
            $locksText = "☑️ Добро пожаловать в раздел (🔒 Заблокированные разделы)\n\n🚦 Руководство:\n1 - 👁 Нажмите на название, чтобы просмотреть каждый из них.\n2 - Нажмите на кнопку ( 🗑 ) для удаления каждого.\n3 - Чтобы добавить блокировку, нажмите кнопку ( ➕ Добавить блок).";
            $button[] = [['text' => '🗝 Название блока', 'callback_data' => 'none'], ['text' => '🗑 Удалить', 'callback_data' => 'none']];
            while ($row = $lockSQL->fetch_assoc()) {
                $name = $row['name'];
                $link = str_replace("@", "", $row['chat_id']);
                $button[] = [['text' => $name, 'url' => "https://t.me/$link"], ['text' => '🗑', 'callback_data' => "remove_lock_{$row['chat_id']}"]];
            }
        } else $locksText = '❌ У вас нет блокировок для удаления или просмотра. Пожалуйста, добавьте их с помощью кнопки ( ➕ Добавить блок).';
        $button[] = [['text' => '➕ Добавить блок', 'callback_data' => 'addLock']];
        if ($data) editmessage($from_id, $locksText, $message_id, json_encode(['inline_keyboard' => $button]));
        else sendMessage($from_id, $locksText, json_encode(['inline_keyboard' => $button]));
    }
    
    
    elseif ($text == '◽Настройки платежного шлюза') {
        sendMessage($from_id, "⚙️ Добро пожаловать в настройки платежного шлюза.\n\n👇🏻 Выберите один из вариантов ниже:", $manage_payment);
    }
    
    elseif ($text == '✏️ Статус платежного шлюза') {
        sendMessage($from_id, "✏️ Статус платежного шлюза робота следующий:", $manage_off_on_paymanet);
    }
    
    elseif ($data == 'change_status_rubpay') {
        $status = $sql->query("SELECT * FROM `payment_setting`")->fetch_assoc()['rubpay_status'];
        if ($status == 'active') {
            $sql->query("UPDATE `payment_setting` SET `rubpay_status` = 'inactive'");
        } elseif ($status == 'inactive') {
            $sql->query("UPDATE `payment_setting` SET `rubpay_status` = 'active'");
        }
        $manage_off_on_paymanet = json_encode(['inline_keyboard' => [
            [['text' => ($status == 'inactive') ? '🟢' : '🔴', 'callback_data' => 'change_status_rubpay'], ['text' => '💳 RUB:', 'callback_data' => 'null']],
            // другие кнопки
        ]]);
        editMessage($from_id, "✏️ Статус платежного шлюза робота следующий:", $message_id, $manage_off_on_paymanet);
    }
    
    
    
    elseif ($data == 'change_status_nowpayment') {
        $status = $sql->query("SELECT * FROM `payment_setting`")->fetch_assoc()['nowpayment_status'];
        if ($status == 'active') {
            $sql->query("UPDATE `payment_setting` SET `nowpayment_status` = 'inactive'");
        } elseif ($status == 'inactive') {
            $sql->query("UPDATE `payment_setting` SET `nowpayment_status` = 'active'");
        }
        $manage_off_on_paymanet = json_encode(['inline_keyboard' => [
            [['text' => ($payment_setting['rubpay_status'] == 'active') ? '🟢' : '🔴', 'callback_data' => 'change_status_rubpay'], ['text' => '💳 RUB :', 'callback_data' => 'null']],
            [['text' => ($status == 'inactive') ? '🟢' : '🔴', 'callback_data' => 'change_status_nowpayment'], ['text' => ': Кпирто:', 'callback_data' => 'null']],
            [['text' => ($payment_setting['card_status'] == 'active') ? '🟢' : '🔴', 'callback_data' => 'change_status_card'], ['text' => '▫️ QIWI :', 'callback_data' => 'null']]
        ]]);
        editMessage($from_id, "✏️ Статус платежного шлюза робота следующий:", $message_id, $manage_off_on_paymanet);
    }
    
    elseif ($data == 'change_status_card') {
        $status = $sql->query("SELECT * FROM `payment_setting`")->fetch_assoc()['card_status'];
        if ($status == 'active') {
            $sql->query("UPDATE `payment_setting` SET `card_status` = 'inactive'");
        } elseif ($status == 'inactive') {
            $sql->query("UPDATE `payment_setting` SET `card_status` = 'active'");
        }
        $manage_off_on_paymanet = json_encode(['inline_keyboard' => [
            [['text' => ($payment_setting['rubpay_status'] == 'active') ? '🟢' : '🔴', 'callback_data' => 'change_status_rubpay'], ['text' => '💳 RUB:', 'callback_data' => 'null']],
            [['text' => ($payment_setting['nowpayment_status'] == 'active') ? '🟢' : '🔴', 'callback_data' => 'change_status_nowpayment'], ['text' => ': Кпирто ▫️', 'callback_data' => 'null']],
            [['text' => ($status == 'inactive') ? '🟢' : '🔴', 'callback_data' => 'change_status_card'], ['text' => '▫️ QIWI  :', 'callback_data' => 'null']]
        ]]);
        editMessage($from_id, "✏️ Статус платежного шлюза робота следующий:", $message_id, $manage_off_on_paymanet);
    }
    
    elseif ($text == '▫️Настроить номер карты') {
        step('set_card_number');
        sendMessage($from_id, "🪪 Пожалуйста, отправьте ваш QIWI кошелка корректно и точно:", $back_panel);
    }
    
    elseif ($user['step'] == 'set_card_number') {
        if (is_numeric($text)) {
            step('none');
            $sql->query("UPDATE `payment_setting` SET `card_number` = '$text'");
            sendMessage($from_id, "✅ Ваш номер QIWI кошелка успешно настроен!\n\n◽️ номер QIWI кошелка : <code>$text</code>", $manage_payment);
        } else {
            sendMessage($from_id, "❌ Ошибка в отправленном вами номер QIWI кошелка!", $back_panel);
        }
    }    
    
    elseif ($text == '▫💵 Введите номер вашего QIWI кошелка') {
        step('set_card_number_name');
        sendMessage($from_id, "#️⃣ Пожалуйста, введите номер карты точно и правильно:", $back_panel);
    }
    
    elseif ($user['step'] == 'set_card_number_name') {
        step('none');
        $sql->query("UPDATE `payment_setting` SET `card_number_name` = '$text'");
        sendMessage($from_id, "✅ Имя владельца QIWI кошелка успешно установлено!\n\n◽️ Имя владельца QIWI кошелка : <code>$text</code>", $manage_payment);
    }
    
    elseif ($text == '🪙 NOWPayments') {
        step('set_nowpayment_token');
        sendMessage($from_id, "🔎 Пожалуйста, отправьте свой API-ключ:", $back_panel);
    }
    
    elseif ($user['step'] == 'set_nowpayment_token') {
        step('none');
        $sql->query("UPDATE `payment_setting` SET `nowpayment_token` = '$text'");
        sendMessage($from_id, "✅ Успешно настроено!", $manage_payment);
    }
    
    elseif ($text == '💳 RUBPAY') {
        step('set_rubpay_token');
        sendMessage($from_id, "🔎 Пожалуйста, введите свой API-ключ RubPay:", $back_panel);
    }
    
    elseif ($user['step'] == 'set_rubpay_token') {
        step('none');
        $sql->query("UPDATE `payment_setting` SET `rubpay_token` = '$text'");
        sendMessage($from_id, "✅ Успешно настроено!", $manage_payment);
    }    
    
    // -----------------Управление скидками----------------- //
    elseif ($text == '🎁 Управление скидками' || $data == 'back_copen') {
        step('none');
        if (isset($text)) {
            sendMessage($from_id, "🎁 Добро пожаловать в раздел управления скидками бота!\n\n👇🏻 Выберите один из вариантов ниже:\n◽️@Proxygram", $manage_copens);
        } else {
            editMessage($from_id, "🎁 Добро пожаловать в раздел управления скидками бота!\n\n👇🏻 Выберите один из вариантов ниже:\n◽️@Proxygram", $message_id, $manage_copens);
        }
    }
    
    elseif ($data == 'add_copen') {
        step('add_copen');
        editMessage($from_id, "🆕 Отправьте код скидки:", $message_id, $back_copen);
    }
    
    elseif ($user['step'] == 'add_copen') {
        step('send_percent');
        file_put_contents('add_copen.txt', "$text\n", FILE_APPEND);
        sendMessage($from_id, "🔢 Укажите процент скидки для кода [ <code>$text</code> ] в виде целого числа:", $back_copen);
    }
    
    elseif ($user['step'] == 'send_percent') {
        if (is_numeric($text)) {
            step('send_count_use');
            file_put_contents('add_copen.txt', "$text\n", FILE_APPEND);
            sendMessage($from_id, "🔢 Укажите, сколько человек может использовать этот код скидки в виде целого числа:", $back_copen);
        } else {
            sendMessage($from_id, "❌ Неверный ввод числа!", $back_copen);
        }
    }
    
    elseif ($user['step'] == 'send_count_use') {
        if (is_numeric($text)) {
            step('none');
            $copen = explode("\n", file_get_contents('add_copen.txt'));
            $sql->query("INSERT INTO `copens` (`copen`, `percent`, `count_use`, `status`) VALUES ('{$copen[0]}', '{$copen[1]}', '{$text}', 'active')");
            sendMessage($from_id, "✅ Ваш код скидки успешно добавлен!", $back_copen);
            unlink('add_copen.txt');
        } else {
            sendMessage($from_id, "❌ Неверный ввод числа!", $back_copen);
        }
    }
    
    elseif ($data == 'manage_copens') {
        step('manage_copens');
        $copens = $sql->query("SELECT * FROM `copens`");
        if ($copens->num_rows > 0) {
            $key[] = [
                ['text' => '▫️Удалить', 'callback_data' => 'null'],
                ['text' => '▫️Статус', 'callback_data' => 'null'],
                ['text' => '▫️Кол-во', 'callback_data' => 'null'],
                ['text' => '▫️Процент', 'callback_data' => 'null'],
                ['text' => '▫️Код', 'callback_data' => 'null']
            ];
            while ($row = $copens->fetch_assoc()) {
                $key[] = [
                    ['text' => '🗑', 'callback_data' => 'delete_copen-'.$row['copen']],
                    ['text' => ($row['status'] == 'active') ? '🟢' : '🔴', 'callback_data' => 'change_status_copen-'.$row['copen']],
                    ['text' => $row['count_use'], 'callback_data' => 'change_countuse_copen-'.$row['copen']],
                    ['text' => $row['percent'], 'callback_data' => 'change_percent_copen-'.$row['copen']],
                    ['text' => $row['copen'], 'callback_data' => 'change_code_copen-'.$row['copen']]
                ];
            }
            $key[] = [['text' => '🔙 Назад', 'callback_data' => 'back_copen']];
            $key = json_encode(['inline_keyboard' => $key]);
            editMessage($from_id, "✏️ Список всех скидочных кодов:\n\n⬅️ Нажмите на каждый, чтобы изменить его текущее значение.\n◽️@Proxygram", $message_id, $key);
        } else {
            alert('❌ Ни один скидочный код в боте не зарегистрирован!');
        }
    }
    
    elseif (strpos($data, 'delete_copen-') !== false) {
        $copen = explode('-', $data)[1];
        alert('🗑 Скидочный код успешно удален.', false);
        $sql->query("DELETE FROM `copens` WHERE `copen` = '$copen'");
        $copens = $sql->query("SELECT * FROM `copens`");
        if ($copens->num_rows > 0) {
            $key[] = [['text' => '▫️Удалить', 'callback_data' => 'null'], ['text' => '▫️Статус', 'callback_data' => 'null'], ['text' => '▫️Кол-во', 'callback_data' => 'null'], ['text' => '▫️Процент', 'callback_data' => 'null'], ['text' => '▫️Код', 'callback_data' => 'null']];
            while ($row = $copens->fetch_assoc()) {
                $key[] = [['text' => '🗑', 'callback_data' => 'delete_copen-'.$row['copen']], ['text' => ($row['status'] == 'active') ? '🟢' : '🔴', 'callback_data' => 'change_status_copen-'.$row['copen']], ['text' => $row['count_use'], 'callback_data' => 'change_countuse_copen-'.$row['copen']], ['text' => $row['percent'], 'callback_data' => 'change_percent_copen-'.$row['copen']], ['text' => $row['copen'], 'callback_data' => 'change_code_copen-'.$row['copen']]];
            }
            $key[] = [['text' => '🔙 Назад', 'callback_data' => 'back_copen']];
            $key = json_encode(['inline_keyboard' => $key]);
            editMessage($from_id, "✏️ Список всех скидочных кодов:\n\n⬅️ Нажмите на каждый, чтобы изменить его текущее значение.\n◽️@Proxygram", $message_id, $key);
        } else {
            editMessage($from_id, "❌ Больше нет других скидочных кодов.", $message_id, $manage_copens);
        }
    }
    
    elseif (strpos($data, 'change_status_copen-') !== false) {
        $copen = explode('-', $data)[1];
        $copen_status = $sql->query("SELECT `status` FROM `copens` WHERE `copen` = '$copen'")->fetch_assoc();
        if ($copen_status['status'] == 'active') {
            $sql->query("UPDATE `copens` SET `status` = 'inactive' WHERE `copen` = '$copen'");    
        } else {
            $sql->query("UPDATE `copens` SET `status` = 'active' WHERE `copen` = '$copen'");
        }
        
        $copens = $sql->query("SELECT * FROM `copens`");
        if ($copens->num_rows > 0) {
            $key[] = [['text' => '▫️Удалить', 'callback_data' => 'null'], ['text' => '▫️Статус', 'callback_data' => 'null'], ['text' => '▫️Кол-во', 'callback_data' => 'null'], ['text' => '▫️Процент', 'callback_data' => 'null'], ['text' => '▫️Код', 'callback_data' => 'null']];
            while ($row = $copens->fetch_assoc()) {
                if ($row['copen'] == $copen) {
                    $status = ($copen_status['status'] == 'active') ? '🔴' : '🟢';
                } else {
                    $status = ($row['status'] == 'active') ? '🟢' : '🔴';
                }
                $key[] = [['text' => '🗑', 'callback_data' => 'delete_copen-'.$row['copen']], ['text' => $status, 'callback_data' => 'change_status_copen-'.$row['copen']], ['text' => $row['count_use'], 'callback_data' => 'change_countuse_copen-'.$row['copen']], ['text' => $row['percent'], 'callback_data' => 'change_percent_copen-'.$row['copen']], ['text' => $row['copen'], 'callback_data' => 'change_code_copen-'.$row['copen']]];
            }
            $key[] = [['text' => '🔙 Назад', 'callback_data' => 'back_copen']];
            $key = json_encode(['inline_keyboard' => $key]);
            editMessage($from_id, "✏️ Список всех скидочных кодов:\n\n⬅️ Нажмите на каждый, чтобы изменить его текущее значение.\n◽️@Proxygram", $message_id, $key);
        } else {
            editMessage($from_id, "❌ Больше нет других скидочных кодов.", $message_id, $manage_copens);
        }
    }
    
    elseif (strpos($data, 'change_countuse_copen-') !== false) {
        $copen = explode('-', $data)[1];
        step('change_countuse_copen-'.$copen);
        editMessage($from_id, "🔢 Отправьте новое значение:", $message_id, $back_copen);
    }    
    
    elseif (strpos($user['step'], 'change_countuse_copen-') !== false) {
        if (is_numeric($text)) {
            $copen = explode('-', $user['step'])[1];
            $sql->query("UPDATE `copens` SET `count_use` = '$text' WHERE `copen` = '$copen'");
            sendMessage($from_id, "✅ Операция выполнена успешно.", $manage_copens);
        } else {
            sendMessage($from_id, "❌ Неверный ввод!", $back_copen);
        }
    }
    
    elseif (strpos($data, 'change_percent_copen-') !== false) {
        $copen = explode('-', $data)[1];
        step('change_percent_copen-'.$copen);
        editMessage($from_id, "🔢 Отправьте новое значение:", $message_id, $back_copen);
    }
    
    elseif (strpos($user['step'], 'change_percent_copen-') !== false) {
        if (is_numeric($text)) {
            $copen = explode('-', $user['step'])[1];
            $sql->query("UPDATE `copens` SET `percent` = '$text' WHERE `copen` = '$copen'");
            sendMessage($from_id, "✅ Операция выполнена успешно.", $manage_copens);
        } else {
            sendMessage($from_id, "❌ Неверный ввод!", $back_copen);
        }
    }
    
    elseif (strpos($data, 'change_code_copen-') !== false) {
        $copen = explode('-', $data)[1];
        step('change_code_copen-'.$copen);
        editMessage($from_id, "🔢 Отправьте новое значение:", $message_id, $back_copen);
    }
    
    elseif (strpos($user['step'], 'change_code_copen-') !== false) {
        $copen = explode('-', $user['step'])[1];
        $sql->query("UPDATE `copens` SET `copen` = '$text' WHERE `copen` = '$copen'");
        sendMessage($from_id, "✅ Операция выполнена успешно.", $manage_copens);
    }
    
    // ----------------- Управление текстами ----------------- //
    elseif ($text == '◽Настройка текстов бота') {
        sendMessage($from_id, "⚙️ Добро пожаловать в настройки текстов бота.\n\n👇 Выберите один из вариантов ниже:", $manage_texts);
    }
    
    elseif ($text == '✏️ Текст стартового сообщения') {
        step('set_start_text');
        sendMessage($from_id, "👇 Отправьте текст стартового сообщения:", $back_panel);
    }
    
    elseif ($user['step'] == 'set_start_text') {
        step('none');
        $texts['start'] = str_replace('
        ', '\n', $text);
        file_put_contents('texts.json', json_encode($texts));
        sendMessage($from_id, "✅ Текст стартового сообщения успешно установлен!", $manage_texts);
    }
    
    elseif ($text == '✏️ Текст тарифов услуг') {
        step('set_tariff_text');
        sendMessage($from_id, "👇 Отправьте текст тарифов услуг:", $back_panel);
    }
    
    
    elseif ($user['step'] == 'set_tariff_text') {
        step('none');
        $texts['service_tariff'] = str_replace('\n', PHP_EOL, $text);
        file_put_contents('texts.json', json_encode($texts));
        sendMessage($from_id, "✅ Текст тарифа услуг успешно установлен!", $manage_text);
    }
    
    elseif ($text == '✏️ Текст руководства по подключению') {
        step('none');
        sendMessage($from_id, "✏️ Какой раздел текста руководства по подключению вы хотите установить?\n\n👇 Пожалуйста, выберите один из вариантов:", $set_text_edu);
    }
    
    elseif (strpos($data, 'set_edu_') !== false) {
        $sys = explode('_', $data)[2];
        step('set_edu_'.$sys);
        sendMessage($from_id, "👇🏻Отправьте ваш желаемый текст точно:\n\n⬅️ Выбранная операционная система: <b>$sys</b>", $back_panel);
    }
    
    elseif (strpos($user['step'], 'set_edu_') !== false) {
        step('none');
        $sys = explode('_', $user['step'])[2];
        $texts['edu_' . $sys] = str_replace('\n', PHP_EOL, $text);
        file_put_contents('texts.json', json_encode($texts));
        sendMessage($from_id, "✅ Ваш текст успешно установлен.\n\n#️⃣ Операционная система: <b>$sys</b>", $manage_texts);
    }
    
    // -----------------управление администраторами ----------------- //
    elseif ($text == '➕ Добавить администратора') {
        step('add_admin');
        sendMessage($from_id, "🔰Отправьте числовой ID пользователя, которого вы хотите добавить в качестве администратора:", $back_panel);
    }
    
    elseif ($user['step'] == 'add_admin' && $text != '⬅️ Назад к управлению') {
        $user = $sql->query("SELECT * FROM `users` WHERE `from_id` = '$text'");
        if($user->num_rows != 0){
            step('none');
            $sql->query("INSERT INTO `admins` (`chat_id`) VALUES ('$text')");
            sendMessage($from_id, "✅ Пользователь <code>$text</code> успешно добавлен в список администраторов.", $manage_admin);
        } else {  
            sendMessage($from_id, "‼ Пользователь <code>$text</code> не является участником бота!", $back_panel);
        }
    }
    
    elseif ($text == '➖ Удалить администратора') {
        step('rem_admin');
        sendMessage($from_id, "🔰Отправьте числовой ID пользователя, которого вы хотите удалить из администраторов:", $back_panel);
    }
    
    
    elseif ($user['step'] == 'rem_admin' and $text != '⬅️ Назад к управлению') {
        $user = $sql->query("SELECT * FROM `users` WHERE `from_id` = '$text'");
        if($user->num_rows > 0){
            step('none');
            $sql->query("DELETE FROM `admins` WHERE `chat_id` = '$text'");
            sendMessage($from_id, "✅ Пользователь <code>$text</code> успешно удален из списка администраторов.", $manage_admin);
        } else {
            sendMessage($from_id, "‼ Пользователь <code>$text</code> не является участником бота!", $back_panel);  
        }
    }
    elseif ($text == '⚙️ Список администраторов') {
        $res = $sql->query("SELECT * FROM `admins`");
        if($res->num_rows == 0){
            sendmessage($from_id, "❌ Список администраторов пуст.");
            exit();
        }
        while($row = $res->fetch_array()){
            $key[] = [['text' => $row['chat_id'], 'callback_data' => 'delete_admin-'.$row['chat_id']]];
        }
        $count = $res->num_rows;
        $key = json_encode(['inline_keyboard' => $key]);
        sendMessage($from_id, "🔰 Список администраторов бота:\n\n🔎 Общее количество администраторов: <code>$count</code>", $key);
    }    
}

/**
* Project name: PROXYGRAM
* Channel: @Proxygram
* Bot: @Proxygram_bot
* Support: @Proxygram_Supp
 * Version: 2.5
**/
