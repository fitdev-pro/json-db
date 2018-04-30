# FitJsonDb

PHP 7.0 Simply development data storage.

## Installation

```
composer require fitdev-pro/json-db
```

## Usage

Base usage
```php
<?php
use FitdevPro\JsonDb\Database;

$db = new Database($file_system);
$table = $db->getTable('Person');

$person = $table->findFirst(['email'=>'test@test.com']);
```

## Contribute

Please feel free to fork and extend existing or add new plugins and send a pull request with your changes!
To establish a consistent code quality, please provide unit tests for all your changes and may adapt the documentation.

## License

The MIT License (MIT). Please see [License File](https://github.com/fitdev-pro/json-db/blob/master/LISENCE) for more information.
