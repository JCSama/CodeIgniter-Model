<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

/**
 * Advert_model Class
 *
 * @package     Application/models
 * @category    Models
 */
class Advert_model extends MY_Model
{

    /**
     * @var string table name,
     * If you want to activate the multilang CRUD operations,
     * you should create another table with '{$table}_lang' as a name in this case it will be advert_lang
     * and this table advert_lang must contain only the multilang fields mentioned in $fields_lang variable
     */
    protected $table = 'advert';

    /**
     * @var string primary key
     */
    protected $identifier = 'id_advert';

    /**
     * @var string the field name used as the foreign key in {$table}_lang and other tables
     */
    protected $foreign_key = 'advert_id';

    /**
     * @var array table fields
     */
    protected $fields = array(
        'title',
        'description',
        'deleted',
        'category_id',
    );

    /**
     * @var array contain multilang fields only from {$table}_lang table
     */
    protected $fields_lang = array();

    /**
     * @var string if the table does not have a created_at field leave it blank
     */
    protected $created_at = 'created_at';

    /**
     * @var string if the table does not have an updated_at field leave it blank
     */
    protected $updated_at = 'updated_at';

    /**
     * For ManyToOne relation, use this variable
     *
     * @var array
     */
    protected $belongs_to = array(
        'category' => 'Category_model',
    );

}