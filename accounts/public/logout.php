<?php

require_once dirname(__DIR__, 2) . '/private/classes/Auth.php';

Auth::logout();

header('Location: login.php');
exit;
