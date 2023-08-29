Paykeeper plugin for WebSpace Engine
====
_(Plugin)_

#### Install
Put in `plugin` folder and setup in `index.php` file:
```php
// paykeeper plugin
$plugins->register(\Plugin\Paykeeper\PaykeeperPlugin::class);
```

#### Usage
```twig
{% set link = pk_link(order) %}
{% if link %}
    <a href="{{ link }}">Оплатить заказ</a>
{% endif %}
```

#### License
Licensed under the MIT license. See [License File](LICENSE.md) for more information.
