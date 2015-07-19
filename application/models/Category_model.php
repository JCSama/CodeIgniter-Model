<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

/**
 * Category_model Class
 *
 * @package     Application/models
 * @category    Models
 */
class Category_model extends MY_Model
{

    /**
     * @var string table name,
     * If you want to activate the multilang CRUD operations,
     * you should create another table with '{$table}_lang' as a name in this case it will be advert_lang
     * and this table advert_lang must contain only the multilang fields mentioned in $fields_lang variable
     */
    protected $table = 'category';

    /**
     * @var string primary key
     */
    protected $identifier = 'id_category';

    /**
     * @var array table fields
     */
    protected $fields = array(
        'parent',
    );

    /**
     * @var array contain multilang fields only from {$table}_lang table
     */
    protected $fields_lang = array(
        'name',
        'description'
    );

    /**
     * For OneToMany relation, use this variable
     * alias => Model_name : this alias is used as a parameter for the ->with(alias) function
     *
     * @var array
     */
    protected $has_many = array(
        'advert' => 'Advert_model'
    );

    /**
     * @var string if the table does not have a created_at field leave it blank
     */
    protected $created_at = 'created_at';

    /**
     * @var string if the table does not have an updated_at field leave it blank
     */
    protected $updated_at = 'updated_at';

}