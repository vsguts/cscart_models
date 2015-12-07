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

    protected function prepare()
    {
        $table_name = $this->model->getTableName();
        $field = $this->model->getPrimaryField();

        if (!empty($this->params['items_per_page']) || !empty($this->params['get_count'])) {

            if (empty($this->params['page'])) {
                $this->params['page'] = 1;
            }

            $this->params['total_items'] = db_get_field(
                "SELECT COUNT(DISTINCT($table_name.$field))"
                . " FROM $table_name"
                . $this->joins->get()
                . $this->condition->get()
            );

            if (!empty($this->params['items_per_page'])) {
                $this->result = db_paginate($this->params['page'], $this->params['items_per_page']);
            }
        }

        if (!empty($this->params['limit'])) {
            $this->result = db_quote(' LIMIT 0, ?i', $this->params['limit']);
        }
    }

    public function get()
    {
        return $this->result;
    }

}
