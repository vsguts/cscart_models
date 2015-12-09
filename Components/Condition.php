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

    public function prepare()
    {
        $condition = array();
        $table_name = $this->model->getTableName();
        $search_fields = $this->model->getSearchFields();
        $primary_field = $this->model->getPrimaryField();

        if (isset($this->params['ids'])) {
            $condition[] = db_quote("$table_name.$primary_field IN(?a)", (array) $this->params['ids']);
        }

        if (isset($this->params['not_ids'])) {
            $condition[] = db_quote("$table_name.$primary_field NOT IN(?a)", (array) $this->params['not_ids']);
        }

        if (!empty($search_fields['number'])) {
            foreach ($search_fields['number'] as $_key => $_field) {
                $param = !is_numeric($_key) ? $_key : $_field;
                $fields = (array) $_field;
                if (isset($this->params[$param]) && fn_string_not_empty($this->params[$param])) {
                    $sub_condition = array();
                    foreach ($fields as $field) {
                        $sub_condition[] = db_quote("$field = ?i", $this->params[$param]);
                    }
                    $condition[] = $this->mixSubConditions($sub_condition);
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
                    if (!empty($this->params[$param . '_' . $_range_name])) {
                        $sub_condition = array();
                        foreach ($fields as $field) {
                            $sub_condition[] = db_quote(
                                "$field ?p ?i", $_range_symbol, $this->params[$param . '_' . $_range_name]
                            );
                        }
                        $condition[] = $this->mixSubConditions($sub_condition);
                    }
                }
            }
        }

        if (!empty($search_fields['in'])) {
            foreach ($search_fields['in'] as $_key => $_field) {
                $param = !is_numeric($_key) ? $_key : $_field;
                $fields = (array) $_field;
                if (!empty($this->params[$param])) {
                    $_in_values = !is_array($this->params[$param])
                        ? explode(',', $this->params[$param])
                        : $this->params[$param];
                    $sub_condition = array();
                    foreach ($fields as $field) {
                        $sub_condition[] = db_quote("$field IN(?a)", $_in_values);
                    }
                    $condition[] = $this->mixSubConditions($sub_condition);
                }
            }
        }

        if (!empty($search_fields['not_in'])) {
            foreach ($search_fields['not_in'] as $_key => $_field) {
                $param = !is_numeric($_key) ? $_key : $_field;
                $fields = (array) $_field;
                if (!empty($this->params[$param])) {
                    $_in_values = !is_array($this->params[$param])
                        ? explode(',', $this->params[$param])
                        : $this->params[$param];
                    $sub_condition = array();
                    foreach ($fields as $field) {
                        $sub_condition[] = db_quote("$field NOT IN(?a)", $_in_values);
                    }
                    $condition[] = $this->mixSubConditions($sub_condition);
                }
            }
        }

        if (!empty($search_fields['string'])) {
            foreach ($search_fields['string'] as $_key => $_field) {
                $param = !is_numeric($_key) ? $_key : $_field;
                $fields = (array) $_field;
                if (isset($this->params[$param]) && fn_string_not_empty($this->params[$param])) {
                    $sub_condition = array();
                    foreach ($fields as $field) {
                        $sub_condition[] = db_quote("$field LIKE ?s", trim($this->params[$param]));
                    }
                    $condition[] = $this->mixSubConditions($sub_condition);
                }
            }
        }

        if (!empty($search_fields['text'])) {
            foreach ($search_fields['text'] as $_key => $_field) {
                $param = !is_numeric($_key) ? $_key : $_field;
                $fields = (array) $_field;
                if (isset($this->params[$param]) && fn_string_not_empty($this->params[$param])) {
                    $sub_condition = array();
                    $like = '%' . trim($this->params[$param]) . '%';
                    foreach ($fields as $field) {
                        $sub_condition[] = db_quote("$field LIKE ?l", $like);
                    }
                    $condition[] = $this->mixSubConditions($sub_condition);
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

                $period = !empty($this->params[$param . 'period']) ? $this->params[$param . 'period'] : null;
                $from = !empty($this->params[$param . 'time_from']) ? $this->params[$param . 'time_from'] : 0;
                $to = !empty($this->params[$param . 'time_to']) ? $this->params[$param . 'time_to'] : 0;

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
                    $condition[] = $this->mixSubConditions($sub_condition);
                } else {
                    if (!empty($this->params[$param . '_from'])) {
                        $sub_condition = array();
                        foreach ($fields as $field) {
                            $sub_condition[] = db_quote("$field >= ?i", $this->params[$param . '_from']);
                        }
                        $condition[] = $this->mixSubConditions($sub_condition);
                    }
                    if (!empty($this->params[$param . '_to'])) {
                        $sub_condition = array();
                        foreach ($fields as $field) {
                            $sub_condition[] = db_quote("$field <= ?i", $this->params[$param . '_to']);
                        }
                        $condition[] = $this->mixSubConditions($sub_condition);
                    }
                }
            }
        }

        $this->result = array_filter(array_merge($condition, (array) $this->model->getExtraCondition($this->params)));
    }

    public function get()
    {
        if (!empty($this->result)) {
            return ' WHERE ' . implode(' AND ', $this->result);
        }

        return '';
    }

    protected function mixSubConditions($sub_condition)
    {
        if (count($sub_condition) > 1) {
            return '(' . implode(' OR ', $sub_condition) . ')';
        } else {
            return reset($sub_condition);
        }
    }

}
