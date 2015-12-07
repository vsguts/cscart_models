<?php
/***************************************************************************
*                                                                          *
*   (c) 2004 Vladimir V. Kalynyak, Alexey V. Vinokurov, Ilya M. Shalnev    *
*                                                                          *
* This  is  commercial  software,  only  users  who have purchased a valid *
* license  and  accept  to the terms of the  License Agreement can install *
* and use this program.                                                    *
*                                                                          *
****************************************************************************
* PLEASE READ THE FULL TEXT  OF THE SOFTWARE  LICENSE   AGREEMENT  IN  THE *
* "copyright.txt" FILE PROVIDED WITH THIS DISTRIBUTION PACKAGE.            *
****************************************************************************/

namespace Tygh\Models;

use Tygh\Languages\Languages;
use Tygh\Models\Components\Fields;
use Tygh\Models\Components\Sorting;
use Tygh\Models\Components\Joins;
use Tygh\Models\Components\Condition;
use Tygh\Models\Components\Limit;
use Tygh\Navigation\LastView;

abstract class AModel implements IModel, \IteratorAggregate, \ArrayAccess
{

    protected static $models = array();

    protected $id;

    protected $attributes = array();

    protected $params = array();

    protected static $_enum_elements = array();

    public function __construct($params = array(), $attributes = array())
    {
        $this->params = array_merge(array(
            'lang_code' => CART_LANGUAGE,
        ), $params);

        if (!empty($attributes)) {
            $this->load($attributes);
        }
    }

    public function __set($name, $value)
    {
        $this->attributes[$name] = $value;
    }

    public function __get($name)
    {
        if (isset($this->attributes[$name])) {
            return $this->attributes[$name];
        }

        return null;
    }

    public function __isset($name)
    {
        return isset($this->attributes[$name]);
    }

    public function __unset($name)
    {
        unset($this->attributes[$name]);
    }

    /**
     * Returns whether there is an element at the specified offset.
     * This method is required by the interface ArrayAccess.
     *
     * @param  mixed   $offset the offset to check on
     * @return boolean
     */
    public function offsetExists($offset)
    {
        return property_exists($this, $offset);
    }

    /**
     * Returns the element at the specified offset.
     * This method is required by the interface ArrayAccess.
     *
     * @param  integer $offset the offset to retrieve element.
     * @return mixed   the element at the offset, null if no element is found at the offset
     */
    public function offsetGet($offset)
    {
        return $this->$offset;
    }

    /**
     * Sets the element at the specified offset.
     * This method is required by the interface ArrayAccess.
     *
     * @param integer $offset the offset to set element
     * @param mixed   $item   the element value
     */
    public function offsetSet($offset, $item)
    {
        $this->$offset = $item;
    }

    /**
     * Unsets the element at the specified offset.
     * This method is required by the interface ArrayAccess.
     * @param mixed $offset the offset to unset element
     */
    public function offsetUnset($offset)
    {
        unset($this->$offset);
    }

    /**
     * Returns an iterator for traversing the attributes in the model.
     * This method is required by the interface IteratorAggregate.
     *
     * @return CMapIterator an iterator for traversing the items in the list.
     */
    public function getIterator()
    {
        return new Iterator($this->attributes);
    }

    public static function model($params = array())
    {
        $class = get_called_class();

        if (!isset(static::$models[$class])) {
            static::$models[$class] = new static($params);
        }

        return static::$models[$class];
    }

    public function findMany($params = array())
    {
        $this->beforeFind($params);

        $params = array_merge($this->params, $params);

        // Init filter
        if ($last_view_object_name = $this->getLastViewObjectName()) {
            $params = LastView::instance()->update($last_view_object_name, $params);
        }

        $fields    = new    Fields($this, $params);
        $sorting   = new   Sorting($this, $params);
        $joins     = new     Joins($this, $params);
        $condition = new Condition($this, $params, $joins);

        $this->prepareQuery($params, $fields->result, $sorting->result, $joins->result, $condition->result);

        $limit = new Limit($this, $params, $joins, $condition);

        if (!empty($params['get_count']) && isset($params['total_items'])) {
            return $params['total_items'];
        }

        $query_foundation =
            " FROM " . $this->getTableName()
            . $joins->get()
            . $condition->get()
            . $sorting->get()
            . $limit->get()
        ;

        if (!empty($params['get_ids'])) {
            return db_get_fields("SELECT " . $this->getTableName() . "." . $this->getPrimaryField() . $query_foundation);
        }

        $items = db_get_array("SELECT " . $fields->get() . $query_foundation);

        $this->gatherAdditionalItemsData($items, $params);

        if (!empty($params['to_array'])) {
            $models = $items;
        } else {
            $models = $this->loadMany($items, true);
        }

        if (!empty($params['return_params'])) {
            return array($models, $params);
        }

        return $models;
    }

    public function find($id, $params = array())
    {
        if (is_array($id) && empty($params)) {
            $params = $id;
            $id = 0;
        }

        if (!empty($id)) {
            $params['ids'] = $id;
        }

        $params['limit'] = 1;

        $items = $this->findMany($params);

        return reset($items);
    }

    public function findAll($params = array())
    {
        return $this->findMany($params);
    }

    public function attributes($attributes = array())
    {
        if (!empty($attributes)) {
            if (!is_array($attributes) && is_a($attributes, 'Tygh\Models\IModel')) {
                $attributes = $attributes->attributes();
            }

            if (is_array($attributes)) {
                $this->attributes = array_merge($this->attributes, $attributes);
            }
        }

        return $this->attributes;
    }

    public function save()
    {
        if ($this->beforeSave()) {
            $result = $this->isNewRecord() ? $this->insert() : $this->update();

            $this->unlockTables();

            $this->load($this->find($this->id));

            $this->afterSave();

            return $result;
        }

        return false;
    }

    public function delete()
    {
        $is_deleted = false;

        if (!$this->isNewRecord() && $this->beforeDelete()) {

            $table_name = $this->getTableName();
            $description_table_name = $this->getDescriptionTableName();
            $primary_field = $this->getPrimaryField();

            $is_deleted = db_query(
                sprintf('DELETE FROM %s WHERE %s = ?s', $table_name, $primary_field),
                $this->id
            );

            if ($is_deleted && !empty($description_table_name)) {
                $is_deleted = db_query(
                    sprintf("DELETE FROM %s WHERE %s = ?s", $description_table_name, $primary_field),
                    $this->id
                );
            }

            if ($is_deleted) {
                $this->id = null;
                $this->afterDelete();
            }
        }

        return $is_deleted;
    }

    public function deleteMany($params)
    {
        $models = $this->findMany($params);

        foreach ($models as $model) {
            $model->delete();
        }

        return true;
    }

    protected function insert()
    {
        $table_name = $this->getTableName();
        $description_table_name = $this->getDescriptionTableName();
        $primary_field = $this->getPrimaryField();

        $_data = $this->prepareAttributes();

        $result = db_query("INSERT INTO $table_name ?e", $_data);
        
        if ($this->primaryAutoIncrement()) {
            $this->id = $result;
        } else {
            $this->id = $this->{$primary_field};
        }

        if (!empty($description_table_name)) {
            $_data[$primary_field] = $this->id;
            foreach (Languages::getAll() as $_data['lang_code'] => $v) {
                db_query("INSERT INTO $description_table_name ?e", $_data);
            }
        }

        $this->{$primary_field} = $this->id;

        return true;
    }

    protected function update()
    {
        $table_name = $this->getTableName();
        $primary_field = $this->getPrimaryField();
        $description_table_name = $this->getDescriptionTableName();

        $_data = $this->prepareAttributes();

        db_query("UPDATE $table_name SET ?u WHERE $primary_field = ?s", $_data, $this->id);

        if (!empty($description_table_name)) {
            db_query(
                "UPDATE $description_table_name SET ?u WHERE $primary_field = ?s AND lang_code = ?s",
                $_data, $this->id, $this->params['lang_code']
            );
        }

        return true;
    }

    public function isNewRecord()
    {
        return empty($this->id);
    }

    public function getFields($params)
    {
        return array(
            $this->getTableName() . '.*',
        );
    }

    protected function prepareAttributes()
    {
        $attributes = $this->attributes;

        if ($this->primaryAutoIncrement()) {
            if (isset($attributes[$this->getPrimaryField()])) {
                unset($attributes[$this->getPrimaryField()]);
            }
        }

        return $attributes;
    }

    protected function load($attributes, $find = false)
    {
        $primary_field = $this->getPrimaryField();

        if (isset($attributes[$primary_field])) {
            $this->id = $attributes[$primary_field];
        }

        $this->attributes($attributes);

        if ($find) {
            $this->afterFind();
        }
    }

    protected function loadMany($items, $find = false)
    {
        $models = array();

        foreach ($items as $item) {
            $model = new static(array(
                'lang_code' => $this->params['lang_code'],
            ));
            $model->load($item, $find);

            $models[] = $model;
        }

        return $models;
    }

    /**
     * Rewrite if need
     * 
     * @param  array &$items Selected items
     * @param  array $params Params
     */
    protected function gatherAdditionalItemsData(&$items, $params)
    {
    }

    protected function primaryAutoIncrement()
    {
        return true;
    }

    public function getSortDefaultDirection()
    {
        return 'asc';
    }

    /**
     * Ability to rewrite query parts. 
     * 
     * @param array  $params    Params
     * @param array  $fields    Fields
     * @param array  $sorting   Sorting
     * @param array  $joins     Joins
     * @param array  $condition Condition
     * @param string $limit     Limit
     */
    public function prepareQuery(&$params, &$fields, &$sorting, &$joins, &$condition)
    {
    }

    /**
     * Setting extra conditions
     * 
     * @param  array $params Params
     * @return array Conditions
     */
    public function getExtraCondition($params)
    {
        return array();
    }

    public function getJoins($params)
    {
        $joins = array();

        $description_table_name = $this->getDescriptionTableName();

        if (!empty($description_table_name)) {

            $table_name = $this->getTableName();
            $primary_field = $this->getPrimaryField();

            $joins[] = db_quote(
                " LEFT JOIN $description_table_name"
                . " ON $description_table_name.$primary_field = $table_name.$primary_field"
                . " AND $description_table_name.lang_code = ?s", $this->params['lang_code']
            );
        }

        return $joins;
    }

    public function getSortFields()
    {
        return array(
            'id' => $this->getPrimaryField()
        );
    }

    public function getLastViewObjectName()
    {
        return false; // disabled by default
    }

    public function getDescriptionTableName()
    {
        return '';
    }

    // Events

    public function beforeFind(&$params)
    {
    }

    public function afterFind()
    {
    }

    public function beforeSave()
    {
        return true;
    }

    public function afterSave()
    {
    }

    public function beforeDelete()
    {
        return true;
    }

    public function afterDelete()
    {
    }

}
