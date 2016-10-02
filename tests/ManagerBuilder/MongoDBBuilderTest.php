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

use Doctrine\MongoDB\Connection;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Repository\DefaultRepositoryFactory;
use Jgut\Doctrine\ManagerBuilder\ManagerBuilder;
use Jgut\Doctrine\ManagerBuilder\MongoDBBuilder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\HelperSet;

/**
 * MongoDB entity builder tests.
 *
 * @group mongodb
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
        $this->builder->setOption('connection', null);
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
     * @expectedExceptionMessageRegExp /^Invalid factory class ".+"\. It must be a Doctrine\\ODM\\MongoDB\\Repository\\RepositoryFactory\.$/
     */
    public function testBadRepositoryFactory()
    {
        $this->builder->setOption('connection', new Connection('localhost'));
        $this->builder->setOption(
            'metadata_mapping',
            [['type' => ManagerBuilder::METADATA_MAPPING_ANNOTATION, 'path' => __DIR__]]
        );
        $this->builder->setOption('repository_factory', new \stdClass);

        $this->builder->getManager();
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
        $this->builder->setOption('repository_factory', new DefaultRepositoryFactory);
        $this->builder->setOption('default_database', 'ddbb');
        $this->builder->setOption('logger_callable', 'class_exists');

        static::assertInstanceOf(DocumentManager::class, $this->builder->getManager());
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
            function (Command $command) {
                static::assertEquals(1, preg_match('/^test:odm:/', $command->getName()));
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
