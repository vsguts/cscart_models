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

class Sorting extends AComponent
{

    protected $_result;

    protected $_directions = array(
        'asc' => 'asc',
        'desc' => 'desc',
    );

    public function _prepare()
    {
        $sort_fields = $this->_model->getSortFields();

        if (empty($this->_params['sort_by']) || empty($sort_fields[$this->_params['sort_by']])) {
            $this->_params['sort_by'] = key($sort_fields);
        }

        if (empty($this->_params['sort_order']) || empty($this->_directions[$this->_params['sort_order']])) {
            $default_direction = $this->_model->getSortDefaultDirection();
            $this->_params['sort_order'] = !empty($default_direction) ? $default_direction : key($this->_directions);
        }

        $sorting = $sort_fields[$this->_params['sort_by']];
        if (is_array($sorting)) {
            $sorting = implode(' ' . $this->_directions[$this->_params['sort_order']] . ', ', $sorting);
        }

        if (!empty($sorting)) {
            $sorting .= ' ' . $this->_directions[$this->_params['sort_order']];
            $this->_params['sort_order_rev'] = $this->_params['sort_order'] == 'asc' ? 'desc' : 'asc';
        }

        $this->_result = $sorting;
    }

    public function get()
    {
        if (!empty($this->_result)) {
            return ' ORDER BY ' . $this->_result;
        }

        return '';
    }

}
