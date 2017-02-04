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

namespace WASP\Model;

use WASP\Config;
use WASP\DB\DAO;

class DBVersion extends DAO
{
    protected static $table = "db_version";
    protected static $idfield = "id";

    public function __construct($module)
    {
        $this->record = self::fetchSingle(array("module" => $module));
        if (empty($this->record))
        {
            $this->record = array('module' => $module, 'version' => 0);
            $this->save();
        }
    }

    public static function createTable()
    {
        $config = Config::getConfig();
        $type = $config->get('sql', 'type');

        if ($type === "mysql")
        {
            $q = "CREATE TABLE `" . self::tablename() . "` (
                id integer primary key auto_increment,
                module varchar(128) not null,
                version integer not null,
                UNIQUE(module)
            )";
        }
        elseif ($type === "pgsql")
        {
            $q = "CREATE TABLE \"" . self::tablename() . "\" (
                id serial,
                module character varying(128),
                version integer not null,
                UNIQUE(module)
            ");
        }

        $db = DB::get();
        $db->exec($q);
    }
}
