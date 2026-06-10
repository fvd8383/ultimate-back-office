<?php

require_once __DIR__ . '/../../private/classes/Session.php';

Session::logout();

header('Location: login.php');
exit;
