<?php

require_once 'config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use UcV\Client;

$mobile = '18823789733';
$password = '854363201';

$client = new Client();

//$ret = $client->ucSmsGetRegcode($mobile, '127.0.0.6');
list($uid, $username, $password, $email) = $client->ucUserLogin($mobile, $password);

var_dump($uid);
var_dump($username);
var_dump($password);
var_dump($email);
