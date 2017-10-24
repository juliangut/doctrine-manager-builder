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

use Doctrine\Common\EventSubscriber;
use Doctrine\MongoDB\Connection;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Repository\DefaultRepositoryFactory;
use Doctrine\ODM\MongoDB\Types\BooleanType;
use Doctrine\ODM\MongoDB\Types\StringType;
use Jgut\Doctrine\ManagerBuilder\ManagerBuilder;
use Jgut\Doctrine\ManagerBuilder\MongoDBBuilder;
use Symfony\Component\Console\Command\Command;

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
        $eventSubscriber = $this->getMockBuilder(EventSubscriber::class)
            ->disableOriginalConstructor()
            ->getMock();
        $connection = new Connection('localhost', [], null, $this->builder->getEventManager());

        $this->builder->setOption('connection', $connection);
        $this->builder->setOption(
            'metadata_mapping',
            [['type' => ManagerBuilder::METADATA_MAPPING_ANNOTATION, 'path' => __DIR__]]
        );
        $this->builder->setOption('repository_factory', new DefaultRepositoryFactory);
        $this->builder->setOption('default_database', 'ddbb');
        $this->builder->setOption('logger_callable', 'class_exists');
        $this->builder->setOption('event_subscribers', ['event' => $eventSubscriber]);
        $this->builder->setOption('custom_types', ['string' => StringType::class, 'fake_type' => BooleanType::class]);
        $this->builder->setOption('custom_filters', ['filter' => '\Doctrine\ODM\MongoDB\Query\Filter\BsonFilter']);

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
                static::assertEquals(1, preg_match('/^odm:test:/', $command->getName()));
            }
        );
    }
}
