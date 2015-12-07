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

    public $result = array();

    protected $model;
    protected $params;
    protected $joins;
    protected $condition;

    public function __construct(IModel $model, Array &$params, $joins = array(), $condition = array())
    {
        $this->model     = $model;
        $this->params    = &$params;
        $this->joins     = $joins;
        $this->condition = $condition;

        $this->prepare();
    }

    /**
     * Preparing result
     */
    abstract public function prepare();

    /**
     * Getting result with convertion to string
     * @return array
     */
    abstract public function get();

}
