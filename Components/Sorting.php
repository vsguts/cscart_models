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

    protected $directions = array(
        'asc' => 'asc',
        'desc' => 'desc',
    );

    public function prepare()
    {
        $sort_fields = $this->model->getSortFields();

        if (empty($this->params['sort_by']) || empty($sort_fields[$this->params['sort_by']])) {
            $this->params['sort_by'] = key($sort_fields);
        }

        if (empty($this->params['sort_order']) || empty($this->directions[$this->params['sort_order']])) {
            $default_direction = $this->model->getSortDefaultDirection();
            $this->params['sort_order'] = !empty($default_direction) ? $default_direction : key($this->directions);
        }

        $sorting = $sort_fields[$this->params['sort_by']];
        if (is_array($sorting)) {
            $sorting = implode(' ' . $this->directions[$this->params['sort_order']] . ', ', $sorting);
        }

        if (!empty($sorting)) {
            $sorting .= ' ' . $this->directions[$this->params['sort_order']];
            $this->params['sort_order_rev'] = $this->params['sort_order'] == 'asc' ? 'desc' : 'asc';
        }

        $this->result = $sorting;
    }

    public function get()
    {
        if (!empty($this->result)) {
            return ' ORDER BY ' . $this->result;
        }

        return '';
    }

}
