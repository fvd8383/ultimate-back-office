<?php

$query = [];

if (isset($_GET['business_id'])) {
    $query['business_id'] = (int) $_GET['business_id'];
}

if (isset($_GET['contact_id'])) {
    $query['contact_id'] = (int) $_GET['contact_id'];
}

$target = 'contact.php';
if (count($query) > 0) {
    $target .= '?' . http_build_query($query);
}

header('Location: ' . $target);
exit;
