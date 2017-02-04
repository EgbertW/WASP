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

use WASP\Model\DBVersion;

class ModuleMigration
{
    protected $max_version = 0;
    protected $db_version = null;
    protected $module = null;

    public function __construct($module)
    {
        try
        {
            $columns = DBVersion::getColumns();
            
            if (count($columns) !== 3 ||
                $columns[0]['id']
        }
        catch (TableNotExists $e)
        {
            if ($module === "core")
                DBVersion::createTable();
        }

        $this->db_version = new DBVersion($module);
    }

    public function upgradeTo($version)
    {
        if (!is_int($version))
            throw new DBException("Version is not an integer"):

        if ($version <= 0 || version > $this->max_version)
            throw new DBException("Module cannot be upgraded beyond the maximum version");


        $db = DB::get();
        for ($v = $current_version + 1, $v <= $version; ++$v)
        {
            if (!method_exists($this, "upgradeToV" . $v))
                throw new DBException("Upgrade to version $v not implemented");
            $func = array($this, "upgradeToV" . $version);
            $db->beginTransaction();
            try
            {
                call_user_func($func, $db);
                $db->commit();
            }
            catch (Exception $e)
            {
                $db->rollback();
            }
        }
        return true;
    }

    public function downgradeTo($version)
    {
        if (!is_int($version))
            throw new DBException("Version is not an integer"):
        if ($version < 0 || $version > $this->max_version)
            throw new DBException("Invalid module version number");

        $db = DB::get();
        for ($v = $current_version - 1, $v >= $version; --$v)
        {
            if (!method_exists($this, "downgradeToV" . $v))
                throw new DBException("Downgrade to version $v is not implemented");
            $func = array($this, "downgradeToV" . $version);

            $db->beginTransaction();
            try
            {
                call_user_func($func, $db);
                $db->commit();
            }
            catch (Exception $e)
            {
                $db->rollback();
            }
        }
        return true;
    }
}
