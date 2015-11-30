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
use Tygh\Mailer;
use Tygh\Settings;

class Notification extends AModel
{
    public function getTableName()
    {
        return '?:notifications';
    }

    public function getDescriptionTableName()
    {
        return '?:notification_descriptions';
    }

    public function getPrimaryField()
    {
        return 'notification_id';
    }

    public function getFields($params)
    {
        return array(
            $this->getTableName() . '.*',
            $this->getDescriptionTableName() . '.*',
        );
    }

    public function getSortFields()
    {
        return array(
            'id' => '?:notifications.notification_id',
            'subject' => 'subject',
            'code' => 'code',
            'status' => 'status',
            'type' => 'type',
        );
    }

    public function getSortDefaultDirection()
    {
        return 'desc';
    }

    public function getSearchFields()
    {
        return array(
            'number' => array(
                'company_id',
            ),
            'string' => array(
                'status'        => 'status',
                'code'          => 'code',
                'subject'       => 'subject',
                'type'          => 'type',
            ),
            'not_in' => array(
                'exclude_ids'   => '?:notifications.notification_id',
            )

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

    public function getTypes()
    {
        return array(
            'customer_created',
            'subscription_created',
            'subscription_activated',
            'subscription_suspended',
            'subscription_disabled',
            'subscription_product_changed',
            'trial_expires_in_period',
            'trial_expired',
            'invoice_issued',
        );
    }

    public function getAvailableTypes()
    {
        $condition = [];;

        if (!$this->isNewRecord()) {
            $condition[] = db_quote("type != ?s", $this->_attributes['type']);
        }

        if (fn_allowed_for('ULTIMATE')) {
            $condition[] = db_quote("company_id = ?i", Registry::get('runtime.company_id'));
        }

        $condition = !empty($condition) ? ' WHERE ' . implode(' AND ', $condition) : '';

        $existed_types = db_get_fields("SELECT DISTINCT(type) FROM {$this->getTableName()} $condition");

        return array_diff($this->getTypes(), $existed_types);
    }

    public function getBasicPlaceholdersDescription()
    {
        return array(
            '%FIRSTNAME%'       => 'user_firstname',
            '%LASTNAME%'        => 'user_lastname',
            '%EMAIL%'           => 'user_email',
            '%COMPANY_NAME%'    => 'company_name',
            '%COMPANY_EMAIL%'   => 'company_email',
            '%COMPANY_PHONE%'   => 'company_phone',
            '%ORDER_ID%'        => 'order_id',
            '%ORDER_URL%'       => 'order_url',
        );
    }

    public function beforeSave()
    {
        $res = parent::beforeSave();

        $this->type = trim($this->type);
        $this->code = trim($this->code);

        if (empty($this->type)) {
            $this->type = null;
        }

        if (empty($this->code)) {
            $this->code = null;
        } else if (
            self::find([
                'code' => $this->code,
                'company_id' => $this->company_id,
                'exclude_ids' => $this->_id,
            ])
        ) {
            fn_set_notification('E', __('error'), __('notification_code_already_exists'));
            return false;
        }

        return $res;
    }

    public function renderBody($replacements = array())
    {
        return self::render($this->body, $replacements);
    }

    public function renderSubject($replacements = array())
    {
        return self::render($this->subject, $replacements);
    }

    public function send($to, $from, $replacements = array())
    {
        $default_replacements = array();
        foreach (fn_get_company_data($this->company_id) as $k => $v) {
            $default_replacements['company_' . $k] = $v;
        }

        if (fn_allowed_for('ULTIMATE')) {
            $section = Settings::instance()->getSectionByName('Company');
            $settings_data = Settings::instance()->getList($section['section_id'], 0, false, $this->company_id, $this->_lang_code);
            foreach ($settings_data['main'] as $v) {
                $default_replacements[$v['name']] = $v['value'];
            }
        }

        $replacements = array_merge($default_replacements, $replacements);

        return Mailer::sendMail(array(
            'to' => $to,
            'from' => $from,
            'data' => array(
                'subject' => $this->renderSubject($replacements),
                'body' => $this->renderBody($replacements),
            ),
            'tpl' => 'addons/licenses_and_subscriptions/notification.tpl',
            'company_id' => $this->company_id
        ), 'C', $this->_lang_code);

    }

    protected static function render($text, $replacements)
    {
        $parsed_replacements = array();
        foreach (array_change_key_case($replacements, CASE_UPPER) as $key => $val) {
            $parsed_replacements['%' . $key . '%'] = @(string) $val;
        }

        return strtr($text, $parsed_replacements);
    }
}
