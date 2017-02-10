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

/**
 * Provide some tools for creating and removing directories.
 */
class Dir
{
    private static $required_prefix = "";

    /** 
     * Security measure that prevents attempts of removing files outside of WASP
     * Each rmtree'd path should have this prefix, otherwise the command is not executed.
     * @param $prefix string The prefix that should be required on each path for rmtree
     */
    public static function setRequiredPrefix($prefix)
    {
        self::$required_prefix = $prefix;
    }

    /**
     * Make a directory and its parents. When all directories already exist, nothing happens.
     * Newly created directories are chmod'ded to 0770: RWX for owner and group.
     *
     * @param $path string The path to create
     */
    public static function mkdir($path)
    {
        $parts = explode("/", $path);

        $path = "";
        foreach ($parts as $p)
        {
            $path .= $p . '/';
            if (!is_dir($path))
            {
                mkdir($path);
                chmod($path, 0770);
            }
        }
    }

    /**
     * Delete a directory and its contents. The provided path must be inside the configured prefix.
     * @param $path string The path to remove
     * @return int Amount of files and directories that have been deleted
     */
    public static function rmtree($path)
    {
        $path = realpath($path);
        if (empty($path)) // File/dir does not exist
            return true;

        if (!empty(self::$required_prefix) && strpos($path, self::$required_prefix) !== 0)
            throw new \RuntimeException("Refusing to remove directory outside " . self::$required_prefix);

        self::checkWrite($path);

        if (!is_dir($path))
            return unlink($path) ? 1 : 0;

        $cnt = 0;
        $d = \dir($path);
        while (($entry = $d->read()) !== false)
        {
            if ($entry === "." || $entry === "..")
                continue;

            $entry = $path . '/' . $entry;
            self::checkWrite($entry);

            if (is_dir($entry))
                $cnt += self::rmtree($entry);
            else
                $cnt += (unlink($entry) ? 1 : 0);
        }

        rmdir($path);
        return $cnt + 1;
    }

    /**
     * @codeCoverageIgnore We cannot test this - to create a file that cannot
     * be chmod'ed, it needs to be owned by someone else. This means that tests
     * would have to be run as root partially, and that would be madness.
     */
    private static function checkWrite($path)
    {
        if (!is_writable($path) && @chmod($path, 0666) === false)
            throw new \RuntimeException("Cannot delete $path - permission denied");
    }
}

// Limit dir by default to the WASP var directory
// @codeCoverageIgnoreStart
// No need to test this, see pathTest
Dir::setRequiredPrefix(Path::$VAR);
// @codeCoverageIgnoreEnd