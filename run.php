<?php
require 'class.prutus.php';

$postFields = array(
	'password' => '%word%'
);
$prutus = new Orpheus\Prutus('http://localhost/prutus2/prutus-test-interface.php', $postFields, __DIR__ . '/wordlist.txt');

$prutus->setPermutation(true);
$prutus->setBuffer(5);
$prutus->setHash('c0c7c76d30bd3dcaefc96f40275bdc0a', 'md5', '%word%');

$results = $prutus->start(function($data, $password) {
	echo $data . PHP_EOL;
	$data = json_decode($data);
	return $data->success == 'logged_in' ? true : false;
});

var_dump($results);