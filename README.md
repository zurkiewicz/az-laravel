# Laravel
Extension for laravel.

## Install

Add repository and credentials

```bash
composer config --global repositories.az composer https://repo.sabau360.net/repository/sabau360/
composer config --global http-basic.repo.sabau360.net user *********
```
Run composer
```bash
composer require az/laravel:^1.0
```

### Add commands:
In ./app/app/Console/Kernel.php add to command list:

```php
protected $commands = [
    \AZ\Laravel\Console\MigrateDB::class,
];
```
