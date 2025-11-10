<?php

require_once (__DIR__ . '/private/settings.php');
require_once (__DIR__ . '/libs/api.uamapgen.php');
require_once (__DIR__ . '/libs/php-svg/autoloader.php');
require_once (__DIR__ . '/libs/api.ubrouting.php');

$mapGen = new UaMapGen();

$statesData = file_get_contents(__DIR__ . '/data/morkstates.json');
$morkStates = json_decode($statesData, true);

if ($mapLabelAppendTimeStamp) {
    $mapLabel .= ' ' . date('Y-m-d H:i');
}

 //here svg map
 if (ubRouting::checkGet('svg',false)) {
    $map = $mapGen->generateSvg($morkStates, $mapLabel, true);
    header('Content-Type: image/svg+xml');
    echo $map;
 } else {
    //or png map by default
    $map = $mapGen->generatePng($morkStates, $mapLabel, true);

    header('Content-Type: ' . $map['contentType']);
    header('Content-Length: ' . strlen($map['bytes']));
    echo $map['bytes'];
}
 