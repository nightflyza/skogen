<?php

$dataSource = __DIR__ . '/data/morkstates.json';
$result=array();
header('Content-Type: application/json');
if (file_exists($dataSource)) { 
    $statesData = file_get_contents($dataSource);
    $morkStates = json_decode($statesData, true);
    if (!empty($morkStates) and is_array($morkStates)) {
        foreach ($morkStates as $state) {
            $stateDistricts=array();
            $stateCommunities=array();
            if (isset($state['districts']) and is_array($state['districts'])) {
                foreach ($state['districts'] as $district) {
                    $stateDistricts[] = array(
                        'name' => $district['name'],
                        'alert' => $district['alert'],
                        'changed' => $district['changed'],
                    );
                }
            }
            if (isset($state['community']) and is_array($state['community'])) {
                foreach ($state['community'] as $community) {
                    $stateCommunities[] = array(
                        'name' => $community['name'],
                        'alert' => $community['alert'],
                        'changed' => $community['changed'],
                    );
                }
            }

            $result[$state['id']] = array(
                'name' => $state['name'],
                'alert' => $state['alert'],
                'changed' => $state['changed'],
                'districts' => $stateDistricts,
                'community' => $stateCommunities,
            );
        }
        print(json_encode($result));
    } else {
        print(json_encode($result));
    }
} else {
    print(json_encode($result));
}