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

class Condition extends AComponent
{

    protected $_result = array();

    protected function _prepare()
    {
        $condition = array();
        $table_name = $this->_model->getTableName();
        $search_fields = $this->_model->getSearchFields();
        $primary_field = $this->_model->getPrimaryField();

        if (isset($this->_params['ids'])) {
            $condition[] = db_quote("$table_name.$primary_field IN(?a)", (array) $this->_params['ids']);
        }

        if (isset($this->_params['not_ids'])) {
            $condition[] = db_quote("$table_name.$primary_field NOT IN(?a)", (array) $this->_params['not_ids']);
        }

        if (!empty($search_fields['number'])) {
            foreach ($search_fields['number'] as $_key => $_field) {
                $param = !is_numeric($_key) ? $_key : $_field;
                $fields = (array) $_field;
                if (isset($this->_params[$param]) && fn_string_not_empty($this->_params[$param])) {
                    $sub_condition = array();
                    foreach ($fields as $field) {
                        $sub_condition[] = db_quote("$field = ?i", $this->_params[$param]);
                    }
                    $condition[] = $this->_mixSubConditions($sub_condition);
                }
            }
        }

        if (!empty($search_fields['range'])) {
            $ranges = array(
                'from' => '>=',
                'to' => '<=',
            );
            foreach ($search_fields['range'] as $_key => $_field) {
                $param = !is_numeric($_key) ? $_key : $_field;
                $fields = (array) $_field;
                foreach ($ranges as $_range_name => $_range_symbol) {
                    if (!empty($this->_params[$param . '_' . $_range_name])) {
                        $sub_condition = array();
                        foreach ($fields as $field) {
                            $sub_condition[] = db_quote("$field ?p ?i", $_range_symbol, $this->_params[$param . '_' . $_range_name]);
                        }
                        $condition[] = $this->_mixSubConditions($sub_condition);
                    }
                }
            }
        }

        if (!empty($search_fields['in'])) {
            foreach ($search_fields['in'] as $_key => $_field) {
                $param = !is_numeric($_key) ? $_key : $_field;
                $fields = (array) $_field;
                if (!empty($this->_params[$param])) {
                    $_in_values = !is_array($this->_params[$param]) ? explode(',', $this->_params[$param]) : $this->_params[$param];
                    $sub_condition = array();
                    foreach ($fields as $field) {
                        $sub_condition[] = db_quote("$field IN(?a)", $_in_values);
                    }
                    $condition[] = $this->_mixSubConditions($sub_condition);
                }
            }
        }

        if (!empty($search_fields['not_in'])) {
            foreach ($search_fields['not_in'] as $_key => $_field) {
                $param = !is_numeric($_key) ? $_key : $_field;
                $fields = (array) $_field;
                if (!empty($this->_params[$param])) {
                    $_in_values = !is_array($this->_params[$param]) ? explode(',', $this->_params[$param]) : $this->_params[$param];
                    $sub_condition = array();
                    foreach ($fields as $field) {
                        $sub_condition[] = db_quote("$field NOT IN(?a)", $_in_values);
                    }
                    $condition[] = $this->_mixSubConditions($sub_condition);
                }
            }
        }

        if (!empty($search_fields['string'])) {
            foreach ($search_fields['string'] as $_key => $_field) {
                $param = !is_numeric($_key) ? $_key : $_field;
                $fields = (array) $_field;
                if (isset($this->_params[$param]) && fn_string_not_empty($this->_params[$param])) {
                    $sub_condition = array();
                    foreach ($fields as $field) {
                        $sub_condition[] = db_quote("$field LIKE ?s", trim($this->_params[$param]));
                    }
                    $condition[] = $this->_mixSubConditions($sub_condition);
                }
            }
        }

        if (!empty($search_fields['text'])) {
            foreach ($search_fields['text'] as $_key => $_field) {
                $param = !is_numeric($_key) ? $_key : $_field;
                $fields = (array) $_field;
                if (isset($this->_params[$param]) && fn_string_not_empty($this->_params[$param])) {
                    $sub_condition = array();
                    $like = '%' . trim($this->_params[$param]) . '%';
                    foreach ($fields as $field) {
                        $sub_condition[] = db_quote("$field LIKE ?l", $like);
                    }
                    $condition[] = $this->_mixSubConditions($sub_condition);
                }
            }
        }

        if (!empty($search_fields['time'])) {
            $process_time = function($time) {
                return str_replace('.', '/', $time);
            };

            foreach ($search_fields['time'] as $_key => $_field) {
                $param = !is_numeric($_key) ? $_key : $_field;
                $fields = (array) $_field;

                $period = !empty($this->_params[$param . 'period']) ? $this->_params[$param . 'period'] : null;
                $from = !empty($this->_params[$param . 'time_from']) ? $this->_params[$param . 'time_from'] : 0;
                $to = !empty($this->_params[$param . 'time_to']) ? $this->_params[$param . 'time_to'] : 0;

                if (!empty($from) || !empty($to)) {
                    list($from, $to) = fn_create_periods(array(
                        'period' => $period,
                        'time_from' => $process_time($from),
                        'time_to' => $process_time($to),
                    ));
                    $sub_condition = array();
                    foreach ($fields as $field) {
                        $sub_condition[] = db_quote(
                            "($field >= ?i AND $field <= ?i)",
                            $from, $to
                        );
                    }
                    $condition[] = $this->_mixSubConditions($sub_condition);
                } else {
                    if (!empty($this->_params[$param . '_from'])) {
                        $sub_condition = array();
                        foreach ($fields as $field) {
                            $sub_condition[] = db_quote("$field >= ?i", $this->_params[$param . '_from']);
                        }
                        $condition[] = $this->_mixSubConditions($sub_condition);
                    }
                    if (!empty($this->_params[$param . '_to'])) {
                        $sub_condition = array();
                        foreach ($fields as $field) {
                            $sub_condition[] = db_quote("$field <= ?i", $this->_params[$param . '_to']);
                        }
                        $condition[] = $this->_mixSubConditions($sub_condition);
                    }
                }
            }
        }

        $this->_result = array_filter(array_merge($condition, (array) $this->_model->getExtraCondition($this->_params)));
    }

    public function get()
    {
        if (!empty($this->_result)) {
            return ' WHERE ' . implode(' AND ', $this->_result);
        }

        return '';
    }

    protected function _mixSubConditions($sub_condition)
    {
        if (count($sub_condition) > 1) {
            return '(' . implode(' OR ', $sub_condition) . ')';
        } else {
            return reset($sub_condition);
        }
    }

}
