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

namespace WASP\DB\Driver;

use WASP\DB\DB;
use WASP\DB\DataType;
use WASP\DB\DAOException;

use WASP\Config;
use PDOException;
use WASP\Debug\Log;

class MySQL implements IDriver
{
    private $logger;
    private $db;

    protected $mapping = array(
        DataType::INT => 'int4',
        DataType::CHAR => 'character',
        DataType::STRING => 'character varying',
        DataType::TEXT => 'text',
        DataType::TIMESTAMP => 'timestamp without time zone',
        DataType::TIMESTAMPTZ => 'timestamp with time zone',
        DateType::DATE = 'timestamp with time zone',
        DateType::TIME = 'timestamp with time zone',
        DateType::BOOLEAN = 'boolean'
    );

    public function __construct(DB $db)
    {
        $this->db = $db;
        $this->logger = new Log("WASP.DB.Driver.MySQL");
    }

    public function identQuote($name)
    {
        return '"' . str_replace('"', '""', $name) . '"';
    }

    public function select($table, $where, $order, array $params)
    {
        $q = "SELECT * FROM " . $this->identQuote($table);
        
        $col_idx = 0;
        $q .= static::getWhere($where, $col_idx, $params);
        $q .= static::getOrder($order);

        $this->logger->info("Preparing query {}", $q);
        $st = $this->db->prepare($q);

        $st->execute($params);
        return $st;
    }

    public function update($table, $idfield, array $record)
    {
        $id = $record[$idfield];
        if (empty($id))
            throw new DAOException("No ID set for record to be updated");

        unset($record[$idfield]);
        if (count($record) == 0)
            throw new DAOException("Nothing to update");
        
        $col_idx = 0;
        $params = array();

        $parts = array();
        foreach ($record as $k => $v)
        {
            $col_name = "col" . (++$col_idx);
            $parts[] .= self::identQuote($k) . " = :{$col_name}";
            $params[$col_name] = $v;
        }

        $q = "UPDATE " . self::identQuote($table) . " SET ";
        $q .= implode(", ", $parts);
        $q .= static::getWhere(array($idfield => $id), $col_idx, $params);

        $this->logger->info("Preparing update query {}", $q);
        $st = $this->db->prepare($q);
        $st->execute($params);

        return $st->rowCount();
    }

    public function insert($table, $idfield, array &$record)
    {
        if (!empty($record[$idfield]))
            throw new DAOException("ID set for record to be inserted");

        $q = "INSERT INTO " . self::identQuote($table) . " ";
        $fields = array_map(array($this, "identQuote"), array_keys($record));
        $q .= "(" . implode(", ", $fields) . ")";

        $col_idx = 0;
        $params = array();
        $parts = array();
        foreach ($record as $val)
        {
            $col_name = "col" . (++$col_idx);
            $parts[] = ":{$col_name}";
            $params[$col_name] = $val;
        }
        $q .= " VALUES (" . implode(", ", $parts) . ")";
    
        $this->logger->info("Preparing insert query {}", $q);
        $st = $this->db->prepare($q);

        $this->logger->info("Executing insert query with params {}", $q);
        $st->execute($params);
        $record[$idfield] = $this->db->lastInsertId();

        return $record[$idfield];
    }

    public function delete($table, $where)
    {
        $q = "DELETE FROM " . self::identQuote($table);
        $col_idx = 0;
        $params = array();
        $q .= static::getWhere($where, $col_idx, $params);

        $this->logger->info("Model.DAO", "Preparing delete query {}", $q);
        $st = $this->db->prepare($q);
        $st->execute($params);

        return $st->rowCount();
    }

    protected function getWhere($where, &$col_idx, array &$params)
    {
        if (is_string($where))
            return " WHERE " . $where;

        if (is_array($where) && count($where))
        {
            $parts = array();
            foreach ($where as $k => $v)
            {
                if (is_array($v))
                {
                    $op = $v[0];
                    $val = $v[1];
                }
                else
                {
                    $op = "=";
                    $val = $v;
                }

                if ($val === null)
                {
                    if ($op === "=")
                        $parts[] = self::identQuote($k) . " IS NULL";
                    else if ($op == "!=")
                        $parts[] = self::identQuote($k) . " IS NOT NULL";
                }
                else
                {
                    $col_name = "col" . (++$col_idx);
                    $parts[] = self::identQuote($k) . " {$op} :{$col_name}";
                    $params[$col_name] = $v;
                }
            }

            return " WHERE " . implode(" AND ", $parts);
        }

        return "";
    }

    public function getOrder($order)
    {
        if (is_string($order))
            return "ORDER BY " . $order;

        if (is_array($order) && count($order))
        {
            $parts = array();
            foreach ($order as $k => $v)
            {
                if (is_numeric($k))
                {
                    $k = $v;
                    $v = "ASC";
                }
                else
                {
                    $v = strtoupper($v);
                    if ($v !== "ASC" && $v !== "DESC")
                        throw new DAOException("Invalid order type {$v}");
                }
                $parts[] = self::identQuote($k) . " " . $v;
            }

            return " ORDER BY " . implode(", ", $parts);
        }

        return "";
    }

    public function getColumns($table)
    {
        try
        {
            $q = $this->db->prepare("
                SELECT column_name, data_type, is_nullable, column_default, numeric_precision, numeric_scale, character_maximum_length
                    FROM information_schema.columns 
                    WHERE table_name = :table AND table_schema = :schema
                    ORDER BY ordinal_position
            ");

            $q->execute(array("table_name" => self::tablename(), "schema" => $schema));

            return $q->fetchAll();
        }
        catch (PDOException $e)
        {
            throw new TableNotExists();
        }
    }

    protected function validateColumn(array $column)
    {
        $keys = array('column_name', 'data_type', 'is_nullable', 'column_default', 'numeric_precision', 'numeric_scale', 'character_maximum_length');
        foreach ($keys as $k)
            if (!isset($column[$k]))
                throw new DBException("Field {$k} from column definition is missing");
        return true;
    }

    public function createTable($table, $columns)
    {

    }

    public function getColumnDef(array $column)
    {

    }

    public function parseColumnInfo(array $column)
    {
        
    }

    public function addColumn($table, array $column)
    {
        $coldef = $this->validateColumn($column);
        $cols = $this->getColumns($table);

        foreach $cols as $c)
        {
            if ($c['column_name'] === $column['column_name'])
                throw new DBException("Duplicate column: {$column['column_name']}");
        }

        $q = "ALTER TABLE " . $this->identQuote($table) . " ADD COLUMN " . $this->getColumnDefin
    }

    public function removeColumn($table, array $column)
    {

    }
}
