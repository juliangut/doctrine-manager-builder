[![PHP version](https://img.shields.io/badge/PHP-%3E%3D5.6-8892BF.svg?style=flat-square)](http://php.net)
[![Latest Version](https://img.shields.io/packagist/vpre/juliangut/doctrine-manager-builder.svg?style=flat-square)](https://packagist.org/packages/juliangut/doctrine-manager-builder)
[![License](https://img.shields.io/github/license/juliangut/doctrine-manager-builder.svg?style=flat-square)](https://github.com//doctrine-manager-builder/blob/master/LICENSE)

[![Build Status](https://img.shields.io/travis/juliangut/doctrine-manager-builder.svg?style=flat-square)](https://travis-ci.org/juliangut/doctrine-manager-builder)
[![Style Check](https://styleci.io/repos/67947100/shield)](https://styleci.io/repos/67947100)
[![Code Quality](https://img.shields.io/scrutinizer/g/juliangut/doctrine-manager-builder.svg?style=flat-square)](https://scrutinizer-ci.com/g/juliangut/doctrine-manager-builder)
[![Code Coverage](https://img.shields.io/coveralls/juliangut/doctrine-manager-builder.svg?style=flat-square)](https://coveralls.io/github/juliangut/doctrine-manager-builder)

[![Total Downloads](https://img.shields.io/packagist/dt/juliangut/doctrine-manager-builder.svg?style=flat-square)](https://packagist.org/packages/juliangut/doctrine-manager-builder)
[![Monthly Downloads](https://img.shields.io/packagist/dm/juliangut/doctrine-manager-builder.svg?style=flat-square)](https://packagist.org/packages/juliangut/doctrine-manager-builder)

# Doctrine2 managers builder

Frees you from the tedious work of configuring Doctrine's managers, ORM Entity Manager, MongoDB Document Manager and CouchDB Document Manager.

## Installation

### Composer

```
composer require juliangut/doctrine-manager-builder
```

If using on PHP >= 7.0

```
composer require alcaeus/mongo-php-adapter --ignore-platform-reqs
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
    'metadata_mapping' => [
        [
            'type' => ManagerBuilder::METADATA_MAPPING_ANNOTATION, 
            'path' => 'path/to/entities',
        ],
    ],
]);
$entityManager = $rdbmsBuilder->getManager();
```

### MongoDB Document Manager

```php
use Jgut\Doctrine\ManagerBuilder\ManagerBuilder;
use Jgut\Doctrine\ManagerBuilder\MongoDBBuilder;

$mongoDBBuilder = new MongoDBBuilder([
    'connection' => [
        'server' => 'mongodb://localhost:27017',
        'options' => ['connect' => false],
    ],
    'metadata_mapping' => [
        [
            'driver' => new \Doctrine\ORM\Mapping\Driver\YamlDriver(
                'path/to/document/yaml/mapping/files',
                '.yml'
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

### CouchDB Document Manager

```php
use Jgut\Doctrine\ManagerBuilder\CouchDBBuilder;
use Jgut\Doctrine\ManagerBuilder\MongoDBBuilder;

$couchDBBuilder = new CouchDBBuilder([
    'connection' => [
        'host' => 'localhost',
        'dbname' => 'doctrine',
    ],
    'metadata_mapping' => [
        [
            'type' => ManagerBuilder::METADATA_MAPPING_XML,
            'path' => 'path/to/document/xml/mapping/files',
            'extension' => '.xml',
        ],
    ],
]);
$documentManager = $couchDBBuilder->getManager();
```

**Mind that Doctrine CouchDB ODM support is not as good/wide as in Doctrine ORM or Doctrine MongoDB ODM**

## Configuration

### Common

* `metadata_mapping` **REQUIRED** array of metadata mapping drivers or configurations to create them, _see note below_
* `annotation_files` array of Doctrine annotations files
* `annotation_namespaces` array of Doctrine annotations namespaces
* `annotation_autoloaders` array of Doctrine annotations auto-loader callable
* `proxies_path` path were Doctrine creates its proxy classes, defaults to /tmp
* `proxies_namespace` string for proxies namespace
* `proxies_auto_generation` integer indicating proxy auto generation behavior
* `metadata_cache_driver` \Doctrine\Common\Cache\CacheProvider metadata cache driver
* `metadata_cache_namespace` string for metadata cache namespace
* `event_manager` a configured `Doctrine\Common\EventManager`

### Relational ORM Entity Manager

* `connection` **REQUIRED** array of PDO configurations or a \Doctrine\DBAL\Connection. See [supported drivers](http://php.net/manual/en/pdo.drivers.php)
* `query_cache_driver` \Doctrine\Common\Cache\CacheProvider query cache driver, defaults to `cache_driver`
* `query_cache_namespace` string for query cache namespace
* `result_cache_driver` \Doctrine\Common\Cache\CacheProvider result cache driver, defaults to `cache_driver`
* `result_cache_namespace` string for result cache namespace
* `default_repository_class` \Doctrine\ORM\EntityRepository
* `naming_strategy` a `\Doctrine\ORM\Mapping\NamingStrategy`, defaults to `UnderscoreNamingStrategy`
* `quote_strategy` a `\Doctrine\ORM\Mapping\QuoteStrategy`, defaults to `DefaultQuoteStrategy`
* `sql_logger` a `\Doctrine\DBAL\Logging\SQLLogger`
* `custom_types` array of `'type_name' => '\Doctrine\DBAL\Types\Type'`
* `string_functions` array of custom `'function_name' => '\Doctrine\ORM\Query\AST\Functions\FunctionNode'` for string DQL functions
* `numeric_functions` array of custom `'function_name' => '\Doctrine\ORM\Query\AST\Functions\FunctionNode'` for numeric DQL functions
* `datetime_functions` array of custom `'function_name' => '\Doctrine\ORM\Query\AST\Functions\FunctionNode'` for datetime DQL functions

### MongoDB ODM Document Manager

* `connection` **REQUIRED** array of \MongoClient configurations (server and options) or a \Doctrine\MongoDB\Connection
* `hydrators_path` path where Doctrine creates its hydrator classes, defaults to /tmp
* `hydrators_namespace` string for hydrators namespace, defaults to 'DoctrineODMHydrator'
* `hydrators_auto_generation` integer indicating hydrators auto generation behavior
* `default_repository_class` \Doctrine\ORM\DocumentRepository
* `default_database` **REQUIRED** default database to be used in case none specified
* `logger_callable` valid callable

### CouchDB ODM Document Manager

* `connection` **REQUIRED** array of \Doctrine\CouchDB\CouchDBClient configurations or a \Doctrine\CouchDB\CouchDBClient
* `lucene_handler_name` Apache Lucene handler name

### Considerations

These are general considerations when configuring managers:

* `metadata_mapping` must be an array containing arrays of configurations to create MappingDriver objects:
    * `type` one of \Jgut\Doctrine\ManagerBuilder\ManagerBuilder constants: `METADATA_MAPPING_ANNOTATION`, `METADATA_MAPPING_XML`, `METADATA_MAPPING_YAML` or `METADATA_MAPPING_PHP` **REQUIRED if no driver**
    * `path` a string path or array of paths to where mapping files are **REQUIRED if no driver**
    * `extension` overrides default mapping file extension: '.dcm.xml' for XML files and '.dcm.yml' for YAML files
    * `namespace` the namespace under which the mapped classes are **REQUIRED only if more than ONE mapping driver is defined**
    * `driver` an already created \Doctrine\Common\Persistence\Mapping\Driver\MappingDriver object **REQUIRED if no type AND path**
* `metadata_cache_driver`, if not provided, is automatically generated in the following order based on availability: `ApcuCache`, `XcacheCache`, `MemcacheCache`, `RedisCache` and finally fallback to `ArrayCache` which is always available. Any other cache driver not provided will fallback to using a clone of metadata cache driver.
* `proxies_auto_generation`, `hydrators_auto_generation` and `persistent_collections_auto_generation` configuration values are Doctrine\Common\Proxy\AbstractProxyFactory constants, in all cases it defaults to `AUTOGENERATE_NEVER`.

Managers are being configured **ready for production**, this means proxies, hydrators and persisten collections won't be automatically generated and, in case no cache driver is provided, one will be auto-generated. It is recommended you always provide your cache provider, for development you should use `VoidCache`.

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
    'metadata_mapping' => [
        [
            'type' => ManagerBuilder::METADATA_MAPPING_ANNOTATION, 
            'path' => 'path/to/entities',
        ],
    ],
    // Register UUID as custom type
    'custom_types' => ['uuid' => UuidType::class],
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
use Gedmo\Timestampable\TimestampableListener;
use Jgut\Doctrine\ManagerBuilder\RelationalBuilder;

require __DIR__ . '/vendor/autoload.php';

$rdbmsBuilder = new RelationalBuilder([
    'connection' => [
        'driver' => 'pdo_sqlite',
        'memory' => true,
    ],
    'metadata_mapping' => [
        [
            'type' => ManagerBuilder::METADATA_MAPPING_ANNOTATION, 
            'path' => 'path/to/entities',
        ],
    ],
]);

// Add listeners to builder event manager
$eventManager = $rdbmsBuilder->getEventManager();
$eventManager->addEventSubscriber(new SluggableListener);
$eventManager->addEventSubscriber(new TimestampableListener);

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

require __DIR__ . '/vendor/autoload.php';

$settings = require 'managers-configurations.php';

$consoleBuilder = new ConsoleBuilder;
$consoleBuilder->addBuilder(new RelationalBuilder($settings['main'], 'main'));
$consoleBuilder->addBuilder(new RelationalBuilder($settings['secondary'], 'secondary'));

return $consoleBuilder->getApplication();
```

If you run `./vendor/bin/doctrine-manager list` you will find the commands prefixed with builder's name, so commands are run with different managers

```
Available commands:
...
 main
  main:dbal:import                          Import SQL file(s) directly to Database.
  main:dbal:run-sql                         Executes arbitrary SQL directly from the command line.
  main:orm:clear-cache:metadata             Clear all metadata cache of the various cache drivers.
  main:orm:clear-cache:query                Clear all query cache of the various cache drivers.
...
 secondary
  secondary:dbal:import                     Import SQL file(s) directly to Database.
  secondary:dbal:run-sql                    Executes arbitrary SQL directly from the command line.
  secondary:orm:clear-cache:metadata        Clear all metadata cache of the various cache drivers.
  secondary:orm:clear-cache:query           Clear all query cache of the various cache drivers.
...
```

_Mind that if you provide builders with no name, or the same name, commands will run with the last manager included_

## Contributing

Found a bug or have a feature request? [Please open a new issue](https://github.com/juliangut/doctrine-manager-builder/issues). Have a look at existing issues before.

See file [CONTRIBUTING.md](https://github.com/juliangut/doctrine-manager-builder/blob/master/CONTRIBUTING.md)

## License

See file [LICENSE](https://github.com/juliangut/doctrine-manager-builder/blob/master/LICENSE) included with the source code for a copy of the license terms.
