<?php

define('INFOBIP_USER', getenv('INFOBIP_USER'));
define('INFOBIP_PWD', getenv('INFOBIP_PWD'));
define('INFOBIP_SMS_OVER_URL_ENDPOINT', getenv('INFOBIP_SMS_OVER_URL_ENDPOINT'));

if (!isset($_REQUEST['key']) || empty($_REQUEST['key'])) {
        die('Nenhuma chave informada.');
}

if (!isset($_REQUEST['domain']) || empty($_REQUEST['domain'])) {
        die('Nenhum domínio informado.');
}

$database_host = getenv("LMSDB_SERVICE_HOST");
$database_port = getenv("LMSDB_SERVICE_PORT");
$database_name = getenv("MYSQL_DATABASE");
$database_user = getenv("MYSQL_USER");
$database_password = getenv("MYSQL_PASSWORD");

$dsn = sprintf('mysql:host=%s;port=%s;dbname=%s', $database_host, $database_port, $database_name);

$connection = new \PDO($dsn, $database_user, $database_password);
if (!$connection) {
        header('Content-Type: application/json');
        echo json_encode(array(
                'success' => false,
                'message' => 'Falha ao conectar ao servidor de dados.',
        ));
        exit();
}

// verifica se expirado, ativo e o domínio confere
$key = (string)$_REQUEST['key'];
$dominio = (string)$_REQUEST['domain'];

$stmt = $connection->prepare('SELECT * FROM `token` WHERE `api_key` = :apikey AND `dominio` = :dominio');
$stmt->execute(array(':apikey' => $key, ':dominio' => $dominio));
$result = $stmt->fetch();

$response = array();
$doSms = false;

if (!$result) {
    $response = array('success' => false, 'message' => 'A Chave de Licença informada não é válida.');
    header('Content-Type: application/json');
    echo(json_encode($response));
    exit();
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
    } elseif ($result['sms'] == 0) {
        // nao assinou notificação via sms
        $response = array('success' => false, 'message' => 'A Chave de Licença não permite envio de notificação SMS.');
    } else {
        $doSms = true;
    }
}

if (!$doSms) {
    header('Content-Type: application/json');
    echo(json_encode($response));
    exit();
}

// Notificacao
$phone = (string)$_REQUEST['phone'];
$message = (string)$_REQUEST['message'];
$query_str = http_build_query(array(
  'username' => INFOBIP_USER,
  'password' => INFOBIP_PWD,
  'to' => $phone,
  'text' => $message,
));

$ch = curl_init();
curl_setopt_array($ch, array(
  CURLOPT_HEADER => 1,
  CURLOPT_RETURNTRANSFER => 1,
  CURLOPT_USERAGENT => 'Prestashop SkyHub Notification System',
  CURLOPT_URL => INFOBIP_SMS_OVER_URL_ENDPOINT . '?' . $query_str,
));

$response = trim(curl_exec($ch));
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpcode != 200) {
    header('Content-Type: application/json');
    echo(json_encode(array('success' => false, 'message' => 'SMS API send error. Code: '. $httpcode)));
    exit();
}

header('Content-Type: application/json');
echo(json_encode(array('success' => true, 'message' => 'Notificação enviada com sucesso para ['.$phone.'].')));
exit();
