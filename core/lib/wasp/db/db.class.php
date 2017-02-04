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

use WASP\Config;
use WASP\Debug;
use PDO;

/**
 * The DB class wraps a PDO allowing for lazy connecting.
 * The configuration is passed at initialization time,
 * but the connection is not established until the first method
 * call on the database. This behavior can be altered by setting
 * [sql][lazy] = false in the configuration, in which case
 * the PDO will be connected directly on construction.
 */
class DB
{
    private static $default_db = null;
    private $pdo;
    private $qdriver;
    private $config;

    /**
     * Create a new DB object for a specific configuration set.
     */
    private function __construct($config)
    {
        $this->config = $config;
        if ($this->config->get('sql', 'lazy', true) == false)
            $this->connect();
    }

    /**
     * Connect to the database: actually initialize the PDO and connect it.
     * @throws PDOException If the connection fails
     */
    private function connect()
    {
        if ($this->pdo !== null)
            return true;

        $username = $this->config->get('sql', 'username');
        $password = $this->config->get('sql', 'password');
        $host = $this->config->get('sql', 'hostname');
        $database = $this->config->get('sql', 'database');
        $dsn = $this->config->get('sql', 'dsn');
            
        if (!$dsn)
        {
            $type = $this->config->get('sql', 'type');
            if ($type === "mysql")
            {
                $dsn = "mysql:host=" . $host . ";dbname=" . $database;
                Debug\info("WASP.DB", "Generated DSN: {}", $dsn);
            }
        }
            
        $pdo = new PDO($dsn, $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
        $this->pdo = $pdo;

        $pos = strpos($dsn, ":");
        $type = substr($dsn, 0, $pos);
        $driver = "WASP\\DB\\Driver\\" . $type;

        if (!class_exists($driver))
        {
            throw new DBException("No driver available for database type $type");
        }

        $this->qdriver = new $driver($this);
    }

    /**
     * Get a DB object for the provided configuration
     */
    public static function get(Config $config = null)
    {
        $default = false;
        if ($config === null)
        {
            if (self::$default_db)
                return self::$default_db;

            $config = Config::getConfig();
            $default = true;
        }

        if (!$config->has('sql', 'pdo'))
        {
            $db = new DB($config);
            $config->set('sql', 'pdo', $db);
        }
        else
            $db = $config->get('sql', 'pdo');

        return $db;
    }

    public function driver()
    {
        return $this->qdriver;
    }
    
    /**
     * @return PDO The PDO object of this connection. Can be used to extract the unwrapped PDO, should the need arise.
     */
    public function getPDO()
    {
        if ($this->pdo === null)
            $this->connect();

        return $this->pdo;
    }

    /**
     * Wrap methods of the PDO. This is implemented this way
     * to allow lazy connecting to the database - the object
     * is always initialized but only connected once requested.
     */
    public function __call($func, $args)
    {
        if ($this->pdo === null)
            $this->connect();
        return call_user_func_array(array($this->pdo, $func), $args);
    }
}
