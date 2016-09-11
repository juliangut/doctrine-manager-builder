<?php
/**
 * doctrine-manager-builder (https://github.com/juliangut/doctrine-manager-builder)
 * Doctrine2 managers builder
 *
 * @license BSD-3-Clause
 * @author Julián Gutiérrez <juliangut@gmail.com>
 */

namespace Jgut\Doctrine\ManagerBuilder\Test;

use Doctrine\MongoDB\Connection;
use Doctrine\ODM\MongoDB\DocumentManager;
use Jgut\Doctrine\ManagerBuilder\ManagerBuilder;
use Jgut\Doctrine\ManagerBuilder\MongoDBBuilder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\HelperSet;

/**
 * MongoDB entity builder tests.
 */
class MongoDBBuilderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var MongoDBBuilder
     */
    protected $builder;

    /**
     * {@inheritdoc}
     */
    public function setUp()
    {
        $this->builder = new MongoDBBuilder([], 'test');
    }

    /**
     * @expectedException \InvalidArgumentException
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

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Cannot use different EventManager instances for DocumentManager and Connection.
     */
    public function testManageWrongConnection()
    {
        $this->builder->setOption('connection', new Connection('localhost'));
        $this->builder->setOption(
            'metadata_mapping',
            [['type' => ManagerBuilder::METADATA_MAPPING_ANNOTATION, 'path' => __DIR__]]
        );

        $this->builder->getManager();
    }

    public function testManager()
    {
        $connection = new Connection('localhost', [], null, $this->builder->getEventManager());

        $this->builder->setOption('connection', $connection);
        $this->builder->setOption(
            'metadata_mapping',
            [['type' => ManagerBuilder::METADATA_MAPPING_ANNOTATION, 'path' => __DIR__]]
        );
        $this->builder->setOption('default_database', 'ddbb');
        $this->builder->setOption('logger_callable', 'class_exists');

        static::assertInstanceOf(DocumentManager::class, $this->builder->getManager(true));
    }

    public function testConsoleCommands()
    {
        $this->builder->setOption('connection', ['server' => 'localhost']);
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
        $this->builder->setOption('connection', ['server' => 'localhost']);
        $this->builder->setOption(
            'metadata_mapping',
            [['type' => ManagerBuilder::METADATA_MAPPING_ANNOTATION, 'path' => __DIR__]]
        );

        $helperSet = $this->builder->getConsoleHelperSet();

        static::assertInstanceOf(HelperSet::class, $helperSet);
        static::assertTrue($helperSet->has('documentManager'));
        static::assertTrue($helperSet->has('dm'));
    }
}