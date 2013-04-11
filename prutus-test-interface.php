<?php
if(isset($_POST['password'])) {
	if($_POST['password'] == 'exploding knees') {
		echo json_encode(array('success' => 'logged_in'));
	} else {
		echo json_encode(array('error' => 'invalid_password'));
	}
}