<?php
/*
This is part of WASP, the Web Application Software Platform.
It is published under the MIT Open Source License.

Copyright 2017, Egbert van der Wal

Permission is hereby granted, free of charge, to any person obtaining a copy of
this software and associated documentation files (the "Software"), to deal in
the Software without restriction, including without limitation the rights to
use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of
}the Software, and to permit persons to whom the Software is furnished to do so,
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

namespace WASP\DB\Table\Column;

use WASP\DB\Table\Table;
use WASP\DB\DBException;

class Column implements \Serializable, \JSONSerializable
{
    const CHAR     =  1;
    const VARCHAR  =  2;
    const TEXT     =  3;
    const JSON     =  4;

    const BOOLEAN  =  5;
    const INT      =  6;
    const BIGINT   =  7;
    const FLOAT    =  8;
    const DECIMAL  =  9;
 
    const DATE     = 10;
    const DATETIME = 11;
    const TIME =     12;

    const BINARY =   13;

    protected $table;

    protected $name;
    protected $type;
    protected $nullable;

    protected $max_char_length;

    protected $numeric_scale;
    protected $numeric_precision;

    protected $default = null;

    protected $serial = null;

    public function __construct($name, $type, $max_length, $numeric_precision, $numeric_scale, $nullable, $default, $serial = false)
    {
        $this->name = $name;
        $this->type = $type;
        $this->max_length = $max_length;
        $this->numeric_scale = $numeric_scale;
        $this->numeric_precision = $numeric_precision;
        $this->nullable = $nullable == true;
        $this->default = $default;
        $this->serial = $serial == true;
    }

    public function setSerial($serial = true)
    {
        $serial = $serial == true;
        if ($serial && $this->type !== Column::INT && $this->type !== Column::BIGINT)
            throw new DBException("A serial column must be of type integer");

        $this->serial = $serial == true;
    }

    public function getSerial()
    {
        return $this->serial;
    }

    public function getTable()
    {
        return $this->table;
    }

    public function setTable(Table $table)
    {
        if ($this->serial)
        {
            foreach ($table->getColumns() as $c)
                if ($c->name !== $this->name && $c->serial)
                    throw new DBException("There can be only one serial column in a table");
        }

        $this->table = $table;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getType()
    {
        return $this->type;
    }

    public function getMaxLength()
    {
        return $this->max_length;
    }

    public function getNumericScale()
    {
        return $this->numeric_scale;
    }

    public function getNumericPrecision()
    {
        return $this->numeric_precision;
    }

    public function isNullable()
    {
        return $this->nullable;
    }

    public function getDefault()
    {
        return $this->default;
    }

    public function toArray()
    {
        return array(
            "column_name" => $this->name,
            "data_type" => $this->typeToStr($this->type),
            "is_nullable" => $this->nullable ? 1 : 0,
            "column_default" => $this->default,
            "numeric_precision" => $this->numeric_precision,
            "numeric_scale" => $this->numeric_scale,
            "character_maximum_length" => $this->max_length,
            "serial" => $this->serial
        );
    }

    public static function fromArray(array $data)
    {
        $args = self::parseArray($data);
        extract($args);
        return new Column($name, $type, $max_length, $is_nullable, $column_default, $numeric_precision, $numeric_scale, $serial);
    }

    public static function parseArray(array $data)
    {
        return array(
            'name' => $data['column_name'],
            'type' => self::strToType($data['data_type']),
            'max_length' => isset($data['character_maximum_length']),
            'is_nullable' => isset($data['is_nullable']) ? $data['is_nullable'] == true : false,
            'column_default' => isset($data['column_default']) ? $data['column_default'] : null,
            'numeric_precision' => isset($data['numeric_precision']) ? $data['numeric_precision'] : null,
            'numeric_scale' => isset($data['numeric_scale']) ? $data['numeric_scale'] : null,
            'serial' => isset($data['serial']) ? $data['serial'] == true : false
        );
    }

    public function jsonSerialize()
    {
        return $this->toArray();
    }

    public function serialize()
    {
        return serialize($this->toArray());
    }

    public function unserialize($data)
    {
        $args = self::parseArray($data);
        extract($args);
        $this->__construct($name, $type, $max_length, $is_nullable, $column_default, $numeric_precision, $numeric_scale, $serial);
    }

    public static function strToType($type)
    {
        if (\is_int_val($type) && $type >= Column::CHAR && $type <= Column::TEXT)
            return $type;

        $name = get_called_class() . "::" . $type;
        if (defined($name))
            return constant($name);
        throw new DBException("Invalid type: $type");
    }

    public static function typeToStr($type)
    {
        switch ($type)
        {
            case Column::CHAR: return "CHAR";
            case Column::VARCHAR: return "VARCHAR";
            case Column::TEXT: return "TEXT";
            case Column::JSON: return "JSON";

            case Column::BOOLEAN: return "BOOLEAN";
            case Column::INT: return "INT";
            case Column::BIGINT: return "BIGINT";
            case Column::FLOAT: return "FLOAT";
            case Column::DECIMAL: return "DECIMAL";
         
            case Column::DATE: return "DATE";
            case Column::DATETIME: return "DATETIME";
            case Column::TIME: return "TIME";

            case Column::BINARY: return "BINARY";
            default: throw new DBException("Invalid column type: $type");
        }
    }

}
