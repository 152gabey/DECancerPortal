<?php
// Ends the session (signs the user out) and goes back to the login page.
session_start();
$_SESSION = [];
session_destroy();
header('Location: login.php');
exit;
