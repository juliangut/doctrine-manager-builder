<?php

/*
 * doctrine-manager-builder (https://github.com/juliangut/doctrine-manager-builder).
 * Doctrine2 managers builder.
 *
 * @license BSD-3-Clause
 * @link https://github.com/juliangut/doctrine-manager-builder
 * @author Julián Gutiérrez <juliangut@gmail.com>
 */

namespace Jgut\Doctrine\ManagerBuilder\Tests;

use Doctrine\Common\Cache\ArrayCache;
use Doctrine\Common\Cache\CacheProvider;
use Doctrine\Common\EventSubscriber;
use Doctrine\Common\Persistence\Mapping\Driver\StaticPHPDriver;
use Doctrine\DBAL\Logging\EchoSQLLogger;
use Doctrine\DBAL\Types\BooleanType;
use Doctrine\DBAL\Types\StringType;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Cache\CacheConfiguration;
use Doctrine\ORM\Cache\CacheFactory;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Repository\DefaultRepositoryFactory;
use Jgut\Doctrine\ManagerBuilder\ManagerBuilder;
use Jgut\Doctrine\ManagerBuilder\RelationalBuilder;
use Symfony\Component\Console\Command\Command;

/**
 * Relational entity builder tests.
 *
 * @group relational
 */
class RelationalBuilderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var RelationalBuilder
     */
    protected $builder;

    /**
     * {@inheritdoc}
     */
    public function setUp()
    {
        $this->builder = new RelationalBuilder([], 'test');
    }

    public function testQueryCache()
    {
        $cacheDriver = $this->getMockBuilder(CacheProvider::class)
            ->disableOriginalConstructor()
            ->setMethodsExcept(['getNamespace', 'setNamespace'])
            ->getMock();

        $this->builder->setOption('query_cache_driver', $cacheDriver);
        $this->builder->setOption('query_cache_namespace', 'namespace');

        /* @var CacheProvider $driver */
        $driver = $this->builder->getQueryCacheDriver();
        self::assertEquals($cacheDriver, $driver);
        self::assertEquals('namespace', $driver->getNamespace());

        /* @var CacheProvider $cacheDriver */
        $cacheDriver = $this->getMockBuilder(ArrayCache::class)
            ->disableOriginalConstructor()
            ->setMethodsExcept(['getNamespace'])
            ->getMock();
        $this->builder->setQueryCacheDriver($cacheDriver);

        self::assertEquals($cacheDriver, $this->builder->getQueryCacheDriver());
    }

    public function testResultCache()
    {
        $cacheDriver = $this->getMockBuilder(CacheProvider::class)
            ->disableOriginalConstructor()
            ->setMethodsExcept(['getNamespace', 'setNamespace'])
            ->getMock();

        $this->builder->setOption('result_cache_driver', $cacheDriver);
        $this->builder->setOption('result_cache_namespace', '');

        self::assertInstanceOf(CacheProvider::class, $this->builder->getResultCacheDriver());

        /* @var CacheProvider $cacheDriver */
        $cacheDriver = $this->getMockBuilder(CacheProvider::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->builder->setResultCacheDriver($cacheDriver);

        self::assertEquals($cacheDriver, $this->builder->getResultCacheDriver());
    }

    public function testHydratorCache()
    {
        $cacheDriver = $this->getMockBuilder(CacheProvider::class)
            ->disableOriginalConstructor()
            ->setMethodsExcept(['getNamespace', 'setNamespace'])
            ->getMock();

        $this->builder->setOption('hydrator_cache_driver', $cacheDriver);
        $this->builder->setOption('hydrator_cache_namespace', '');

        self::assertInstanceOf(CacheProvider::class, $this->builder->getHydratorCacheDriver());

        /* @var CacheProvider $cacheDriver */
        $cacheDriver = $this->getMockBuilder(CacheProvider::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->builder->setHydratorCacheDriver($cacheDriver);

        self::assertEquals($cacheDriver, $this->builder->getHydratorCacheDriver());
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessageRegExp /^".+" file does not exist$/
     */
    public function testManagerNoAnnotationFile()
    {
        $this->builder->setOption('annotation_files', __DIR__ . '/fake_file.php');

        $this->builder->getManager(true);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage No metadata mapping defined
     */
    public function testManagerNoMetadataMapping()
    {
        $this->builder->getManager();
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage metadata_mapping must be array with "driver" key or "type" and "path" keys
     */
    public function testManagerNoMappingDriver()
    {
        $this->builder->setOption('metadata_mapping', [[]]);

        $this->builder->getManager(true);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessageRegExp /^Provided driver should be of the type MappingDriver, ".+" given$/
     */
    public function testManagerWrongMappingDriver()
    {
        $this->builder->setOption('metadata_mapping', [__DIR__]);

        $this->builder->getManager(true);
    }

    /**
     * @expectedException \UnexpectedValueException
     * @expectedExceptionMessageRegExp /^".+" is not a valid metadata mapping type$/
     */
    public function testManagerWrongMappingType()
    {
        $this->builder->setOption('metadata_mapping', [['type' => 'unknown', 'path' => __DIR__]]);

        $this->builder->getManager(true);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Only one default metadata mapping driver allowed, a namespace must be defined
     */
    public function testManagerSingleDefaultMapping()
    {
        $this->builder->setOption(
            'metadata_mapping',
            [
                ['driver' => new StaticPHPDriver([__DIR__])],
                ['type' => ManagerBuilder::METADATA_MAPPING_XML, 'path' => __DIR__, 'namespace' => 'namespace'],
                ['type' => ManagerBuilder::METADATA_MAPPING_YAML, 'path' => __DIR__],
            ]
        );

        $this->builder->getManager(true);
    }

    /**
     * @expectedException \Doctrine\DBAL\DBALException
     * @expectedExceptionMessageRegExp /^The options 'driver' or 'driverClass' are mandatory if no PDO instance/
     */
    public function testManagerNoConnection()
    {
        $this->builder->setOption(
            'metadata_mapping',
            [
                ['type' => ManagerBuilder::METADATA_MAPPING_ANNOTATION, 'path' => __DIR__, 'namespace' => 'annotation'],
                ['type' => ManagerBuilder::METADATA_MAPPING_XML, 'path' => __DIR__, 'namespace' => 'xml'],
                ['type' => ManagerBuilder::METADATA_MAPPING_YAML, 'path' => __DIR__, 'namespace' => 'yaml'],
                ['type' => ManagerBuilder::METADATA_MAPPING_PHP, 'path' => __DIR__, 'namespace' => 'php'],
            ]
        );

        $this->builder->getManager(true);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testBadRepositoryFactory()
    {
        $this->builder->setOption('annotation_files', __FILE__);
        $this->builder->setOption('annotation_namespaces', ['namespace' => __FILE__]);
        $this->builder->setOption('annotation_autoloaders', ['class_exists']);
        $this->builder->setOption('connection', ['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->builder->setOption(
            'metadata_mapping',
            [['type' => ManagerBuilder::METADATA_MAPPING_ANNOTATION, 'path' => __DIR__]]
        );
        $this->builder->setOption('repository_factory', new \stdClass);

        $this->builder->getManager();
    }

    public function testManager()
    {
        $eventSubscriber = $this->getMockBuilder(EventSubscriber::class)
            ->disableOriginalConstructor()
            ->getMock();

        /* @var CacheFactory $cacheFactory */
        $cacheFactory = $this->getMockBuilder(CacheFactory::class)
        ->disableOriginalConstructor()
        ->getMock();

        $cacheConfiguration = $this->getMockBuilder(CacheConfiguration::class)
            ->disableOriginalConstructor()
            ->getMock();

        $cacheConfiguration->expects(self::once())
            ->method('getCacheFactory')
            ->will(self::returnValue($cacheFactory));
        /* CacheConfiguration $cache */

        $this->builder->setOption('annotation_files', __FILE__);
        $this->builder->setOption('annotation_namespaces', ['namespace' => __FILE__]);
        $this->builder->setOption('annotation_autoloaders', ['class_exists']);
        $this->builder->setOption('connection', ['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->builder->setOption(
            'metadata_mapping',
            [['type' => ManagerBuilder::METADATA_MAPPING_ANNOTATION, 'path' => __DIR__]]
        );
        $this->builder->setOption('repository_factory', new DefaultRepositoryFactory);
        $this->builder->setOption('second_level_cache_configuration', $cacheConfiguration);
        $this->builder->setOption('sql_logger', new EchoSQLLogger);
        $this->builder->setOption('custom_string_functions', 'string');
        $this->builder->setOption('custom_numeric_functions', 'numeric');
        $this->builder->setOption('custom_datetime_functions', 'datetime');
        $this->builder->setOption('custom_types', ['string' => StringType::class, 'fake_type' => BooleanType::class]);
        $this->builder->setOption('custom_mapping_types', ['string' => Type::STRING, 'fake_type' => Type::BOOLEAN]);
        $this->builder->setOption('event_subscribers', ['event' => $eventSubscriber]);
        $this->builder->setOption('custom_filters', ['filter' => '\Doctrine\ORM\Query\Filter\SQLFilter']);

        static::assertInstanceOf(EntityManager::class, $this->builder->getManager());
    }

    public function testConsoleCommands()
    {
        $this->builder->setOption('connection', ['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->builder->setOption(
            'metadata_mapping',
            [['type' => ManagerBuilder::METADATA_MAPPING_ANNOTATION, 'path' => __DIR__]]
        );

        $commands = $this->builder->getConsoleCommands();

        return array_walk(
            $commands,
            function (Command $command) {
                static::assertEquals(1, preg_match('/^(dbal|orm):test:/', $command->getName()));
            }
        );
    }
}
