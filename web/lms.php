<?php

if (!isset($_REQUEST['key']) || empty($_REQUEST['key'])) {
    die('Nenhuma chave informada.');
}

if (!isset($_REQUEST['domain']) || empty($_REQUEST['domain'])) {
    die('Nenhum domínio informado.');
}

$database_host = getenv('mysql_SERVICE_HOST');
$database_port = getenv('mysql_SERVICE_PORT');
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

header('Content-Type: application/json');
echo(json_encode($response));
exit();
