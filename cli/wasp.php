<?php

use WASP\CLI;
use WASP\Task;
use WASP\Arguments;

require_once "../sys/init.php";

$a = new CLI;
$a->addOption("r", "run", "action", "Run the specified task");
$a->addOption("s", "list", false, "List the available tasks");
$opts = $a->parse($_SERVER['argv']);

if (isset($opts['help']))
    $a->syntax("");

if ($opts->has('list'))
{
    echo "LISTIN TASKS\n";
    Task::listTasks();
    exit();
}

if (!$opts->has('run'))
    $a->syntax("Please specify the action to run");

Task::runTask($opts->get('run'));
