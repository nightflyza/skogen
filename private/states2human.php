<?php

require_once(__DIR__ . '/../private/uastates.php');

//converting defaultStates to human readable format
if (is_array($defaultStates) and count($defaultStates) > 0) {
    foreach ($defaultStates as $state) {
        print('* ' . $state['name'] . PHP_EOL);
        if (isset($state['districts']) and is_array($state['districts'])) {
            foreach ($state['districts'] as $district) {
                print('     + ' . $district['name'] . PHP_EOL);
            }
        }
        if (isset($state['community']) and is_array($state['community'])) {
            foreach ($state['community'] as $community) {
                print('         - ' . $community['name'] . PHP_EOL);
            }
        }
        print(PHP_EOL);
    }
}
