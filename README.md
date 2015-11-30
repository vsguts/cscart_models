## Объявление

Для минимального объявления достаточно:

```php
class License extends AModel
{
    public function getTableName()
    {
        return '?:licenses';
    }

    public function getPrimaryField()
    {
        return 'license_id';
    }
}
```

И уже можно делать выборки элементов, а так же их сохранять. Выборки в этом случае будут работать только по айдишникам. Ну и конечно всегда можно взять все элементы через findAll().

Для фильтрации нужно объявить метод getSearchFields:
```php

    public function getSearchFields()
    {
        return array(
            'number' => array(
                'product_id' => '?:licenses.product_id',
                'stores_allowed',
            ),
            'range' => array(
                'stores_allowed',
            ),
            'string' => array(
                'status' => '?:licenses.status',
            ),
            'time' => array(
                'issue_date' => '?:licenses.timestamp',
            ),
            'not_in' => array(
                'exclude_ids'   => '?:notifications.notification_id',
            ),
        );
    }
```
Массив имеет формат:
тип => параметр в запросе => поле в БД
Если параметр в запросе не задан (массив без ключа), то значение используется и как параметр в запросе и как поле в БД.
Типы есть следующие: range, in, not_in, string, text, time.
Всегда доступны дополнительные параметры для фильтрации по айдишникам: ids, not_ids, в которые можно передавать и массив и просто значение.

Для сортировок нужно объявить такой массив:
```php
    public function getSortFields()
    {
        return array(
            'time' => 'timestamp',
            'id' => 'license_id',
            'license_number' => 'license_number',
            'status' => 'status',
            'store_name' => 'store_name',
            'product' => 'product',
            'user' => array(
                'lastname',
                'firstname',
            ),
        );
    }
```
По дефолту сортируется по первому варианту из массива возвращаемого методом по возрастанию. Если по дефолту хотим сортировать по убыванию, то добавляем такой метод:
```php
    public function getSortDefaultDirection()
    {
        return 'desc';
    }
```

Если в выборке необходимо использовать дополнительные условия, то для этого есть метод getExtraCondition(). Пример:
```php
    public function getExtraCondition($params)
    {
        $condition = array();
        $table_name = $this->getTableName();

        if ($this->_area == 'C') {
            $condition[] = db_quote("$table_name.user_id = ?i", $this->_auth['user_id']);
        } elseif ($company_id = Registry::get('runtime.company_id')) {
            $condition[] = db_quote("$table_name.company_id = ?i", $company_id);
        }

        return $condition;
    }
```

Если мы используем таблицу без автоинкрементного поля, то нужно в модели переопределить метод _primaryAutoIncrement, таким образом, чтобы он возвращал false:
```php
    protected function _primaryAutoIncrement()
    {
        return false;
    }
```

Можно подключить таблицу с описаниями таким образом:
```php

    public function getDescriptionTableName()
    {
        return '?:notification_descriptions';
    }
```
В этом случае эта таблица будет автоматом джойнится и из нее можно будет сразу выбирать поля. При сохранении данные в нее так же будут сохраняться.

Для выборок можно использовать джоины дополнительных таблиц:
```php
    public function getJoins($params)
    {
        return array(
            db_quote("LEFT JOIN ?:users ON ?:users.user_id = ?:licenses.user_id"),
            db_quote("LEFT JOIN ?:products ON ?:products.product_id = ?:licenses.product_id"),
            db_quote(
                "LEFT JOIN ?:product_descriptions"
                . " ON ?:product_descriptions.product_id = ?:licenses.product_id"
                . " AND ?:product_descriptions.lang_code = ?s"
                , $params['lang_code']
            ),
        );
    }

```

Если нам нужно после выборки подгрузить дополнительные данные, то это можно сделать так:
```php
    protected function _gatherAdditionalItemsData(&$items, $params)
    {
        foreach ($items as &$item) {
            $item['name_extended'] = sprintf("%s (%s, %s)",
                $item['license_number'],
                $item['store_name'],
                __('num_stores_allowed', array('[num]' => $item['stores_allowed']))
            );
        }
    }
```

## Использование

Получить все элементы:
```php
$licenses = License::model()->findAll();
```

Получить элемент по ID:
```php
$license = License::model()->find($license_id);
```

Получить список элементов по параметрам:
```php
$licenses = License::model()->findMany($params);
```

Удалить элемент:
```php
License::model()->find($license_id)->delete();
```

Удалить элементы:
```php
License::model()->deleteMany(array('user_id' => $user_id));
```

Получить атрибут элемента:
```php
$l_number = License::model()->find($license_id)->license
```

Создать новый элемент и сохранить:
```php
$license = new License;
$license->product_id = 5;
$license->save();
```

или так:
```php
$license = new License;
$license->attributes(array(...));
$license->save();
```

Изменить существующий элемент:
```php
$license = License::model()->find($license_id);
$license->status = $new_status;
$license->save();
```

Обращение к полям:
```php
$license = License::model()->find($license_id);

// Magic methods
$license->license_id;
$license->license_number;

// ArrayAccess interface
$license['license_id'];
$license['license_number'];

// IteratorAggregate interface
foreach ($license as $field_name => $field_value) { ... }
```

Получить количество элементов:
```php
$count = License::model()->findMany([
    'get_count' => true,
    'product_id' => 5, // or any other condition
]);
```

Получить только айдишники элементов:
```php
$count = License::model()->findMany([
    'get_ids' => true,
    'product_id' => 5, // or any other condition
]);
```

Получить данные в виде массива:
```php
$count = License::model()->findMany([
    'to_array' => true,
    'product_id' => 5, // or any other condition
]);
```

#### Использование в контроллере

Сохранение данных в POST-запросе:
```php
if (!empty($_REQUEST['notification_id'])) {
    $notification = Notification::model()->find($_REQUEST['notification_id']);
} else {
    $notification = new Notification;
}

$notification->attributes($_REQUEST['notification']);

if (!$notification->save()) {
    fn_set_notification('E', __('error'), __('error_occurred'));
}
```

Отрисовка грида карты:
```php
$params = array_merge(
    array(
        'items_per_page' => Registry::get('settings.Appearance.admin_elements_per_page')
    ),
    $_REQUEST,
    array(
        'return_params' => true,// Нужно для того, чтобы вернуть параметры
    )
);

list($notifications, $search) = Notification::model()->findMany($params);

Registry::get('view')->assign('notifications', $notifications);
Registry::get('view')->assign('search', $search);
```

#### Использование в шаблоне

```smarty
{foreach from=$log_records item=r}
    <tr>        
        <td>{$r.timestamp}</td>
        <td>{$r->getLogAction() nofilter}</td>
    </tr>
{/foreach}
```
(Дополнительная функциональность в методе getLogAction())

## Обработчики

#### Чтение:
- beforeFind();
- afterFind();

#### Сохранение:
- beforeSave();
- afterSave();

#### Удаление:
- beforeDelete();
- afterDelete();

