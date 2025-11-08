<?php

require_once(__DIR__ . '/../private/settings.php');
require_once(__DIR__ . '/../private/uastates.php');
require_once(__DIR__ . '/../libs/api.omaeurl.php');
require_once(__DIR__ . '/../libs/api.telechanparser.php');
require_once(__DIR__ . '/../libs/api.updater.php');


$updater = new StatesUpdater();
$updater->setDebug($debug);

$parser = new TeleChanParser();
$parser->setDebug($debug);
$parser->setChannel($channel);
$parser->setTimezoneName($timezone);
$parser->setBacklogSize($backlogSize);

//fetch backlog
$messages = $parser->fetchMessages();

if (empty($messages)) {
    print('[error] No messages fetched.' . PHP_EOL);
} else {
    foreach ($messages as $message) {
        if ($debug) {
            $line = '[' . $message['id'] . '] ' . $message['author'] . ' (' . $message['datetime'] . '): ' . $message['text'] . PHP_EOL;
            print($line);
        }
        $updater->parseMessage($message);
    }

        print('[stats] Backlog loaded. Last message ID: ' . $parser->getLastMessageId() . PHP_EOL);
}


// retreiving new messages from the channel
while (true) {
    $newMessages = $parser->fetchMessages();

    if (!empty($newMessages)) {
        if ($debug) {
            print('[info] New messages detected:' . PHP_EOL);
        }

        foreach ($newMessages as $message) {
            if ($debug) {
                $line = '[' . $message['id'] . '] ' . $message['author'] . ' (' . $message['datetime'] . '): ' . $message['text'] . PHP_EOL;
                print($line);
            }
            $updater->parseMessage($message);
        }

        if ($debug) {
            print('[info] Updated last message ID: ' . $parser->getLastMessageId() . PHP_EOL);
        }
    } else {
        if ($debug and $paranoidDebug) {
            print('[info] No new messages at this time.' . PHP_EOL);
        }
    }
    sleep($pollTimeout);
}

