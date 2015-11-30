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

namespace Tygh\Models\Components;

class Limit extends AComponent
{

    protected $_result;

    protected function _prepare()
    {
        $table_name = $this->_model->getTableName();
        $field = $this->_model->getPrimaryField();

        if (!empty($this->_params['items_per_page']) || !empty($this->_params['get_count'])) {

            if (empty($this->_params['page'])) {
                $this->_params['page'] = 1;
            }

            $this->_params['total_items'] = db_get_field(
                "SELECT COUNT(DISTINCT($table_name.$field))"
                . " FROM $table_name"
                . $this->_joins->get()
                . $this->_condition->get()
            );

            if (!empty($this->_params['items_per_page'])) {
                $this->_result = db_paginate($this->_params['page'], $this->_params['items_per_page']);
            }
        }

        if (!empty($this->_params['limit'])) {
            $this->_result = db_quote(' LIMIT 0, ?i', $this->_params['limit']);
        }
    }

    public function get()
    {
        return $this->_result;
    }

}
