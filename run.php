<?php
require 'class.prutus.php';

$postFields = array(
	'password' => '%word%'
);
$prutus = new Orpheus\Prutus('http://localhost/prutus2/prutus-test-interface.php', $postFields, __DIR__ . '/wordlist.txt');

$results = $prutus->start(function($data) {
	$data = json_decode($data);
	return $data->success == 'logged_in' ? true : false;
});

var_dump($results);