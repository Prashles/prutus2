<?php
require 'class.prutus.php';

$postFields = array(
	'password' => '%word%'
);
$prutus = new Orpheus\Prutus('http://localhost/prutus2/prutus-test-interface.php', $postFields, __DIR__ . '/wordlist.txt');

$prutus->setPermutation(true);
$prutus->setBuffer(5);
$prutus->setHash('81dc9bdb52d04dc20036dbd8313ed055', 'md5', '%word%');

$results = $prutus->start(function($data, $password) {
	echo $data . PHP_EOL;
	$data = json_decode($data);
	return $data->success == 'logged_in' ? true : false;
});

echo 'The password is: ' . $results;