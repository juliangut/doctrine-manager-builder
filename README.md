[![PHP version](https://img.shields.io/badge/PHP-%3E%3D8.0-8892BF.svg?style=flat-square)](http://php.net)
[![Latest Version](https://img.shields.io/packagist/vpre/juliangut/doctrine-manager-builder.svg?style=flat-square)](https://packagist.org/packages/juliangut/doctrine-manager-builder)
[![License](https://img.shields.io/github/license/juliangut/doctrine-manager-builder.svg?style=flat-square)](https://github.com//doctrine-manager-builder/blob/master/LICENSE)

[![Total Downloads](https://img.shields.io/packagist/dt/juliangut/doctrine-manager-builder.svg?style=flat-square)](https://packagist.org/packages/juliangut/doctrine-manager-builder)
[![Monthly Downloads](https://img.shields.io/packagist/dm/juliangut/doctrine-manager-builder.svg?style=flat-square)](https://packagist.org/packages/juliangut/doctrine-manager-builder)

# Doctrine managers builder

Frees you from the tedious work of configuring Doctrine's managers, ORM Entity Manager and MongoDB Document Manager.

## Installation

### Composer

```
composer require juliangut/doctrine-manager-builder
```

## Usage

### Relational Database Entity Manager

```php
use Jgut\Doctrine\ManagerBuilder\ManagerBuilder;
use Jgut\Doctrine\ManagerBuilder\RelationalBuilder;

$rdbmsBuilder = new RelationalBuilder([
    'connection' => [
        'driver' => 'pdo_sqlite',
        'memory' => true,
    ],
    'metadata_Mapping' => [
        [
            'type' => ManagerBuilder::METADATA_MAPPING_ATTRIBUTE, 
            'path' => 'path/to/entities',
        ],
    ],
]);
```

### MongoDB Document Manager

```php
use Doctrine\ORM\Mapping\Driver\XmlDriver;
use Jgut\Doctrine\ManagerBuilder\ManagerBuilder;
use Jgut\Doctrine\ManagerBuilder\MongoDBBuilder;

$mongoDBBuilder = new MongoDBBuilder([
    'client' => [
        'uri' => 'mongodb://localhost:27017',
        'driverOptions' => ['connect' => false],
    ],
    'metadataMapping' => [
        [
            'driver' => new XmlDriver(
                'path/to/document/yaml/mapping/files',
                '.xml'
            ),
        ],
        [
            'type' => ManagerBuilder::METADATA_MAPPING_PHP,
            'path' => 'path/to/document/php/mapping/files',
            'namespace' => 'Project\Document\Namespace',
        ],
    ],
]);
$documentManager = $mongoDBBuilder->getManager();
```

## Configuration

### Common

* `name`, name of the builder, required for `doctrine-manager` CLI tool
* `metadataMapping` **REQUIRED** array of metadata mapping drivers or configurations to create them, _see note below_
* `proxiesPath` path were Doctrine creates its proxy classes, defaults to /tmp
* `proxiesNamespace` string for proxies namespace
* `proxiesAutoGeneration` integer indicating proxy auto generation behavior
* `metadataCache` PSR6 cache instance
* `eventManager` a `Doctrine\Common\EventManager` instance
* `eventSubscribers` an array of custom `Doctrine\Common\EventSubscriber`

### Relational ORM Entity Manager

* `connection` **REQUIRED** array of PDO configurations or a `\Doctrine\DBAL\Connection`. See [supported drivers](http://php.net/manual/en/pdo.drivers.php)
* `queryCache` a PSR6 cache instance
* `resultCache` a PSR6 cache instance
* `hydrationCache` a PSR6 cache instance
* `repositoryFactory` a `\Doctrine\ORM\Repository\RepositoryFactory` instance
* `defaultRepositoryClass` a `\Doctrine\ORM\EntityRepository` class name
* `namingStrategy` a `\Doctrine\ORM\Mapping\NamingStrategy` instance, defaults to `UnderscoreNamingStrategy`
* `quoteStrategy` a `\Doctrine\ORM\Mapping\QuoteStrategy` instance, defaults to `DefaultQuoteStrategy`
* `secondLevelCacheConfiguration` a `\Doctrine\ORM\Cache\CacheConfiguration` instance
* `sqlLogger` a PSR3 logger instance
* `customTypes` array of `'type_name' => '\Doctrine\DBAL\Types\Type'`
* `customMappingTypes` array of `'type_name' => 'Doctrine type: a constant on \Doctrine\DBAL\Types\Type'`. Used in conjunction with customTypes
* `customFilters` array of custom `'filter_name' => '\Doctrine\ORM\Query\Filter\SQLFilter'`
* `stringFunctions` array of custom `'function_name' => '\Doctrine\ORM\Query\AST\Functions\FunctionNode'` for string DQL functions
* `numericFunctions` array of custom `'function_name' => '\Doctrine\ORM\Query\AST\Functions\FunctionNode'` for numeric DQL functions
* `datetimeDunctions` array of custom `'function_name' => '\Doctrine\ORM\Query\AST\Functions\FunctionNode'` for datetime DQL functions

### MongoDB ODM Document Manager

* `client` **REQUIRED** array of MongoClient configurations (uri and options) or a `\MongoDB\Client`
* `defaultDatabase` **REQUIRED** default database to be used in case none specified
* `hydrationPath` path where Doctrine creates its hydration classes, defaults to /tmp
* `hydrationAutoGeneration` integer indicating hydration auto generation behavior
* `persistentCollectiosPath` path where Doctrine creates its persistent collection classes, defaults to /tmp
* `persistentCollectionAutoGeneration` integer persistent collection auto generation behavior
* `repositoryFactory`  a `\Doctrine\ODM\MongoDB\Repository\RepositoryFactory` instance
* `defaultRepositoryClass` a `\Doctrine\ODM\MongoDB\Repository\DocumentRepository` class name
* `customTypes` array of `'type_name' => '\Doctrine\ODM\MongoDB\Types\Type'`
* `customFilters` array of custom `'filter_name' => '\Doctrine\ODM\MongoDB\Query\Filter\BsonFilter'`

### Considerations

* `metadataMapping` must be an array containing arrays of configurations to create MappingDriver objects:
    * `type` one of \Jgut\Doctrine\ManagerBuilder\ManagerBuilder constants: `METADATA_MAPPING_ATTRIBUTES`, `METADATA_MAPPING_XML`, `METADATA_MAPPING_PHP`, `METADATA_MAPPING_YAML` or `METADATA_MAPPING_ANNOTATION` **REQUIRED if no driver**
    * `path` a string path or array of paths to where mapping files are **REQUIRED if no driver**
    * `extension` overrides default mapping file extension: '.dcm.xml' for XML files and '.dcm.yml' for YAML files
    * `namespace` the namespace under which the mapped classes are **REQUIRED only if more than ONE mapping driver is defined**
    * `driver` an already created \Doctrine\Common\Persistence\Mapping\Driver\MappingDriver object **REQUIRED if no type AND path**
* `metadataCache`, `queryCache`, `resultCache` and `hydrationCache`, if not provided, each one is automatically generated as an in-memory cache instance.
* `proxiesAutoGeneration` configuration values are `\Doctrine\Common\Proxy\AbstractProxyFactory` constants, defaults to `AUTOGENERATE_NEVER` on RelationalBuilder and `AUTOGENERATE_FILE_NOT_EXISTS` on MongoDBBuilder.
* `hydrationAutoGeneration` and `persistentCollectionAutoGeneration` configuration values are `Doctrine\ODM\MongoDB\Configuration` constants, defaults to `AUTOGENERATE_NEVER`.

Managers are being configured **ready for production**, this means proxies, hydration and persisten collections won't be automatically generated and, in case no cache driver are provided, in-memory caches will be auto-generated. It is recommended to always provide your own caches. For development, you should use a dummy cache.

## Extending managers

Extending default managers with extra features is extremely easy. Lets see two examples with well-known libraries.

### Adding new types

Using [ramsey/uuid-doctrine](https://github.com/ramsey/uuid-doctrine)

```
composer require ramsey/uuid-doctrine
```

```php
use Jgut\Doctrine\ManagerBuilder\RelationalBuilder;
use Ramsey\Uuid\Doctrine\UuidType;

require __DIR__ . '/vendor/autoload.php';

$rdbmsBuilder = new RelationalBuilder([
    'connection' => [
        'driver' => 'pdo_sqlite',
        'memory' => true,
    ],
    'metadataMapping' => [
        [
            'type' => ManagerBuilder::METADATA_MAPPING_ATTRIBUTE, 
            'path' => 'path/to/entities',
        ],
    ],
    // Register UUID as custom type
    'customTypes' => ['uuid' => UuidType::class],
]);
$entityManager = $rdbmsBuilder->getManager();
```

### Adding new behaviour

Using [gedmo/doctrine-extensions](https://github.com/Atlantic18/DoctrineExtensions)

```
composer require gedmo/doctrine-extensions
```

```php
use Gedmo\DoctrineExtensions;
use Gedmo\Sluggable\SluggableListener;
use Gedmo\SoftDeleteable\Filter\SoftDeleteableFilter;
use Gedmo\SoftDeleteable\SoftDeleteableListener;
use Gedmo\Timestampable\TimestampableListener;
use Jgut\Doctrine\ManagerBuilder\RelationalBuilder;

require __DIR__ . '/vendor/autoload.php';

$rdbmsBuilder = new RelationalBuilder([
    'connection' => [
        'driver' => 'pdo_sqlite',
        'memory' => true,
    ],
    'metadataMapping' => [
        [
            'type' => ManagerBuilder::METADATA_MAPPING_ATTRIBUTE, 
            'path' => 'path/to/entities',
        ],
    ],
    // Register new doctrine behaviours
    'eventSubscribers' => [
        new SluggableListener,
        new TimestampableListener,
        new SoftDeleteableListener,
    ],
    // Register custom filters
    'customFilters' => [
        'soft-deleteable' => SoftDeleteableFilter::class,
    ],
]);

// Register mapping driver into DoctrineExtensions
DoctrineExtensions::registerAbstractMappingIntoDriverChainORM($rdbmsBuilder->getMetadataMappingDriver());

// Get entity manager as usual
$entityManager = $rdbmsBuilder->getManager();
```

## Console integration

Although Doctrine ORM comes with a great CLI tool this library is intended to be used without ORM, and thus a new tool has been created instead of forcing to require Doctrine ORM.

This new CLI tool (doctrine-manager) is more powerful in the sense that it runs the same commands for different databases (managers) of the same kind by providing \Jgut\Doctrine\ManagerBuilder\ConsoleBuilder with named builders.

The configuration of `doctrine-manager` tool resembles the one ORM comes with and so you must have a 'cli-config.php' or ' config/cli-config.php' file

The only difference is that here you must return an instance of Symfony\Component\Console\Application instead of a Symfony\Component\Console\Helper\HelperSet

```php
use Jgut\Doctrine\ManagerBuilder\ConsoleBuilder;
use Jgut\Doctrine\ManagerBuilder\RelationalBuilder;
use Jgut\Doctrine\ManagerBuilder\MongoDBBuilder;

require __DIR__ . '/vendor/autoload.php';

$settings = require 'managers-configurations.php';

$consoleBuilder = new ConsoleBuilder;
$consoleBuilder->addBuilder(new RelationalBuilder($settings['mysql1']));
$consoleBuilder->addBuilder(new RelationalBuilder($settings['mysql2']));
$consoleBuilder->addBuilder(new MongoDBBuilder($settings['mongo1']));
$consoleBuilder->addBuilder(new MongoDBBuilder($settings['mongo2']));

return $consoleBuilder->getApplication();
```

If you run `./vendor/bin/doctrine-manager list` you will find the commands prefixed with builder's name, so commands are run with different managers

```
Available commands:
...
 dbal-mysql1
  dbal-mysql1:reserved-words        Checks if the current database contains identifiers that are reserved.
  dbal-mysql1:run-sql               Executes arbitrary SQL directly from the command line.
  ...
 dbal-mysql2
  dbal-mysql2:reserved-words        Checks if the current database contains identifiers that are reserved.
  dbal-mysql2:run-sql               Executes arbitrary SQL directly from the command line.
  ...
 odm-mongo1
  odm-mongo1:query                  Query mongodb and inspect the outputted results from your document classes.
  odm-mongo1:generate:hydrators     Generates hydrator classes for document classes.
  ...
 odm-mongo2
  odm-mongo2:query                  Query mongodb and inspect the outputted results from your document classes.
  odm-mongo2:generate:hydrators     Generates hydrator classes for document classes.
  ...
 orm-mysql1
  orm-mysql1:clear-cache:metadata   Clear all metadata cache of the various cache drivers.
  orm-mysql1:info                   Show basic information about all mapped entities.
  ...
 orm-mysql2
  orm-mysql2:clear-cache:metadata   Clear all metadata cache of the various cache drivers.
  orm-mysql2:info                   Show basic information about all mapped entities.
  ...
```

_doctrine-manager only allows named manager builders_

## Migrating from 1.x

* Minimum PHP version is now 8.0
* Minimum doctrine/common dependency is now 3.0
* Minimum doctrine/orm is now 2.13
* Minimum doctrine/mongodb-odm is now 2.3
* Configuration names have changed to camelCase
* Caches must be instances of PSR6 instead of doctrine/cache
* Auto generated caches are now always in-memory instances
* MongoDBBuilder's `connection` configuration is now `client`
* Annotation mapping is deprecated, migrate to Attribute mapping
* YAML mapping for MongoDBBuilder has been removed

## Contributing

Found a bug or have a feature request? [Please open a new issue](https://github.com/juliangut/doctrine-manager-builder/issues). Have a look at existing issues before.

See file [CONTRIBUTING.md](https://github.com/juliangut/doctrine-manager-builder/blob/master/CONTRIBUTING.md)

## License

See file [LICENSE](https://github.com/juliangut/doctrine-manager-builder/blob/master/LICENSE) included with the source code for a copy of the license terms.
