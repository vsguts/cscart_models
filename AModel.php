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

use Tygh\Models\Components\Fields;
use Tygh\Models\Components\Sorting;
use Tygh\Models\Components\Joins;
use Tygh\Models\Components\Condition;
use Tygh\Models\Components\Limit;
use Tygh\Navigation\LastView;

abstract class AModel implements IModel, \IteratorAggregate, \ArrayAccess
{

    protected static $_models = array();

    protected $_id;
    protected $_attributes = array();

    protected $_auth;
    protected $_area;
    protected $_lang_code;
    protected $_error;

    protected static $_enum_elements = array();

    public function __construct($params = array(), $attributes = array())
    {
        $params = array_merge(array(
            'auth' => & $_SESSION['auth'],
            'area' => AREA,
            'lang_code' => CART_LANGUAGE,
        ), $params);

        $this->_auth = $params['auth'];
        $this->_area = $params['area'];
        $this->_lang_code = $params['lang_code'];

        if (!empty($attributes)) {
            $this->_load($attributes);
        }
    }

    public function __set($name, $value)
    {
        $this->_attributes[$name] = $value;
    }

    public function __get($name)
    {
        if (isset($this->_attributes[$name])) {
            return $this->_attributes[$name];
        }

        return null;
    }

    public function __isset($name)
    {
        return isset($this->_attributes[$name]);
    }

    public function __unset($name)
    {
        unset($this->_attributes[$name]);
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
        return new Iterator($this->_attributes);
    }

    public static function model($params = array())
    {
        $class = get_called_class();

        if (!isset(static::$_models[$class])) {
            static::$_models[$class] = new static($params);
        }

        return static::$_models[$class];
    }

    public function setAuth(&$auth)
    {
        $this->_auth = &$auth;
    }

    public function findMany($params = array())
    {
        $this->beforeFind();

        $params = array_merge(array(
            'lang_code' => $this->_lang_code,
        ), $params);

        // Init filter
        if ($last_view_object_name = $this->getLastViewObjectName()) {
            $params = LastView::instance()->update($last_view_object_name, $params);
        }

        $fields    = new    Fields($this, $params);
        $sorting   = new   Sorting($this, $params);
        $joins     = new     Joins($this, $params);
        $condition = new Condition($this, $params, $joins);
        $limit     = new     Limit($this, $params, $joins, $condition);

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

        $this->_gatherAdditionalItemsData($items, $params);

        if (!empty($params['to_array'])) {
            $models = $items;
        } else {
            $models = $this->_loadMany($items, true);
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
                $this->_attributes = array_merge($this->_attributes, $attributes);
            }
        }

        return $this->_attributes;
    }

    public function save()
    {
        if ($this->beforeSave()) {
            $result = $this->isNewRecord() ? $this->_insert() : $this->_update();

            $this->unlockTables();

            $this->_load($this->find($this->_id));

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
                $this->_id
            );

            if ($is_deleted && !empty($description_table_name)) {
                $is_deleted = db_query(
                    sprintf("DELETE FROM %s WHERE %s = ?s", $description_table_name, $primary_field),
                    $this->_id
                );
            }

            if ($is_deleted) {
                $this->_id = null;
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

    protected function _insert()
    {
        $table_name = $this->getTableName();
        $description_table_name = $this->getDescriptionTableName();
        $primary_field = $this->getPrimaryField();

        $_data = $this->_prepareAttributes();

        $result = db_query("INSERT INTO $table_name ?e", $_data);
        
        if ($this->_primaryAutoIncrement()) {
            $this->_id = $result;
        } else {
            $this->_id = $this->{$primary_field};
        }

        if (!empty($description_table_name)) {
            $_data[$primary_field] = $this->_id;
            foreach (fn_get_translation_languages() as $_data['lang_code'] => $v) {
                db_query("INSERT INTO $description_table_name ?e", $_data);
            }
        }

        $this->{$primary_field} = $this->_id;

        return true;
    }

    protected function _update()
    {
        $table_name = $this->getTableName();
        $primary_field = $this->getPrimaryField();
        $description_table_name = $this->getDescriptionTableName();

        $_data = $this->_prepareAttributes();

        db_query("UPDATE $table_name SET ?u WHERE $primary_field = ?s", $_data, $this->_id);

        if (!empty($description_table_name)) {
            db_query(
                "UPDATE $description_table_name SET ?u WHERE $primary_field = ?s AND lang_code = ?s",
                $_data, $this->_id, $this->_lang_code
            );
        }

        return true;
    }

    public function isNewRecord()
    {
        return empty($this->_id);
    }

    public function getFields($params)
    {
        return array(
            $this->getTableName() . '.*',
        );
    }

    protected function _prepareAttributes()
    {
        $attributes = $this->_attributes;

        if ($this->_primaryAutoIncrement()) {
            if (isset($attributes[$this->getPrimaryField()])) {
                unset($attributes[$this->getPrimaryField()]);
            }
        }

        return $attributes;
    }

    protected function _load($attributes, $find = false)
    {
        $primary_field = $this->getPrimaryField();

        if (isset($attributes[$primary_field])) {
            $this->_id = $attributes[$primary_field];
        }

        $this->attributes($attributes);

        if ($find) {
            $this->afterFind();
        }
    }

    protected function _loadMany($items, $find = false)
    {
        $models = array();

        foreach ($items as $item) {
            $model = new static(array(
                'auth' => $this->_auth,
                'area' => $this->_area,
                'lang_code' => $this->_lang_code,
            ));
            $model->_load($item, $find);

            $models[] = $model;
        }

        return $models;
    }

    protected function _gatherAdditionalItemsData(&$items, $params)
    {
    }

    protected function _primaryAutoIncrement()
    {
        return true;
    }

    public function getSortDefaultDirection()
    {
        return 'asc';
    }

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
                . " AND $description_table_name.lang_code = ?s", $this->_lang_code
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

    public function beforeFind()
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

    public function enumElements($field_name)
    {
        if (empty(self::$_enum_elements[$field_name])) {
            $column_info = db_get_row('SHOW COLUMNS FROM ?p WHERE Field = ?s', $this->getTableName(), $field_name);
            
            self::$_enum_elements[$field_name] = [];

            if (
                !empty($column_info)
                && preg_match('/^(\w{3,4})\s?\((.+)\)/s', $column_info['Type'], $matches)
                && in_array($matches[1], ['set', 'enum'])
            ) {
                $elements = explode(',', $matches[2]);

                self::$_enum_elements[$field_name] = $this->trim($elements, "'");
            }
        }

        return self::$_enum_elements[$field_name];
    }

    public function enumElementsLang($field_name, $prefix = '', $lang_code = DESCR_SL)
    {
        $elements = $this->enumElements($field_name);
        $lang_elements = array();
        foreach ($elements as $element) {
            $lang_elements[$element] = __($prefix . $element, [], $lang_code);
        }
        
        return $lang_elements;
    }

    public function getError()
    {
        return $this->_error;
    }

    public function setError($error)
    {
        $this->_error = $error;
    }

    protected function trim($str, $chars = null)
    {
        if (is_array($str)) {
            foreach ($str as &$s) {
                $s = $this->trim($s, $chars);
            }
        } else {
            $str = trim($str, $chars);
        }

        return $str;
    }

}
