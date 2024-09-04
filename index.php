<?php

include 'location.php';

$pid = $_SERVER['argv'][1];
$location = new Location();

if ($pid) {
    $location->getProvince($pid);
} else {
    $location->handle(5);
}
