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

namespace WASP\DB\Table;

use WASP\DB\Table\Column\Column;
use WASP\DB\DBException;

class Index
{
    const PRIMARY = 3;
    const INDEX = 1;
    const UNIQUE = 2;

    protected $table;
    protected $name;
    protected $columns = array();

    public function __construct($type, $column = null)
    {
        $this->type = $type;
        $args = func_get_args();
        array_shift($args); // Type

        foreach ($args as $arg)
            $this->addColumn($arg);
    }
    
    public function setTable($table)
    {
        if ($table instanceof Table)
            $table = $table->getName();
        $this->table = $table;
    }

    public function addColumn($column)
    {
        $args = func_get_args();
        foreach ($args as $column)
        {
            if (is_string($column))
                $this->columns[] = $column;
            elseif ($column instanceof Column)
                $this->columns[] = $column->getName();
            else
                throw new DBException("Invalid column for index");
        }
    }

    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    public function getName()
    {
        if ($this->type === Index::PRIMARY)
            return "PRIMARY";

        if ($this->name === null)
        {
            $this->name = $this->table . "_" . implode("_", $this->columns);
            $this->name .= ($this->type === Index::UNIQUE) ? "_uidx" : "_idx";
        }
        return $this->name;
    }

    public function getColumns()
    {
        return $this->columns;
    }

    public function getType()
    {
        return $this->type;
    }

    public function getTable()
    {
        return $this->table;
    }
}
