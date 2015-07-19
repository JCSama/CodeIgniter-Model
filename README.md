# CodeIgniter Model Class

CodeIgniter Model is an extended class for CI_Model, it allows you to manipulate CRUD operations in an easy way.
It support multilang and relations between tables.

# Installation

CodeIgniter Versoin >= 2.x.x


Copy the file `MY_Model.php` to the `application/core/` folder.

# Usage

Create a Model that extends from MY_Model and set the variables :

```php
protected $table = 'my_table';

protected $identifier = 'id_my_table';

protected $foreign_key = 'my_table_id';

protected $fields = array(
    'field_1',
    'field_2',
);
```
# Example
Assuming that we have 2 tables advertisement and category, each advertisement belongs to a category, so a category can have
one or many advertisements, which means our tables may look like this :
NOTE : The category table has multilang fields so we're gonna put them in category_lang table
NOTE : {table}_lang must respect the recommended structure

 -----------------------
| category              |
 -----------------------
| id_category (int)     |
| parent (int)          |
| created_at (datetime) |
| updated_at (datetime) |
 -----------------------
 
 -----------------------
| category_lang         |
 -----------------------
| category_id (int)     |
| lang_id (int)         |
| title (varchar)       |
| description (varchar) |
 -------------------
  
 -----------------------
| advert                |
 -----------------------
| id_advert (int)       |
| category_id (int)     |
| title (int)           |
| description (int)     |
| deleted (tinyInt)     |
| created_at (datetime) |
| updated_at (datetime) |
 -----------------------

## Fetch records
After that, you can call the Model within your controller for instance like following :

```php
// Return all records
$this->load->model('New_model');
$records = $this->New_model->get_all();
var_dump($records);

// Return record with id=10
$record = $this->New_model->get(10);
var_dump($record);

## Fetch records with criteria
```php
// You can also add some criteria like following :
$criteria = array();
$criteria[] = array('title', 'like', 'test');
$criteria[] = array('id_my_table', '=', 10);

$result = $this->New_model->get_all($criteria);
                
var_dump($result);
```

## Fetch record with association
```php
$result = $this->Advert_model
                ->with('category')
                ->order_by('created_at', 'DESC')
                ->get_all($criteria);
                
var_dump($result);

$result = $this->Category_model
                ->with('advert')
                ->get_all();
                
var_dump($result);
```

## Fetch records with multiple associations
```php
$result = $this->Advert_model
                ->with('category')
                ->with('alias_2')
                ->with('alias_3')
                ->order_by('created_at', 'DESC')
                ->get_all($criteria);
                
var_dump($result);
```

## Fetch records with filter on the associations
```php
$filter = array();
$filter[] = array('lang_id', '=', 2);
$result = $this->Advert_model
                ->with('category', $filter)
                ->with('alias_2')
                ->with('alias_3')
                ->order_by('created_at', 'DESC')
                ->get_all($criteria);
                
var_dump($result);
```

## Fetch records with pagination
```php
$page = 1;
$total_items_per_page = 100;
$records = $this->Advert_model
                ->limit($total_items_per_page, $page)
                ->order_by('created_at', 'DESC')
                ->get_all($criteria);

$total_records = $this->Advert_model->count_all_results();
var_dump($records);
```

## Add new record
```php
$data = array(
  'title' => 'New Advertisement',
  'description' => 'New Advertisement',
  'deleted' => 0,
  'category_id' => 23,
);
$this->Advert_model->save($data);
```

## Update record
```php
$id_record = 10;
$data = array(
  'title' => 'Update Advertisement',
  'description' => 'Update Advertisement',
  'deleted' => 0,
  'category_id' => 23,
);
$this->Advert_model->save($data, $id_record);
```

## Delete record
```php
$id_record = 10;
$this->Advert_model->delete($id_record);
```