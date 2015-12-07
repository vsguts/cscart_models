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

interface IModel
{

    // Abstract

    public static function model();

    public function findMany($params = array());

    public function findAll($params = array());

    public function find($id, $params = array());

    public function save();

    public function delete();

    public function deleteMany($params);

    public function isNewRecord();

    // Events

    public function beforeFind($params);

    public function afterFind();

    public function beforeSave();

    public function afterSave();

    public function beforeDelete();

    public function afterDelete();

    // Instance

    public function getTableName();

    public function getPrimaryField();

    public function getFields($params);

    public function getSearchFields();

    public function getSortFields();

    public function getSortDefaultDirection();

    public function getExtraCondition($params);

    public function getJoins($params);

    public function getLastViewObjectName();

    public function getDescriptionTableName();

}
