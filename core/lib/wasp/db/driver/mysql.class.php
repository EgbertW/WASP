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
use WASP\DB\DAOException;

use WASP\DB\Table\Table;
use WASP\DB\Table\Index;
use WASP\DB\Table\ForeignKey;
use WASP\DB\Table\Column\Column;

use WASP\Config;
use WASP\Debug\Log;

use PDOException;

class MySQL implements IDriver
{
    private $logger;
    private $db;

    protected $mapping = array(
        Column::CHAR => 'CHAR',
        Column::VARCHAR => 'VARCHAR',
        Column::TEXT => 'MEDIUMTEXT',
        Column::JSON => 'MEDIUMTEXT',

        Column::BOOLEAN = 'TINYINT',
        Column::INT => 'INT',
        Column::BIGINT => 'BIGINT',
        Column::FLOAT => 'FLOAT',
        Column::DECIMAL => 'DECIMAL',

        Column::DATETIME => 'DATETIME',
        Column::DATE = 'DATE',
        Column::TIME = 'TIME',

        Column::BINARY => 'MEDIUMBLOBL'
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

    public function getColumns($table_name)
    {
        $config = Config::getConfig();
        $database = $config->get('sql', 'database');
        $schema = $config->get('sql', 'schema', $database);

        try
        {
            $q = $this->db->prepare("
                SELECT column_name, data_type, is_nullable, column_default, numeric_precision, numeric_scale, character_maximum_length, extra
                    FROM information_schema.columns 
                    WHERE table_name = :table AND table_schema = :schema
                    ORDER BY ordinal_position
            ");

            $q->execute(array("table_name" => $table_name, "schema" => $schema));

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

    public function createTable(Table $table)
    {
        $query = "CREATE TABLE " . $this->identQuote($table->getName()) . " (\n";

        $cols = $table->getColumns();
        $coldefs = array();
        $serial = null;
        foreach ($cols as $c)
        {
            if ($c->getSerial())
                $serial = $c;
            $coldefs[] = $this->getColumnDefinition($c);
        }

        $query .= "    " . implode("\n    ", $coldefs);
        $query .= ") ENGINE=InnoDB\n";

        // Create the main table
        $this->db->exec($query);

        // Add indexes
        $serial_col = null;

        $indexes = $table->getIndexes();
        foreach ($indexes as $idx)
            $this->addIndex($table, $idx);

        // Add auto_increment
        if ($serial !== null)
            $this->createSerial($serial);

        // Add foreign keys
        $fks = $table->getForeignKeys();
        foreach ($fks as $fk)
            $this->createForeignKey($table, $fk);
        return $this;
    }

    public function dropTable(Table $table)
    {
        $query = "DROP TABLE " . $this->identQuote($table->getName());
        $this->db->exec($query);
        return $this;
    }
    
    public function truncateTable(Table $table)
    {
        $query = "TRUNCATE " . $this->identQuote($table->getName());
        $this->db->exec($query);
        return $this;
    }

    public function createIndex(Table $table, Index $idx)
    {
        $cols = $idx->getColumns();
        $names = array();
        foreach ($cols as $col)
            $names[] = $this->identQuote($col->getName());
        $names = '(' . implode(',', $names) . ')';

        if ($idx->getType() === Index::PRIMARY)
        {
            $this->db->exec("ALTER TABLE " . $this->identQuote($table->getName()) . " ADD PRIMARY KEY $names)");
            if ($idx->getColumn()->getSerial())
                $serial_col = $idx->getColumn();
        }
        else
        {
            $q = "CREATE ";
            if ($idx->getType() === Index::UNIQUE)
                $q .= "UNIQUE ";
            $q .= "INDEX ON " . $this->identQuote($table->getName()) . " $names";
            $this->db->exec($q);
        }
        return $this;
    }

    public function dropIndex(Table $table, Index $idx)
    {
        $name = $idx->getName();
        $q = " DROP INDEX " . $this->identQuote($name) . " ON " . $this->identQuote($table->getName());
        $this-db->exec($q);
        return $this;
    }

    public function createForeignKey(Table $table, ForeignKey $fk)
    {
        $src_table = $table->getName();
        $src_cols = array();

        foreach ($fk->getColumns() as $c)
            $src_cols[] = $this->identQuote($c->getName());

        $tgt_table = $fk->getReferredTable()->getName();
        $tgt_cols = array();

        foreach ($fk->getReferredColumns() as $c)
            $tgt_cols[] = $this->identQuote($c->getName());

        $q = 'ALTER TABLE ' . $this->identQuote($src_table)
            . ' ADD FOREIGN KEY ' . $this->identQuote($fk->getName())
            . '(' . implode(',', $src_cols) . ') '
            . 'REFERENCES ' . $this->identQuote($tgt_table)
            . '(' . implode(',', $tgt_cols) . ')';

        $on_update = $fk->getOnUpdate();
        if ($on_update === ForeignKey::DO_CASCADE)
            $q .= ' ON UPDATE CASCADE ';
        elseif ($on_update === ForeignKey::DO_RESTRICT)
            $q .= ' ON UPDATE RESTRICT ';
        elseif ($on_update === ForeignKey::DO_NULL)
            $q .= ' ON UPDATE SET NULL ';

        $on_delete = $fk->getOnDelete();
        if ($on_update === ForeignKey::DO_CASCADE)
            $q .= ' ON DELETE CASCADE ';
        elseif ($on_update === ForeignKey::DO_RESTRICT)
            $q .= ' ON DELETE RESTRICT ';
        elseif ($on_update === ForeignKey::DO_NULL)
            $q .= ' ON DELETE SET NULL ';

        $this->db->exec($q);
        return $this;
    }

    public function dropForeignKey(Table $table, ForeignKey $fk)
    {
        $name = $fk->getName();
        $this->db->exec("ALTER TABLE DROP FOREIGN KEY " . $this->identQuote($name));
        return $this;
    }

    public function createSerial(Table $table, Column $column)
    {
        $q = "ALTER TABLE " . $this->identQuote($table->getName()) 
            . " MODIFY " . $this->identQuote($column->getName())
            . " " . $this->getColumnDefinition($column) . " AUTO_INCREMENT";

        $this-db->exec($column);
        return $this;
    }

    public function dropSerial(Table $table, Column $column)
    {
        $q = "ALTER TABLE " . $this->identQuote($table->getName()) 
            . " MODIFY " . $this->identQuote($column->getName())
            . " " . $this->getColumnDefinition($column);

        $this-db->exec($column);
        $column->setSerial(false);
        return $this;
    }

    public function addColumn(Table $table, Column $column)
    {
        $q = "ALTER TABLE " . $this->identQuote($table) . " ADD COLUMN " . $this->getColumnDefinition($column);
        $this->db->exec($q);

        return $this;
    }

    public function removeColumn(Table $table, Column $column)
    {
        $q = "ALTER TABLE " . $this->identQuote($table->getName()) . " DROP COLUMN " . $this->identQuote($column->getName());
        $this->db->exec($q);

        return $this;
    }

    public function getColumnDefinition(Column $col)
    {
        $numtype = $col->getType();
        if (!isset($this->mapping[$type]))
            throw new DBException("Unsupported column type: $type");

        $type = $this->mapping[$numtype];
        $coldef = $this->identQuote($col->getName()) . " " . $type;
        switch ($numtype)
        {
            case Column::CHAR:
            case Column::VARCHAR:
                $coldef .= "(" . $col->getMaxLength() . ")";
                break;
            case Column::INT:
            case Column::BIGINT:
                $coldef .= "(" . $col->getNumericPrecision() . ")";
                break;
            case Column::BOOLEAN:
                $coldef .= "(1)";
                break;
            case Column::DECIMAL:
                $coldef .= "(" . $col->getNumericPrecision() . "," . $col->getNumericScale() . ")";
        }

        $coldef .= $col->isNullable() ? " NULL " : " NOT NULL ";
        $def = $col->getDefault();
        if ($def)
            $coldef .= " DEFAULT " . $def;

        return $coldef;
    }

    public function loadTable($table_name)
    {
        $table = new Table($table_name);

        // Get all columns
        $columns = $this->getColumns($table_name);
        $serial = null;
        foreach ($columns as $col)
        {
            $type = $col['data_type'];
            $numtype = array_search($type, $this->mapping);
            if ($numtype === false)
                throw new DBException("Unsupported field type: " . $type);

            $column = new Column(
                $col['column_name'],
                $numtype,
                $col['character_maximum_length'],
                $col['numeric_scale'],
                $col['numeric_precision'],
                $col['is_nullable'],
                $col['column_default']
            );

            $table->addColumn($column);
            if (strtolower($col['extra']) === "auto_increment")
            {
                $pkey = new Index(Index::PRIMARY);
                $pkey->addColumn($column);
                $table->addIndex($pkey);

                $column->setSerial(true);
                $serial = $column;
            }
        }

        $constraints = $this->getConstraints($table_name);
        foreach ($constraints as $constraint)
        {
            if ($constraint['CONSTRAINT_TYPE'] === "FOREIGN KEY")
            {
                $fk = new ForeignKey($constraint['CONSTRAINT_NAME']);

                $ref_table = $constraint['REF_TABLE'];

                // Get refere

            }
            elseif ($constraint['CONSTRAINT_TYPE'] === "PRIMARY KEY")
            {
                if ($serial !== null) // Should have already have this one
                    continue;

                $idx = new Index(Index::PRIMARY, $constraint['CONSTRAINT_NAME']);
            }
            elseif($constraint['CONSTRAINT_TYPE'] === "UNIQUE")
            {
                $idx = new Index(Index::UNIQUE, $constraint['CONSTRAINT_NAME']);
            }
        }

        // Get all indexes
    }

    public function getConstraints($table_name)
    {
        $cfg = Config::getConfig();
        $database = $cfg->get('sql', 'database');
        $schema = $cfg->get('sql', 'schema', $database);

        $q = "
        SELECT 
            kcu.CONSTRAINT_NAME AS CONSTRAINT_NAME,
            kcu.REFERENCED_TABLE_NAME AS REF_TABLE,
            kcu.REFERENCED_COLUMN_NAME AS REF_COLUMN,
            tc.CONSTRAINT_TYPE AS CONSTRAINT_TYPE
        FROM
            information_schema.key_column_usage kcu
        LEFT JOIN information_schema.TABLE_CONSTRAINTS tc 
            ON (
                tc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME AND
                tc.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA AND
                tc.TABLE_NAME = kcu.TABLE_NAME
            )
        WHERE 
            tc.CONSTRAINT_SCHEMA = :schema AND
            kcu.table_name = :table
        ";

        $q = $db->prepare($q);
        $q->execute(array("schema " => $schema, "table" => $table_name));

        return $q->fetchAll();
    }
}
