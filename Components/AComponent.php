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

use Tygh\Models\IModel;

abstract class AComponent
{

    protected $_model;
    protected $_params;
    protected $_joins;
    protected $_condition;

    public function __construct(IModel $model, Array &$params, $joins = array(), $condition = array())
    {
        $this->_model = $model;
        $this->_params = &$params;
        $this->_joins = $joins;
        $this->_condition = $condition;

        $this->_prepare();
    }

    abstract protected function _prepare();

    abstract protected function get();

}
