[![phpunit](https://github.com/danilovl/entity-data-list-console-bundle/actions/workflows/phpunit.yml/badge.svg)](https://github.com/danilovl/entity-data-list-console-bundle/actions/workflows/phpunit.yml)
[![downloads](https://img.shields.io/packagist/dt/danilovl/entity-data-list-console-bundle)](https://packagist.org/packages/danilovl/entity-data-list-console-bundle)
[![latest Stable Version](https://img.shields.io/packagist/v/danilovl/entity-data-list-console-bundle)](https://packagist.org/packages/danilovl/entity-data-list-console-bundle)
[![license](https://img.shields.io/packagist/l/danilovl/entity-data-list-console-bundle)](https://packagist.org/packages/danilovl/entity-data-list-console-bundle)

# EntityDataListConsoleBundle #

## About ##

This is a Symfony bundle providing a console command designed to render database data for a specified doctrine entity in a tabular format.

The command is highly flexible, allowing developers to configure which fields to display, handle related associations, and apply pagination using options like --limit and --offset.

### Requirements

* PHP 8.3 or higher
* Symfony 7.0 or higher

### 1. Installation

Install `danilovl/entity-data-list-console-bundle` package by Composer:

``` bash
 composer require danilovl/entity-data-list-console-bundle
```

``` php
<?php
// config/bundles.php

return [
    // ...
    Danilovl\EntityDataListConsoleBundle\EntityDataListConsoleBundle::class => ['all' => true]
];
```

### 2. Command overview

The abstract class `EntityDataListCommand` provides a base for creating commands that retrieve and display data for doctrine entities.

The `OrmEntityDataListCommand` is a concrete implementation registered as a Symfony console command under the name:

```bash
 php bin/console danilovl:entity-data-list:orm
```

### 3. Features

- Universal data rendering:<br/>
  Works with any doctrine entity by providing its class name.<br/>
  Retrieves data directly from the database.


- Field customization:<br/>
  Allows you to configure which fields of the entity to display in the output.<br/>
  Supports customization of related entity fields (e.g., OneToMany, ManyToOne).


- Pagination support:<br/>
  Includes --limit and --offset options to control the number of records retrieved and the starting point.


- Formatted output:<br/>
  Displays data in a structured table format for easy reading.


- Date handling:<br/>
  Automatically formats datetime and date fields into human-readable strings.


### 4. Usage

To list the data of a specific entity:

```bash
 php bin/console danilovl:entity-data-list:orm App\\Entity\\User
```

#### 4.1 Options

--entity (required argument):

Specifies the fully qualified class name of the doctrine entity to query (e.g., App\\Entity\\User).

--limit (optional):

Limits the number of rows retrieved. Default: 10.

--offset (optional):

Skips the specified number of rows before starting the result set. Default: 0.

--associations-ignore (optional):

Specifies associations (e.g., OneToMany, ManyToOne) to ignore during rendering.<br/>
Provide a comma-separated list of association field names.<br/>
Default: value returned by getAssociationsIgnore().

--associations-limit (optional):

Limits the number of items rendered for associations (e.g., child entities in OneToMany relationships). <br/>
Default: value returned by getAssociationsLimit().

Example Commands

```bash
 php bin/console danilovl:entity-data-list:orm App\\Entity\\User --limit=100
 php bin/console danilovl:entity-data-list:orm App\\Entity\\User --limit=100 --offset=10
 
 php bin/console danilovl:entity-data-list:orm App\\Entity\\User --associations-ignore=1
 php bin/console danilovl:entity-data-list:orm App\\Entity\\User --associations-limit=3
 
 php bin/console danilovl:entity-data-list:orm App\\Entity\\User --limit=100 --offset=10 --associations-ignore=0 --associations-limit=5
```

### 5. Extending the command

The EntityDataListCommand provides a flexible foundation for building custom commands tailored to specific doctrine entities. <br/>
By extending this base command, you can customize the behavior to suit your application's needs.

Here are the steps to create and extend your own entity data listing command:

```php
namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand('app:user-data-list')]
class UserDataListCommand extends EntityDataListCommand
{
    protected function getEntityClass(): ?string
    {
        return User::class;
    }

    protected function getLimit(): int
    {
        return 20;
    }

    protected function getAssociationsIgnore(): int
    {
        return 1; 
    }

    protected function getFields(ClassMetadata $metadata): array
    {
        return ['id', 'username', 'email', 'createdAt'];
    }

    protected function processRow(object $entity, array $fields, ClassMetadata $metadata): array
    {
        $row = parent::processRow($entity, $fields, $metadata);

        if (isset($row['createdAt'])) {
            $row['createdAt'] = $row['createdAt']->format('Y-m-d');
        }

        return $row;
    }
    
    // or customize another logic
}
```

Run your custom command:

```bash 
 php bin/console app:user-data-list
```

### 5. Gedmo

If your project utilizes the `Gedmo\Translatable` extension to manage translations for entities, you can leverage the `OrmTranslatableEntityDataListCommand` class to handle translatable entities in console commands.

This command ensures that the correct translation locale is set before querying data, allowing you to easily render localized data from the database.

#### 5.1 Important note

The default locale is set to en (English).<br/>
If your project uses a different default locale (e.g., en, fr), you need to override the `getLocale()` method in your custom command to set the appropriate default.

```php
protected function getLocale(): string
{
    return 'en_US'; // Gedmo default locale is en_US
}
````

#### 5.2 How to use

Create your custom command class and extend `OrmTranslatableEntityDataListCommand`.

```php
<?php declare(strict_types=1);

namespace App\Command;

use App\Domain\Product\Entity\Product;
use Danilovl\EntityDataListConsoleBundle\Command\OrmTranslatableEntityDataListCommand;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand('danilovl:entity-data-list:product', 'Render the database data of a product entity.')]
class ProductListCommand extends OrmTranslatableEntityDataListCommand
{
    protected function getEntityClass(): ?string
    {
        return Product::class;
    }
}
```

Run your custom command:

```bash 
 php bin/console danilovl:entity-data-list:product --locale=ru
```

## License

The EntityDataListConsoleBundle is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
