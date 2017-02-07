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
use WASP\DB\TableNotExists;
use WASP\DB\DAOException;

use WASP\DB\Table\Table;
use WASP\DB\Table\Index;
use WASP\DB\Table\ForeignKey;
use WASP\DB\Table\Column\Column;

use WASP\Config;
use WASP\Debug\Log;

use PDO;
use PDOException;

class PGSQL extends Driver
{
    protected $iquotechar = '"';

    protected $mapping = array(
        Column::CHAR => 'character',
        Column::VARCHAR => 'character varying',
        Column::TEXT => 'text',
        Column::JSON => 'json',
        Column::ENUM => 'enum',

        Column::BOOLEAN => 'boolean',
        Column::TINYINT => 'smallint',
        Column::SMALLINT => 'smallint',
        Column::MEDIUMINT => 'integer',
        Column::INT => 'integer',
        Column::BIGINT => 'bigint',
        Column::FLOAT => 'double precision',
        Column::DECIMAL => 'decimal',

        Column::DATETIME => 'timestamp without time zone',
        Column::DATE => 'date',
        Column::TIME => 'time',

        Column::BINARY => 'bytea'
    );

    public function select($table, $where, $order, array $params)
    {
        $q = "SELECT * FROM " . $this->getName($table);
        
        $col_idx = 0;
        $q .= $this->getWhere($where, $col_idx, $params);
        $q .= $this->getOrder($order);
        $st = $this->db->prepare($q);

        $st->execute($params);
        return $st;
    }

    public function update($table, $idfield, array $record)
    {
        $id = $record[$idfield];
        if (empty($id))
            throw new DBException("No ID set for record to be updated");

        unset($record[$idfield]);
        if (count($record) == 0)
            throw new DBException("Nothing to update");
        
        $col_idx = 0;
        $params = array();

        $parts = array();
        foreach ($record as $k => $v)
        {
            $col_name = "col" . (++$col_idx);
            $parts[] .= $this->identQuote($k) . " = :{$col_name}";
            $params[$col_name] = $v;
        }

        $q = "UPDATE " . $this->getName($table) . " SET ";
        $q .= implode(", ", $parts);
        $q .= $this->getWhere(array($idfield => $id), $col_idx, $params);

        $st = $this->db->prepare($q);
        $st->execute($params);

        return $st->rowCount();
    }

    public function insert($table, $idfield, array &$record)
    {
        if (!empty($record[$idfield]))
            throw new DAOException("ID set for record to be inserted");

        $q = "INSERT INTO " . $this->getName($table) . " ";
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
        $q .= " VALUES (" . implode(", ", $parts) . ") RETURNING " . $this->identQuote($idfield);
    
        $st = $this->db->prepare($q);

        $this->logger->info("Executing insert query with params {}", $q);
        $st->execute($params);
        $record[$idfield] = $st->fetchColumn(0);

        return $record[$idfield];
    }

    public function upsert($table, $idfield, $conflict, array &$record)
    {
        if (!empty($record[$idfield]))
            return $This->update($table, $idfield, $record);

        $q = "INSERT INTO " . $this->getName($table) . " ";
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

        // Upsert part
        $q .= " ON CONFLICT ";

        $names = array_map(array($this, 'identQuote'), $conflict);
        $q .= '(' . implode(',', $names) . ') ';


        $q .= 'DO UPDATE SET ';

        $conflict = (array)$conflict;
        $parts = array();
        foreach ($record as $field => $value)
        {
            if (in_array($field, $conflict))
                continue;

            $col_name = "col" . (++$col_idx);
            $parts[] = $this->identQuote($field) . ' = :' . $col_name;
            $params[$col_name] = $value;
        }
        $q .= implode(",", $parts);

        $q .= " RETURNING " . $this->identQuote($idfield);
        $st = $this->db->prepare($q);

        $this->logger->info("Executing upsert query with params {}", $params);
        $st->execute($params);
        $record[$idfield] = $st->fetchColumn(0);

        return $record[$idfield];
    }

    public function delete($table, $where)
    {
        $q = "DELETE FROM " . $this->getName($table);
        $col_idx = 0;
        $params = array();
        $q .= $this->getWhere($where, $col_idx, $params);

        $this->logger->info("Model.DAO", "Preparing delete query {}", $q);
        $st = $this->db->prepare($q);
        $st->execute($params);

        return $st->rowCount();
    }

    public function getColumns($table_name)
    {
        try
        {
            $q = $this->db->prepare("
                SELECT column_name, data_type, is_nullable, column_default, numeric_precision, numeric_scale, character_maximum_length, extra
                    FROM information_schema.columns 
                    WHERE table_name = :table AND table_schema = :schema
                    ORDER BY ordinal_position
            ");

            $q->execute(array("table_name" => $table_name, "schema" => $this->schema));

            return $q->fetchAll();
        }
        catch (PDOException $e)
        {
            throw new TableNotExists();
        }
    }

    public function createTable(Table $table)
    {
        $query = "CREATE TABLE " . $this->getName($table->getName()) . " (\n";

        $cols = $table->getColumns();
        $coldefs = array();
        $serial = null;
        foreach ($cols as $c)
        {
            if ($c->getSerial())
                $serial = $c;
            $coldefs[] = $this->getColumnDefinition($c);
        }

        $query .= "    " . implode(",\n    ", $coldefs);
        $query .= "\n)";

        // Create the main table
        $this->db->exec($query);

        // Add indexes
        $serial_col = null;

        $indexes = $table->getIndexes();
        foreach ($indexes as $idx)
            $this->createIndex($table, $idx);

        // Add auto_increment
        if ($serial !== null)
            $this->createSerial($table, $serial);

        // Add foreign keys
        $fks = $table->getForeignKeys();
        foreach ($fks as $fk)
            $this->createForeignKey($table, $fk);
        return $this;
    }

    /**
     * Drop a table
     *
     * @param $table mixed The table to drop
     * @param $safe boolean Add IF EXISTS to query to avoid errors when it does not exist
     * @return Driver Provides fluent interface 
     */
    public function dropTable($table, $safe = false)
    {
        $query = "DROP TABLE " . ($safe ? " IF EXISTS " : "") . $this->getName($table);
        $this->db->exec($query);
        return $this;
    }

    
    public function createIndex(Table $table, Index $idx)
    {
        $cols = $idx->getColumns();
        $names = array();
        foreach ($cols as $col)
            $names[] = $this->identQuote($col);
        $names = '(' . implode(',', $names) . ')';

        if ($idx->getType() === Index::PRIMARY)
        {
            $this->db->exec("ALTER TABLE " . $this->getName($table) . " ADD PRIMARY KEY $names");
            $cols = $idx->getColumns();
            $first_col = $cols[0];
            $col = $table->getColumn($first_col);
            if (count($cols) == 1 && $col->getSerial())
                $serial_col = $col;
        }
        else
        {
            $q = "CREATE ";
            if ($idx->getType() === Index::UNIQUE)
                $q .= "UNIQUE ";
            $q .= "INDEX " . $this->getName($idx) . " ON " . $this->getName($table) . " $names";
            $this->db->exec($q);
        }
        return $this;
    }

    public function dropIndex(Table $table, Index $idx)
    {
        if ($idx->getType() === Index::PRIMARY || $idx->getType() === Index::UNIQUE)
            $q = "ALTER TABLE " . $this->getName($table) . " DROP CONSTRAINT " . $this->getName($idx);
        else
            $q = "DROP INDEX " . $this->identQuote($idx);

        $this->db->exec($q);
        return $this;
    }

    public function createForeignKey(Table $table, ForeignKey $fk)
    {
        $src_table = $table->getName();
        $src_cols = array();

        foreach ($fk->getColumns() as $c)
            $src_cols[] = $this->identQuote($c);

        $tgt_table = $fk->getReferredTable();
        $tgt_cols = array();

        foreach ($fk->getReferredColumns() as $c)
            $tgt_cols[] = $this->identQuote($c);

        $q = 'ALTER TABLE ' . $this->getName($src_table)
            . ' ADD CONSTRAINT ' . $this->getName($fk)
            . ' FOREIGN KEY (' . implode(',', $src_cols) . ') '
            . 'REFERENCES ' . $this->getName($tgt_table)
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
        $this->db->exec("ALTER TABLE DROP CONSTRAINT " . $this->identQuote($name));
        return $this;
    }

    public function createSerial(Table $table, Column $column)
    {
        $tablename = $this->getName($table);
        $colname = $this->identQuote($column->getName());
        $seqname = $this->getName($table->getName() . "_" . $column->getName() . "_seq", false);

        // Create the new sequence
        $this->db->exec("CREATE SEQUENCE $seqname");

        // Change the column type to use the sequence
        $q = "ALTER TABLE {$tablename}"
            . " ALTER COLUMN {$colname} SET DEFAULT nextval('{$seqname}'), "
            . " ALTER COLUMN {$seqname} SET NOT NULL;";
        $this->db->exec($q);

        // Make the sequence owned by the column so it will be automatically
        // removed when the column is removed
        $this->db->exec("ALTER SEQUENCE {$seqname} OWNED BY {$tablename}.{$colname};");

        return $this;
    }

    public function dropSerial(Table $table, Column $column)
    {
        $tablename = $this->getName($table);
        $colname = $this->identQuote($column->getName());
        $seqname = $this->prefix . $table->getName() . "_" . $column->getName() . "_seq";
        
        // Remove the default value for the column
        $this->db->exec("ALTER TABLE {$tablename} ALTER COLUMN {$colname} DROP DEFAULT");

        // Drop the sequence providing the value
        $this->db->exec("DROP SEQUENCE {seqname}");

        $column->setSerial(false);
        $column->setDefault(null);
        return $this;
    }

    public function addColumn(Table $table, Column $column)
    {
        $q = "ALTER TABLE " . $this->getName($table) . " ADD COLUMN " . $this->getColumnDefinition($column);
        $this->db->exec($q);

        return $this;
    }

    public function removeColumn(Table $table, Column $column)
    {
        $q = "ALTER TABLE " . $this->getName($table->getName()) . " DROP COLUMN " . $this->identQuote($column->getName());
        $this->db->exec($q);

        return $this;
    }

    public function getColumnDefinition(Column $col)
    {
        $numtype = $col->getType();
        if (!isset($this->mapping[$numtype]))
            throw new DBException("Unsupported column type: $numtype");

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
        $q->execute(array("schema " => $this->schema, "table" => $table_name));

        return $q->fetchAll();
    }
}
