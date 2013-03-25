<?php
include_once 'src/Process.php';
include_once 'src/Worker.php';
include_once 'Task.php';


$task1 = new Task(0, function() {
    $i = 1000;
    $arr = [];
    while(500<$i--){
        $arr[] = $i;
    }
    return $arr;
});
$task2 = new Task(1, function() {
    $i = 500;
    $arr = [];
    while($i--){
         $arr[] = $i;
    }
    return $arr;
});


$worker1 = new Worker(0);
$worker1->addTask($task1);
$worker2 = new Worker(1);
$worker2->addTask($task2);
$process1 = new Process(array($worker1, 'run'));
$process1->start();
$process2 = new Process(array($worker2, 'run'));
$process2->start();

$processes = [$process1, $process2];

$responses = [];

while (!empty($processes)) {
    foreach ($processes as $index => $process) {
        if (!$process->isAlive()) {
            $response = $process->getMessage();
            $responses = array_merge($responses, $response[0][$index]);
            unset($processes[$index]);
        }
    }
    // let the CPU do its work
    sleep(0.1);
}

print_r($responses);