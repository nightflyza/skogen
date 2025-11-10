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

//custom title overrides default map label
if (ubRouting::checkGet('customtitle')) {
   $mapLabel = ubRouting::get('customtitle','safe');
}

$supportedFormats = array('svg', 'webp', 'png', 'jpeg', 'jpg', 'gif');
$defaultFormat = 'png';
$requestedFormat = $defaultFormat;

foreach ($supportedFormats as $format) {
    if (ubRouting::checkGet($format, false)) {
        $requestedFormat = $format;
        break;
    }
}

if ($requestedFormat === 'svg') {
    $map = $mapGen->generateSvg($morkStates, $mapLabel);
    header('Content-Type: image/svg+xml');
    print($map);
} else {
    if ($requestedFormat === 'jpg') {
        $requestedFormat = 'jpeg';
    }
    $map = $mapGen->rasterize($morkStates, $mapLabel, $requestedFormat);
    header('Content-Type: ' . $map['contentType']);
    header('Content-Length: ' . strlen($map['bytes']));
    print($map['bytes']);
}
 