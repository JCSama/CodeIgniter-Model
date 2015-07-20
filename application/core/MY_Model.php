<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

/**
 * MY_Model Class
 *
 * @package     Applicaton/Core
 * @category    Core
 */
class MY_Model extends CI_Model
{

    /**
     * @var string table name
     */
    protected $table = null;

    /**
     * @var string primary key
     */
    protected $identifier = null;

    /**
     * @var string the field name used as the foreign key in {$table}_lang
     */
    protected $foreign_key = null;

    /**
     * @var array fields names
     */
    protected $fields = array();

    /**
     * @var array multilang fields names if they exists
     */
    protected $fields_lang = array();

    /**
     * @var array
     */
    protected $has_many = array();

    /**
     * @var array
     */
    protected $has_one = array();

    /**
     * @var array
     */
    protected $belongs_to = array();

    /**
     * @var string if the table does not have a created_at field leave it blank
     */
    protected $created_at = '';

    /**
     * @var string if the table does not have an updated_at field leave it blank
     */
    protected $updated_at = '';

    /**
     * @var array
     */
    private $attach = array();

    /**
     * @var array
     */
    private $join = array();

    /**
     * @var array
     */
    protected $filter = array();


    public function __construct()
    {
        parent::__construct();

        /* Check if the identifier and table variables are set */
        if ((empty($this->identifier) OR empty($this->table)) && get_called_class() != get_class()) {
            throw new \Exception("Table name and primary key must be defined in " . get_called_class());
        }

        $this->foreign_key = preg_replace('/(^\id_?)/i', '', $this->identifier) . '_id';
    }

    /**
     * Nom de la table utilisé
     *
     * @return string
     */
    public function get_table_name()
    {
        return $this->table;
    }

    /**
     * @return null
     */
    public function get_identifier()
    {
        return $this->identifier;
    }

    /**
     * @return null|string
     */
    public function get_foreign_key()
    {
        return $this->foreign_key;
    }

    /**
     * @return array
     */
    public function get_fields()
    {
        return array_merge($this->fields, $this->fields_lang);
    }

    /**
     * Get record by ID
     *
     * @param int|array $id
     *
     * @return bool|mixed
     */
    public function get($id)
    {
        if (empty($id))
            return false;

        if (is_numeric($id))
            $row = $this->get_all(array(array($this->identifier, '=', intval($id))));
        elseif (is_array($id))
            $row = $this->get_all($id);

        return array_shift($row);
    }

    /**
     * List all records
     *
     * @param Array $filter
     *
     * @return array
     */
    public function get_all($filter = array())
    {
        $this->set_filter($filter);

        // Join multiling table
        if (count($this->fields_lang)) {
            $this->filter[]['join'] = array($this->table . '_lang', "$this->table.$this->identifier = $this->table" . "_lang.$this->foreign_key", 'INNER');
            $this->db->join($this->table . '_lang', "$this->table.$this->identifier = $this->table" . "_lang.$this->foreign_key", 'INNER');
            $this->db->select(implode(',', $this->fields_lang));
        }

        $this->db->select($this->table . '.*');

        $result = $this->db->get($this->table)->result_object();

        // Attach multiling fields
        $this->set_multilang_data($result);

        // Fetch relation
        $this->fetch_join($result);

        // Fetch children
        $this->fetch_attach($result);

        return $result;

    }

    /**
     * Check if a record exists
     *
     * @param $key $field name
     * @param $value $field value
     * @param array conditions to exclude
     *
     * @return bool
     */
    public function exists($key, $value, $exclude = array())
    {
        if (count($exclude)) {
            foreach ($exclude as $condition => $val) {
                $this->db->where($condition . ' !=', $val);
            }
        }


        $result = $this->db->where($key, $value)->get($this->table);

        return (bool) $result->num_rows();
    }

    /**
     * @param $column
     * @param $order
     *
     * @return $this
     */
    public function order_by($column, $order)
    {
        $this->db->order_by($column, $order);

        return $this;
    }

    /**
     * @param $limit
     * @param $offset
     *
     * @return $this
     */
    public function limit($limit, $offset)
    {
        $this->db->limit($limit, $offset);

        return $this;
    }

    private function set_filter($filter = array())
    {
        if (isset($filter[0]) && !is_array($filter[0]))
            $filter = array($filter);

        // Make filter
        $this->filter = array();

        if ($filter && count($filter)) {
            foreach ($filter as $condition) {
                list($field, $operator, $value) = $condition;
                if ($operator == 'like') {
                    $this->filter[]['like'] = array($field => $value);
                    $this->db->like($field, $value);
                } elseif ($operator == 'in') {
                    if (count($value)) {
                        $this->filter[]['where_in'] = array($field, $value);
                        $this->db->where_in($field, $value);
                    }
                } elseif ($operator == '!in') {
                    if (count($value)) {
                        $this->filter[]['where_not_in'] = array($field, $value);
                        $this->db->where_not_in($field, $value);
                    }
                } else {
                    // Fix null and not null value
                    if (is_null($value)) {
                        $operator = $operator == '!=' ? ' IS NOT NULL' : ' IS NULL';
                        $this->db->where($field . $operator);
                    } else {
                        $this->db->where($field . ' ' . $operator, (string)$value);
                    }

                    $this->filter[]['where'] = $field . ' ' . $operator . ' ' . $value;
                }
            }
        }
    }

    /**
     * Matching object relations based on $has_many, $has_one, $belongs_to attribtues
     *
     * @param string $table
     * @param array $filter
     * @param array $recursive_tables_join
     *
     * @return $this
     * @throws Exception
     */
    public function with($table, $filter = array(), $recursive_tables_join = array())
    {
        $aliases = array_merge($this->belongs_to, $this->has_many, $this->has_one);

        if (!isset($aliases[$table])) {
            throw new \Exception("Table name ({$table}) has no relation with (" . $this->table . ") table in " . get_called_class());
        }

        if (isset($filter[0]) && !is_array($filter[0]))
            $filter = array($filter);

        if (isset($this->belongs_to[$table])) {
            $this->join[$table] = array(
                'model' => $this->belongs_to[$table],
                'filter' => $filter,
                'recursive_tables_join' => $recursive_tables_join
            );
        } elseif (isset($this->has_many[$table]) OR isset($this->has_one[$table])) {
            $this->attach[$table] = array(
                'model' => isset($this->has_many[$table]) ? $this->has_many[$table] : $this->has_one[$table],
                'filter' => $filter,
            );
        }

        return $this;
    }

    /**
     * Fetch object relations when it's ManyToOne relation
     *
     * @param array $result
     *
     * @return array
     * @throws Exception
     */
    private function fetch_join($result)
    {
        if (!count($this->join) OR !count($result)) {
            return $result;
        }

        $foreignKeys = array();

        foreach ($this->join as $table) {
            $this->load->model($table['model']);

            if (!is_object($this->$table['model'])) {
                throw new \Exception("Cannot load ({$table['model']})");
            }

            foreach ($result as $object) {
                $primary_key = $this->$table['model']->get_identifier();
                $foreign_key = $this->$table['model']->get_foreign_key();

                $foreignKeys[$primary_key][$object->{$foreign_key}] = $object->{$foreign_key};
            }
        }

        $updatedResults = $rows = array();

        foreach ($this->join as $table_name => $table) {

            if (count($this->join[$table_name]['recursive_tables_join']))
                foreach ($this->join[$table_name]['recursive_tables_join'] as $recursive_join)
                    $this->{$table['model']}->with($recursive_join);

            $rows[$table_name] = $this->{$table['model']}->get_all(
                array_merge(
                    array(array($this->{$table['model']}->get_identifier(), 'in', $foreignKeys[$this->{$table['model']}->get_identifier()])),
                    $this->join[$table_name]['filter']
                )
            );
        }

        if (count($rows)) {
            foreach ($result as $object) {
                foreach ($rows as $table => $data) {
                    if (count($data) == 0)
                        break;

                    foreach ($data as $row) {
                        $primary_key = $this->{$this->join[$table]['model']}->get_identifier();
                        $foreign_key = $this->{$this->join[$table]['model']}->get_foreign_key();

                        if ($object->$foreign_key == $row->$primary_key)
                            $object->{$table} = $row;
                    }
                }

                $updatedResults[] = $object;
            }
        }

        $this->join = array();

        return $updatedResults;
    }

    /**
     * Fetch object relations when it's OneToMany relation
     *
     * @param $result
     *
     * @return mixed
     * @throws Exception
     */
    private function fetch_attach($result)
    {
        if (!count($this->attach) OR !count($result)) {
            return $result;
        }
        // Extract objects IDs
        foreach ($result as $object) {
            $ids[$object->{$this->identifier}] = $object->{$this->identifier};
        }

        foreach ($this->attach as $table_name => $table) {
            $this->load->model($table['model']);

            if (!is_object($this->{$table['model']})) {
                throw new \Exception("Cannot load ({$table['model']})");
            }
            $rows = $this->{$table['model']}->get_all(
                array_merge(
                    $this->attach[$table_name]['filter'],
                    array(array($this->foreign_key, 'in', $ids)))
            );

            if (count($rows) == 0)
                continue;

            foreach ($result as $object) {
                foreach ($rows as $row) {
                    if ($object->{$this->identifier} == $row->{$this->foreign_key}) {
                        if (!isset($object->{$table_name}))
                            $object->{$table_name} = array();

                        array_push($object->{$table_name}, $row);
                    }
                }
            }
        }

        $this->attach = array();

        return $result;
    }

    /**
     * Extracting multilang fileds data from {$table}_lang
     *
     * @param $result
     */
    private function set_multilang_data(&$result)
    {
        if (!count($this->fields_lang) OR !count($result))
            return;

        foreach ($result as $row) {
            if (count($this->filter)) {
                foreach ($this->filter as $value) {
                    $array_keys = array_keys($value);
                    $key = array_shift($array_keys);
                    $array_values = array_values($value);
                    $value = array_shift($array_values);

                    if ($key == 'where' && preg_match('/(lang_id?)/', $value))
                        return;
                }
            }

            $ids[$row->{$this->identifier}] = $row->{$this->identifier};
        }

        // Get data from {$table}_lang
        $result_lang = $this->db->where_in($this->foreign_key, $ids)
            ->get($this->table . '_lang')
            ->result_object();

        foreach ($result as $row) {
            if (count($result_lang)) {
                foreach ($this->fields_lang as $field_lang) {
                    unset($row->$field_lang);
                    $data = array();
                    foreach ($result_lang as $field) {
                        $data[$field->lang_id] = $field->$field_lang;
                    }

                    $row->$field_lang = $data;
                }
            }
        }
    }


    /**
     * Get All records by IDs
     *
     * @param array|int $ids
     *
     * @return array
     */
    public function get_by_id($ids)
    {
        if (!is_array($ids))
            $ids = array($ids);

        return $this->db->where_in($this->identifier, $ids)
            ->get($this->table)
            ->result_object();

    }

    /**
     * Save object (add or update)
     *
     * @param array $object
     * @param int|null $id
     *
     * @return boolean || int
     */
    public function save(&$object, $id = null)
    {
        if (!$object)
            return false;

        return intval($id) > 0 ? $this->update($object, $id) : $this->add($object);
    }

    /**
     * Add new record
     *
     * @param array $object
     *
     * @return mixed
     * @throws Exception
     */
    public function add(&$object)
    {
        /* Automatically fill dates */
        if (!empty($this->created_at)) {
            $this->db->set(array($this->created_at => date('Y-m-d H:i:s')));
        }

        if (!empty($this->updated_at)) {
            $this->db->set(array($this->updated_at => date('Y-m-d H:i:s')));
        }

        $this->_bind_object_properties($object);
        $fields = $this->get_untranslated_fields($object);

        $this->db->insert($this->table, $fields);
        $object[$this->identifier] = $this->db->insert_id();
        $this->save_translated_fields($object);

        $object = (object)$object;

        return $object->{$this->identifier};
    }

    /**
     * Update record by ID
     *
     * @param array $object
     * @param int $id
     *
     * @return mixed
     * @throws Exception
     */
    public function update($object, $id)
    {
        /* Automatically fill dates */
        if (!empty($this->updated_at)) {
            $this->db->set(array($this->updated_at => date('Y-m-d H:i:s')));
        }

        $this->_bind_object_properties($object);
        $fields = $this->get_untranslated_fields($object);
        $object[$this->identifier] = $id;
        $this->save_translated_fields($object, $id);

        if (count($fields)) {
            $this->db->where($this->identifier, $id);
            $this->db->update($this->table, $fields);
        }

        return $id;
    }

    /**
     * Delete record by ID
     *
     * @param int $id
     * @param bool|false $deleted in set to TRUE, the table must have a deleted column so it can be updated, it will be
     *                            no physical delete.
     *
     * @return bool
     * @throws Exception
     */
    public function delete($id, $deleted = false)
    {
        if (!is_numeric($id))
            return false;

        if (!is_array($id))
            $id = array($id);

        $this->db->where_in($this->identifier, $id);

        if ($deleted)
            return $this->db->update($this->table, array('deleted' => 1));

        return $this->db->delete($this->table);
    }

    /**
     * Delete all records from table
     *
     * @param null $table
     */
    public function clear_table($table = null)
    {
        $table = is_null($table) ? $this->table : $table;
        $this->db->empty_table($table);
    }

    /**
     * Save multiple object
     *
     * @param array $rows
     */
    public function save_multiple($rows)
    {
        $this->db->insert_batch($this->table, $rows);
    }

    /**
     * Remove unmatched variables
     *
     * @param $object array
     *
     * @throws Exception
     */
    private function _bind_object_properties(&$object)
    {
        if (!count($this->fields))
            throw new \Exception("Fields names must be defined in " . get_called_class());

        $fields = array_merge($this->fields, $this->fields_lang);

        if (count($object)) {
            foreach ($object as $field_name => $value) {
                if (!in_array($field_name, $fields))
                    unset($object[$field_name]);
            }
        }
    }

    /**
     * Count all results
     *
     * @return mixed
     * @throws Exception
     */
    public function count_all_results()
    {
        if (count($this->filter)) {
            foreach ($this->filter as $value) {
                $array_keys = array_keys($value);
                $key = array_shift($array_keys);
                $array_values = array_values($value);
                $value = array_shift($array_values);

                if ($key == 'join') {
                    $this->db->{$key}($value[0], $value[1], @$value[2]);
                } elseif ($key == 'where_in') {
                    $this->db->{$key}($value[0], $value[1]);
                } else {
                    $this->db->{$key}($value);
                }

            }
        }

        return $this->db->count_all_results($this->table);
    }

    /**
     * @param $object array
     *
     * @return array
     */
    protected function get_untranslated_fields($object)
    {
        unset($object[$this->identifier]);

        if (count($this->fields_lang)) {
            $fields = array();
            foreach ($object as $field => $value) {
                if (!in_array($field, $this->fields_lang)) {
                    $fields[$field] = $value;
                }
            }

            return $fields;
        }

        return $object;
    }

    /**
     * @param $object
     * @param null $id
     */
    protected function save_translated_fields(&$object, $id = null)
    {
        if (count($this->fields_lang)) {
            $fields = array();

            foreach ($this->fields_lang as $field) {
                if (isset($object[$field]) && is_array($object[$field])) {
                    foreach ($object[$field] as $key => $value) {
                        $fields[$key][$field] = $value;
                    }
                }
            }

            if (!count($fields))
                return;

            $arr_fields = array();
            foreach ($fields as $key => $field) {
                $arr_fields[] = array_merge(array($this->foreign_key => $object[$this->identifier], 'lang_id' => $key), $field);
            }

            if (!is_null($id)) {
                $this->db->ar_where = array();
                $this->db->delete($this->table . '_lang', array($this->foreign_key => $id));
            }
            $this->db->insert_batch($this->table . '_lang', $arr_fields);
        }
    }

    /**
     * @param $object
     * @param $old_object
     */
    protected function update_translated_fields($object, $old_object)
    {
        if (count($this->fields_lang)) {
            $fields = array();

            foreach ($this->fields_lang as $field) {
                if (isset($object[$field]) && is_array($object[$field])) {
                    foreach ($object[$field] as $key => $value) {
                        $fields[$key][$field] = $value;
                    }
                }
            }

            if (!count($fields))
                return;

            $arr_fields = array();
            foreach ($fields as $key => $field) {
                $arr_fields[] = array_merge(array($this->foreign_key => $old_object[$this->identifier], 'lang_id' => $key), $field);
            }

            $this->db->ar_where = array();
            $this->db->where($this->foreign_key, $object[$this->identifier]);
            $this->db->delete($this->table . '_lang');

            $this->db->insert_batch($this->table . '_lang', $arr_fields);
        }
    }

}