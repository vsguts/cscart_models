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

use Tygh\Registry;

class Partner extends AModel
{
    public function getTableName()
    {
        return '?:partners';
    }

    public function getPrimaryField()
    {
        return 'partner_id';
    }

    public function getFields($params)
    {
        return array(
            $this->getTableName() . '.*',
        );
    }

    public function getSortFields()
    {
        return array(
            'id' => 'partner_id',
            'name' => 'name',
            'status' => 'status',
        );
    }

    public function getSearchFields()
    {
        return array(
            'string' => array(
                'status' => 'status',
                'name'   => 'name',
            ),
        );
    }

    public function getExtraCondition($params)
    {
        $condition = [];
        $table_name = $this->getTableName();

        $company_id = 0;
        if (fn_allowed_for('ULTIMATE') && $company_id = Registry::get('runtime.company_id')) {
            $condition[] = db_quote("$table_name.company_id = ?i", $company_id);
        }

        return $condition;
    }

    public function getStatuses()
    {
        return array(
            'A' => __('active', '', $this->_lang_code),
            'D' => __('disabled', '', $this->_lang_code),
        );
    }

}
