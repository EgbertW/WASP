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
use PDOException;

class DAO
{
    /** A mapping between class identifier and full, namespaced class name */
    protected static $classes = array();
    /** A mapping between full, namespaced class name and class identifier */
    protected static $classnames = array();

    /** Override to set the name of the ID field */
    protected static $idfield = "id";

    /** Override to set the name of the table */
    protected static $table = null;

    /** The columns defined in the database */
    protected static $columns = null;

    /** The quote character for identifiers */
    protected static $ident_quote = '`';

    /** The ID value */
    protected $id;
    
    /** The database record */
    protected $record;

    /** The associated ACL entity */
    protected $acl_entity = null;

    protected function db()
    {
        return DB::get();
    }

    public function save()
    {
        $idf = static::$idfield;
        if (isset($this->record[$idf]))
            self::update($this->record);
        else
            $this->id = self::insert($this->record);
        $this->initACL();
    }

    protected function load($id)
    {
        $idf = static::$idfield;
        $rec = static::fetchSingle(array($idf => $id));
        if (empty($rec))
            throw new DAOEXception("Object not found with $id");

        $this->record = $rec;
        $this->id = $id;
        $this->initACL();
        $this->init();
    }

    public function remove()
    {
        $idf = static::$idfield;
        if ($this->id === null)
            throw new DAOException("Object does not have a ID");

        static::delete(array($idf => $this->id));
        $this->id = null;
        $this->record = null;
        $this->removeACL();
    }

    // Override to perform initialization after record has been loaded
    protected function init()
    {}

    // Create an object from a database record or a ID
    public static function get($id)
    {
        $idf = static::$idfield;
        if (!\is_int_val($id))
        {
            if (!is_array($id) || empty($id[$idf]))
                throw new DAOException("Cannot initialize object from $id");
            $record = $id;
            $id = $record[$idf];
        }
        else
        {
            $record = static::fetchSingle(array($idf => $id));
            if (empty($record))
                return null;
        }

        $class = get_called_class();
        $obj = new $class();
        $obj->id = $id;
        $obj->record = $record;
        $obj->init();
        return $obj;
    }

    // Create an object from a database record or a ID
    public static function getAll($where = array(), $order = array(), $params = array())
    {
        $list = array();
        $records = static::fetchAll($where, $order, $params);
        foreach ($records as $record)
            $list[] = static::get($record);
        return $list;
    }

    protected static function fetchSingle($where = array(), $order = array(), array $params = array())
    {
        $st = static::select($where, $order, $params);
        return $st->fetch();
    }

    protected static function fetchAll($where = array(), $order = array(), array $params = array())
    {
        $st = static::select($where, $order, $params);
        return $st->fetchAll();
    }

    protected static function select($where = array(), $order = array(), array $params = array())
    {
        $db = DB::get();
        $q = "SELECT * FROM " . self::identQuote(static::tablename());
        
        $col_idx = 0;
        $q .= static::getWhere($where, $col_idx, $params);
        $q .= static::getOrder($order);

        \Debug\info("Model.DAO", "Preparing query {}", $q);
        $st = $db->prepare($q);

        $st->execute($params);
        return $st;
    }

    protected static function update(array $record)
    {
        $idf = static::$idfield;
        if (!isset($record[$idf]))
            throw new DAOException("No ID set for record to be updated");

        $q = "UPDATE " . self::identQuote(static::tablename()) . " SET ";

        $id = $record[$idf];
        if (empty($id))
            throw new DAOException("No ID set for record to be updated");

        unset($record[$idf]);

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

        $q .= implode(", ", $parts);
        $q .= static::getWhere(array($idf => $id), $col_idx, $params);

        \Debug\info("Model.DAO", "Preparing update query {}", $q);
        $db = DB::get();
        $st = $db->prepare($q);

        $st->execute($params);

        return $st->rowCount();
    }

    protected static function insert(array &$record)
    {
        $idf = static::$idfield;
        if (!empty($record[$idf]))
            throw new DAOException("ID set for record to be inserted");

        $q = "INSERT INTO " . self::identQuote(static::tablename()) . " ";
        $fields = array_map(array("Model\\DAO", "identQuote"), array_keys($record));
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
    
        \Debug\info("Model.DAO", "Preparing insert query {}", $q);
        $db = DB::get();
        $st = $db->prepare($q);

        \Debug\info("Model.DAO", "Executing insert query with params {}", $q);
        $st->execute($params);
        $record[$idf] = $db->lastInsertId();

        return $record[$idf];
    }

    protected static function delete($where)
    {
        $q = "DELETE FROM " . self::identQuote(static::tablename());
        $col_idx = 0;
        $params = array();
        $q .= static::getWhere($where, $col_idx, $params);
        var_dump($q);

        \Debug\info("Model.DAO", "Preparing delete query {}", $q);
        $db = DB::get();
        $st = $db->prepare($q);
        $st->execute($params);

        return $st->rowCount();
    }

    protected static function getWhere($where, &$col_idx, array &$params)
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

    protected static function getOrder($order)
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

    public static function identQuote($ident)
    {
        return '`' . str_replace("`", "``", $ident) . "`";
    }

    public function getID()
    {
        return $this->id;
    }

    public function __get($field)
    {
        if (isset($this->record[$field]))
            return $this->record[$field];
        return null;
    }

    public function __set($field, $value)
    {
        $correct = $this->validate($field, $value);
        if ($correct !== true)
            throw new DAOException("Field $field cannot be set to $value: {$correct}");

        $this->record[$field] = $value;
    }
    
    // Override to perform checks
    public function validate($field, $value)
    {
        return true;
    }

    /**
     * Override to provide a list of parent objects where this object can 
     * inherit permissions from. Used by the ACL permission system.
     */
    protected function getParents()
    {
        return array();
    }

    /**
     * Check if an action is allowed on this object. If the ACL subsystem
     * is not loaded, true will be returned.
     *
     * @param $action scalar The action to be performed
     * @param $role WASP\ACL\Role The role that wants to perform an action. 
     *                           If not specified, the current user is used.
     * @return boolean True if the action is allowed, false if it is not
     * @throws WASP\ACL\Exception When the role or the action is invalid
     */
    public function isAllowed($action, $role = null)
    {
        if ($this->acl_entity === null)
        {
            if (class_exists("WASP\\ACL\\Rule", false))
                return WASP\ACL\Rule::getDefaultPolicy();

            return true;
        }

        if ($role === null)
            $role = WASP\Request::$current_role;

        return $this->acl_entity->isAllowed($role, $action, array(get_class($this), "loadByACLID"));
    }

    /**
     * This method will load a new instance to be used in ACL inheritance
     */
    public static function loadByACLID($id)
    {
        $parts = explode("#", $id);
        if (count($parts) !== 2)
            throw new \RuntimeException("Invalid DAO ID: {$id}");

        if (!isset(self::$classes[$parts[0]))
            throw new \RuntimeException("Invalid DAO type: {$parts[0]}");

        $classname = self::$classes[$parts[0]];
        $id = (int)$parts[1];

        return new $classname($id);
    }

    /**
     * Return the ACL Entity that manages permissions on this object
     *
     * @return WASP\ACL\Entity The ACL Entity that manages permissions
     */
    public function getACL()
    {
        return $this->acl_entity;
    }

    /**
     * Set up the ACL entity. This is called after the init() method,
     * so that ID and parents can be set up before calling.
     */
    protected function initACL()
    {
        if (!class_exists("WASP\\ACL\\Entity", false))
            return;
        
        $id = \WASP\ACL\Entity::generateID($this);
        if (!\WASP\ACL\Entity::hasInstance($id))
            $this->acl_entity = new \WASP\ACL\Entity($id, $this->getParents());
        else
            $this->acl_entity = \WASP\ACL\Entity::getInstance($id);
    }

    /**
     * Return the name of the object class to be used in ACL entity naming.
     */
    public static function registerClass($name)
    {
        if (isset(self::$classes[$name]))
            throw new \RuntimeException("Cannot register the same name twice");

        $cl = get_called_class();
        self::$classes[$name] = $cl;
        self::$classesnames[$cl] = $name;
    }

    public static function tablename()
    {
        return static::$table;
    }

    public static function quoteIdentity($identity)
    {
        $identity = str_replace(self::$ident_quote, self::$ident_quote . self::$ident_quote, $identity);
        return self::$ident_quote . $identity . self::$ident_quote;
    }

    public static function getColumns()
    {
        if ($this->columns === null)
        {
            $config = Config::getConfig();
            $dbname = $config->get('sql', 'database');
            $schema = $config->get('sql', 'schema');
            if (empty($schema))
                $schema = $dbname;

            $db = DB::get();
            try
            {
                $q = $db->prepare("
                    SELECT column_name, data_type, is_nullable, column_default, numeric_precision, numeric_scale, character_maximum_length
                        FROM information_schema.columns 
                        WHERE table_name = :table AND table_schema = :schema
                        ORDER BY ordinal_position
                ");

                $q->execute(array("table_name" => self::tablename(), "schema" => $schema));

                $this->columns = $q->fetchAll();
            }
            catch (PDOException $e)
            {
                throw new TableNotExists();
            }
        }
        return $this->columns;
    }
}
