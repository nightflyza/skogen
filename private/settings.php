<?php

$pollTimeout=5;
$backlogSize=100;
$channel='air_alert_ua';
$timezone='Europe/Kiev';
$mapLabel='';
$debug=true;
$paranoidDebug=false;

// this is the list of strings that will be used to detect the alert status
$parserStrings=array(
    'Повітряна тривога' => true,
    'Повітряна Тривога' => true,
    'Відбій тривоги' => false,
    '🟢' => false,
    '🔴' => true,
    '🟠' => true,
    '🟡' => true,
);

// this is the list of strings in messages that will be ignored
$ignoreStrings=array(
  'БЕЗКОШТОВНА ЕВАКУАЦІЯ',
);