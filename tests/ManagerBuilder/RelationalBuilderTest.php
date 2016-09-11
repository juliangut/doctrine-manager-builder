<?php
/**
 * doctrine-manager-builder (https://github.com/juliangut/doctrine-manager-builder)
 * Doctrine2 managers builder
 *
 * @license BSD-3-Clause
 * @author Julián Gutiérrez <juliangut@gmail.com>
 */

namespace Jgut\Doctrine\ManagerBuilder\Test;

use Doctrine\Common\Cache\Cache;
use Doctrine\Common\Cache\VoidCache;
use Doctrine\Common\Persistence\Mapping\Driver\StaticPHPDriver;
use Doctrine\DBAL\Logging\EchoSQLLogger;
use Doctrine\DBAL\Types\StringType;
use Doctrine\ORM\EntityManager;
use Jgut\Doctrine\ManagerBuilder\ManagerBuilder;
use Jgut\Doctrine\ManagerBuilder\RelationalBuilder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\HelperSet;

/**
 * Relational entity builder tests.
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
        $this->builder->setOption('cache_driver_namespace', '');

        self::assertInstanceOf(Cache::class, $this->builder->getQueryCacheDriver());

        /** @var Cache $cacheDriver */
        $cacheDriver = $this->getMockBuilder(VoidCache::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->builder->setQueryCacheDriver($cacheDriver);

        self::assertEquals($cacheDriver, $this->builder->getQueryCacheDriver());
    }

    public function testResultCache()
    {
        $this->builder->setOption('cache_driver_namespace', '');

        self::assertInstanceOf(Cache::class, $this->builder->getResultCacheDriver());

        /** @var Cache $cacheDriver */
        $cacheDriver = $this->getMockBuilder(VoidCache::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->builder->setResultCacheDriver($cacheDriver);

        self::assertEquals($cacheDriver, $this->builder->getResultCacheDriver());
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessageRegExp /^".+" file does not exist$/
     */
    public function testManagerNoAnnotationFile()
    {
        $this->builder->setOption('annotation_files', __DIR__ . '/fake_file.php');

        $this->builder->getManager(true, true);
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

        $this->builder->getManager(true, true);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessageRegExp /^Provided driver should be of the type MappingDriver, ".+" given$/
     */
    public function testManagerWrongMappingDriver()
    {
        $this->builder->setOption('metadata_mapping', [__DIR__]);

        $this->builder->getManager(true, true);
    }

    /**
     * @expectedException \UnexpectedValueException
     * @expectedExceptionMessageRegExp /^".+" is not a valid metadata mapping type$/
     */
    public function testManagerWrongMappingType()
    {
        $this->builder->setOption('metadata_mapping', [['type' => 'unknown', 'path' => __DIR__]]);

        $this->builder->getManager(true, true);
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

        $this->builder->getManager(true, true);
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

        $this->builder->getManager(true, true);
    }

    public function testManager()
    {
        $this->builder->setOption('annotation_files', __FILE__);
        $this->builder->setOption('annotation_namespaces', ['namespace' => __FILE__]);
        $this->builder->setOption('annotation_autoloaders', ['class_exists']);
        $this->builder->setOption('connection', ['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->builder->setOption(
            'metadata_mapping',
            [['type' => ManagerBuilder::METADATA_MAPPING_ANNOTATION, 'path' => __DIR__]]
        );
        $this->builder->setOption('sql_logger', new EchoSQLLogger);
        $this->builder->setOption('custom_string_functions', 'string');
        $this->builder->setOption('custom_numeric_functions', 'numeric');
        $this->builder->setOption('custom_datetime_functions', 'datetime');
        $this->builder->setOption('custom_types', ['fake_type' => StringType::class]);

        static::assertInstanceOf(EntityManager::class, $this->builder->getManager(true));
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
            function ($command) {
                static::assertInstanceOf(Command::class, $command);
            }
        );
    }

    public function testConsoleHelperSet()
    {
        $this->builder->setOption('connection', ['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->builder->setOption(
            'metadata_mapping',
            [['type' => ManagerBuilder::METADATA_MAPPING_ANNOTATION, 'path' => __DIR__]]
        );

        $helperSet = $this->builder->getConsoleHelperSet();

        static::assertInstanceOf(HelperSet::class, $helperSet);
        static::assertTrue($helperSet->has('connection'));
        static::assertTrue($helperSet->has('db'));
        static::assertTrue($helperSet->has('entityManager'));
        static::assertTrue($helperSet->has('em'));
    }
}
