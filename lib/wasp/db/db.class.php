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

namespace WASP\DB;

class DB
{
    private static $default_db = null;

    public static function get(Config $config = null)
    {
        $default = false;
        if ($config === null)
        {
            if (self::$default_db)
                return self::$default_db;

            $config = Config::load();
            $default = true;
        }

        if (!$config->has('sql', 'pdo'))
        {
            $username = $config->get('sql', 'username');
            $password = $config->get('sql', 'password');
            $host = $config->get('sql', 'hostname');
            $database = $config->get('sql', 'database');
            $dsn = $config->get('sql', 'dsn');
            
            if (!$dsn)
            {
                $type = $config->get('sql', 'type');
                if ($type === "mysql")
                {
                    $dsn = "mysql:host=" . $host . ";dbname=" . $database;
                    \Debug\info("WASP.DB", "Generated DSN: {}", $dsn);
                }
            }
            
            $pdo = new \PDO($dsn, $username, $password);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
            
            $config->set('sql', 'pdo', $pdo);
            if ($default)
                self::$default_db = $pdo;
        }

        return $config->get('sql', 'pdo');
    }
}
