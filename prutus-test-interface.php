<?php
if(isset($_POST['password'])) {
	if($_POST['password'] == 'exploding knees') {
		echo json_encode(array('success' => 'logged_in', 'error' => ''));
	} else {
		echo json_encode(array('success' => '', 'error' => 'invalid_password:' . $_POST['password']));
	}
}