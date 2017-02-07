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
use WASP\DB\DBException;

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

    public function generateDSN(array $config)
    {
        foreach (array('hostname', 'database') as $req)
            if (!isset($config[$req]))
                throw new DBException("Required field missing: " . $req);

        $ssl = (!empty($config['ssl'])) ? " sslmode=require" : "";
        $port = (!empty($config['port'])) ? " port=" . $config['port'] : "";

        return "pgsql:dbname=" . $config['database'] . " host=" . $config['hostname'] . $ssl . $port;
    }

    public function setDatabaseName($dbname, $schema = null)
    {
        $this->dbname = $dbname;
        $this->schema = $schema !== null ? $schema : "public";

        return $this;
    }

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
                SELECT column_name, data_type, is_nullable, column_default, numeric_precision, numeric_scale, character_maximum_length 
                    FROM information_schema.columns 
                    WHERE table_name = :table AND table_schema = :schema
                    ORDER BY ordinal_position
            ");

            $q->execute(array("table" => $table_name, "schema" => $this->schema));

            return $q->fetchAll();
        }
        catch (PDOException $e)
        {
            var_dump($e);
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
            $type = strtolower($col['data_type']);
            $numtype = array_search($type, $this->mapping);
            if ($numtype === false)
                throw new DBException("Unsupported field type: " . $type);
            
            $column = new Column(
                $col['column_name'],
                $numtype,
                $col['character_maximum_length'],
                $col['numeric_precision'],
                $col['numeric_scale'],
                $col['is_nullable'],
                $col['column_default']
            );

            // TODO: Postgresify
            if ($numtype === Column::ENUM)
            {
                // Extract values from enum
                $vals = substr($col['column_type'], 5, -1); //  Remove 'ENUM(' and ')'
                $enum_values = explode(',', $vals);
                $vals = array();
                foreach ($enum_values as $val)
                    $vals[] = trim($val, "'");
                $column->setEnumValues($vals);
            }

            $table->addColumn($column);

            // TODO: detect serial / nextval columns
            //if (strtolower($col['extra']) === "auto_increment")
            //{
            //    $pkey = new Index(Index::PRIMARY);
            //    $pkey->addColumn($column);
            //    $table->addIndex($pkey);

            //    $column->setSerial(true);
            //    $serial = $column;
            //}
        }

        $constraints = $this->getConstraints($table_name);
        foreach ($constraints as $constraint)
        {
            $constraint['name'] = $this->stripPrefix($constraint['name']);
            $table->addIndex($constraint);
        }
        
        // Get update/delete policy from foreign keys
        $fks = $this->getForeignKeys($table_name);
        foreach ($fks as $fk)
        {
            $fk['name'] = $this->stripPrefix($fk['name']);
            $table->addForeignKey(new ForeignKey($fk));
        }

        return $table;
    }

    public function getForeignKeys($table_name)
    {
        $q = "
        SELECT conname,
            pg_catalog.pg_get_constraintdef(r.oid, true) as condef
        FROM pg_catalog.pg_constraint r
        WHERE r.conrelid = :table::regclass AND r.contype = 'f' ORDER BY 1;
        ";
        $q = $this->db->prepare($q);
        $tname = $this->getName($table_name, false);
        $q->execute(array("table" => $tname));

        $fks = array();
        foreach ($q as $row)
        {
            $name = $row['conname'];
            $def = $row['condef'];

            if (!preg_match('/^FOREIGN KEY \(([\w\d\s,_]+)\) REFERENCES ([\w\d]+)\(([\w\d\s,_]+)\)[\s]*(ON UPDATE (CASCADE|RESTRICT|SET NULL))?[\s]*(ON DELETE (CASCADE|RESTRICT|SET NULL))?$/', $def, $matches))
                throw new DBException("Invalid condef: " . $def);
            $columns = explode(", ", $matches[1]);
            $reftable = $matches[2];
            $refcolumns = explode(", ", $matches[3]);

            $update_policy = isset($matches[5]) ? $matches[5] : "RESTRICT";
            $delete_policy = isset($matches[7]) ? $matches[5] : "RESTRICT";

            $fks[] = array('name' => $name, 'column' => $columns, 'referred_table' => $reftable, 'referred_column' => $refcolumns, "on_update" => $update_policy, "on_delete" => $delete_policy);
        }

        var_dump($fks);

        return $q->fetchAll();
    }

    public function getConstraints($table_name)
    {
        $q = "
        SELECT 
            c2.relname AS name, 
            i.indisprimary AS is_primary, 
            i.indisunique AS is_unique, 
            pg_catalog.pg_get_indexdef(i.indexrelid, 0, true) AS indexdef,
            pg_catalog.pg_get_constraintdef(con.oid, true) AS constraintdef
        FROM 
            pg_catalog.pg_class c, pg_catalog.pg_class c2, pg_catalog.pg_index i
        LEFT JOIN
            pg_catalog.pg_constraint con ON (conrelid = i.indrelid AND conindid = i.indexrelid AND contype IN ('p','u','x'))
        WHERE c.oid = :table::regclass AND c.oid = i.indrelid AND i.indexrelid = c2.oid
        ORDER BY i.indisprimary DESC, i.indisunique DESC, c2.relname;
        ";

        $table_name = $this->getName($table_name, false);
        $q = $this->db->prepare($q);
        $q->execute(array("table" => $table_name));

        $constraints = array();
        foreach ($q as $row)
        {
            $name = $row['name'];
            $primary = $row['is_primary'];
            $unique = $row['is_unique'];
            $indexdef = $row['indexdef'];
            $constraintdef = $row['constraintdef'];

            if ($primary)
            {
                if (!preg_match('/^PRIMARY KEY \(([\w\d\s,_]+)\)$/', $constraintdef, $matches))
                    throw new DBException("Invalid primary key: $constraintdef");

                $columns = explode(", ", $matches[1]);
                $constraints[] = array(
                    'type' => 'PRIMARY',
                    'column' => $columns
                );
            }
            elseif ($unique)
            {
                if (!preg_match('/^UNIQUE \(([\w\d\s,_]+)\)$/', $constraintdef, $matches))
                    throw new DBException("Invalid unique key: $constraintdef");

                $columns = explode(", ", $matches[1]);
                $constraints[] = array(
                    'type' => 'UNIQUE',
                    'column' => $columns,
                    'name' => $name
                );
            }
            else
            {
                $qname = preg_quote($name, '/');
                $tname = preg_quote($table_name, '/');
                var_dump($indexdef);
                if (!preg_match('/^CREATE INDEX ' . $qname . ' ON ' . $tname . ' (USING ([\w]+))?\s*\((.+)\)(\s*WHERE (.*))?$/', $indexdef, $matches))
                    throw new DBException("Invalid index: $indexdef");

                var_dump($matches);
                $algo = $matches[2];
                $columns = explode(", ", $matches[3]);
                $constraints[] = array(
                    'type' => 'INDEX',
                    'column' => $columns,
                    'name' => $name,
                    'algorithm' => $algo,
                    'condition' => isset($matches[5]) ? $matches[5] : null
                );
                var_dump(end($constraints));
            }
        }

        return $q->fetchAll();
    }

    public function getIndexes($table_name)
    {
        $q = "SHOW INDEX FROM " . $this->getName($table_name);
        $q = $this->db->prepare($q);
        $q->execute();

        return $q->fetchAll();
    }
}
