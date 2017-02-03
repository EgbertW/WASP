<?php

require_once "../sys/init.php";

$a = new WASP\CLI;
$a->addOption("r", "run", "action", "Run the specified task");
$opts = $a->parse($_SERVER['argv']);

if (!isset($opts['run']))
{
    $a->syntax();
    die();
}

if (isset($opts['help']))
{
    die($a->syntax(false));
}

$task = $opts['run'];

echo "Running task {$task}\n";
