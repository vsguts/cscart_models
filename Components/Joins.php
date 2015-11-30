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

class Joins extends AComponent
{

    protected $_result;

    protected function _prepare()
    {
        $table_name = $this->_model->getTableName();
        $this->_result = $this->_model->getJoins($this->_params);
    }

    public function get()
    {
        if (!empty($this->_result)) {
            return ' ' . implode(' ', $this->_result);
        }

        return '';
    }

}
