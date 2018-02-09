<?php
require_once __DIR__ . '/vendor/autoload.php';

use Symfony\Component\HttpFoundation\Request;

// Database Connection
$database_host = 'localhost';
$database_port = 3306;
$database_name = 'c1lms';
$database_user = 'c1lms';
$database_password = '1q2w3e4r';

$connection = new \PDO(
    sprintf('mysql:host=%s;port=%s;dbname=%s', $database_host, $database_port, $database_name),
    $database_user,
    $database_password
);

$app = new Silex\Application();
$app['connection'] = $connection;

/**
 * LMS Check if is avalid key for domain
 */
$app->post('/check', function (Request $request) use ($app) {
    $key = $request->get('key');
    $domain = $request->get('domain');
    if (!$key && !$domain) {
        return $app->json(array(
            'success' => false,
            'message' => 'Nenhuma chave ou domínio fornecido na requisição.',
        ));
    }

    $stmt = $app['connection']->prepare('SELECT * FROM `token` WHERE `api_key` = :apikey AND `dominio` = :dominio');
    $stmt->execute(array(':apikey' => $key, ':dominio' => $domain));
    $result = $stmt->fetch();

    $response = array();
    if (!$result) {
        $response = array('success' => false, 'message' => 'A Chave de Licença informada não é válida.');
    } else {
        if ($result['expirou'] == 1) {
            // expirada ?
            $response = array('success' => false, 'message' => 'A Chave de Licença fornecida expirou.');
        } elseif ($result['ativo'] == 0) {
            // ativa ?
            $response = array('success' => false, 'message' => 'A Chave de Licença está inativa.');
        } elseif ($result['validado'] == 0) {
            // nao validou
            $response = array('success' => false, 'message' => 'A Chave de Licença não está validada.');
        } else {
            $response = array('success' => true);
        }
    }

    return $app->json($response);
});

/**
 * SMS Notification via POST
 */
$app->post('/notify', function (Request $request) use ($app) {
    $key = $request->get('key');
    $domain = $request->get('domain');

    if (!$key && !$domain) {
        return $app->json(array(
            'success' => false,
            'message' => 'Nenhuma chave ou domínio fornecido na requisição.',
        ));
    }

    $stmt = $app['connection']->prepare('SELECT * FROM `token` WHERE `api_key` = :apikey AND `dominio` = :dominio');
    $stmt->execute(array(':apikey' => $key, ':dominio' => $domain));
    $result = $stmt->fetch();

    $response = array();
    $doSms = false;

    if (!$result) {
        $response = array('success' => false, 'message' => 'A Chave de Licença informada não é válida.');
    } elseif ($result['expirou'] == 1) {
        // expirada
        $response = array('success' => false, 'message' => 'A Chave de Licença fornecida expirou.');
    } elseif ($result['ativo'] == 0) {
        // inativa
        $response = array('success' => false, 'message' => 'A Chave de Licença está inativa.');
    } elseif ($result['validado'] == 0) {
        // nao validou
        $response = array('success' => false, 'message' => 'A Chave de Licença não está validada.');
    } elseif ($result['sms'] == 0) {
        // nao assinou notificação via sms
        $response = array('success' => false, 'message' => 'A Chave de Licença não permite envio de notificação SMS.');
    } else {
        $doSms = true;
    }

    if (!$doSms) {
        return $app->json($response);
    }

    // Notificacao
    $phone = $request->get('phone');
    $message = $request->get('message');
    $query_str = http_build_query(array(
        'username' => getenv('INFOBIP_USER'),
        'password' => getenv('INFOBIP_PWD'),
        'to' => $phone,
        'text' => $message,
    ));

    $url = sprintf('%s?%s', getenv('INFOBIP_SMS_OVER_URL_ENDPOINT'), $query_str);

    $ch = curl_init();
    curl_setopt_array($ch, array(
        CURLOPT_HEADER => 1,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_USERAGENT => 'Prestashop SkyHub Notification System',
        CURLOPT_URL => $url,
    ));

    $response = trim(curl_exec($ch));
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpcode != 200) {
        return $app->json(array(
            'success' => false,
            'message' => 'SMS API send error. Code: ' . $httpcode,
        ));
    }

    return $app->json(array(
        'success' => true,
        'message' => 'Notificação enviada com sucesso para [' . $phone . '].',
    ));
});

/**
 * Fallback to website
 */
$app->get('/', function () use ($app) {
    return $app->redirect('http://www.lojamodulos.com.br');
});

/**
 * Run it ;D
 */
$app->run();
