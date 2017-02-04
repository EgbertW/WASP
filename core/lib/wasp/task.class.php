<?php
/*
This is part of WASP, the Web Application Software Platform.
It is published under the MIT Open Source License.

Copyright 2017, Egbert van der Wal

Permission is hereby granted, free of charge, to any person obtaining a copy of
this software and associated documentation files (the "Software"), to deal in
the Software without restriction, including without limitation the rights to
use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of
the Software, and to permit persons to whom the Software is furnished to do so,
subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS
FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR
COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER
IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/

namespace WASP;

class Task
{
    private static $task_list = array();

    public function execute()
    {}

    public static function listTasks($ostr = STDOUT)
    {
        if (count(self::$task_list) === 0)
        {
            fprintf($ostr, "No tasks available\n");
        }
        else
        {
            fprintf($ostr, "Listing available tasks: \n");

            foreach (Task::$task_list as $task => $desc)
            {
                printf("- %-30s");
                CLI::formatText(32, CLI::MAX_LINE_LENGTH, $desc);
            }
            printf("\n");
        }
    }

    public static function runTask($task)
    {
        // CLI uses : because \ is used as escape character, so that
        // awkward syntax is required.
        $task = str_replace(":", "\\", $task);

        try
        {
            $taskrunner = new $task($opts);
            if (!($taskrunner instanceof Task))
            {
                fprintf(STDERR, "Error: invalid task: {$task}\n");
                exit(1);
            }
        }
        catch (\Exception $e)
        {
            fprintf(STDERR, "Error: error while running task: {$task}\n");
            fprintf(STDERR, $e->getTraceAsString() . "\n");
        }
        catch (\Error $e)
        {
            fprintf(STDERR, "Error: task does not exist: {$task}\n");
        }
    }
}
