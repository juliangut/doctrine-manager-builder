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

use Doctrine\ODM\CouchDB\DocumentManager;
use Jgut\Doctrine\ManagerBuilder\CouchDB\Repository\DefaultRepositoryFactory;
use Jgut\Doctrine\ManagerBuilder\CouchDBBuilder;
use Jgut\Doctrine\ManagerBuilder\ManagerBuilder;
use Symfony\Component\Console\Command\Command;

/**
 * CouchDB entity builder tests.
 *
 * @group couchdb
 */
class CouchDBBuilderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var CouchDBBuilder
     */
    protected $builder;

    /**
     * {@inheritdoc}
     */
    public function setUp()
    {
        $this->builder = new CouchDBBuilder([], 'test');
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

        $this->builder->getManager(true);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessageRegExp  /^Expecting array of instance of CouchDBClient as first argument/
     */
    public function testManageWrongConnection()
    {
        $this->builder->setOption('connection', 'connection');
        $this->builder->setOption(
            'metadata_mapping',
            [['type' => ManagerBuilder::METADATA_MAPPING_ANNOTATION, 'path' => __DIR__]]
        );

        $this->builder->getManager();
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testBadRepositoryFactory()
    {
        $this->builder->setOption('connection', ['dbname' => 'ddbb']);
        $this->builder->setOption(
            'metadata_mapping',
            [['type' => ManagerBuilder::METADATA_MAPPING_ANNOTATION, 'path' => __DIR__]]
        );
        $this->builder->setOption('repository_factory', new \stdClass);

        $this->builder->getManager();
    }

    public function testManager()
    {
        $this->builder->setOption('connection', ['dbname' => 'ddbb']);
        $this->builder->setOption(
            'metadata_mapping',
            [['type' => ManagerBuilder::METADATA_MAPPING_ANNOTATION, 'path' => __DIR__]]
        );
        $this->builder->setOption('repository_factory', new DefaultRepositoryFactory);
        $this->builder->setOption('lucene_handler_name', 'lucene');

        static::assertInstanceOf(DocumentManager::class, $this->builder->getManager());
    }

    public function testConsoleCommands()
    {
        $this->builder->setOption('connection', ['dbname' => 'ddbb']);
        $this->builder->setOption(
            'metadata_mapping',
            [['type' => ManagerBuilder::METADATA_MAPPING_ANNOTATION, 'path' => __DIR__]]
        );

        $commands = $this->builder->getConsoleCommands();

        array_walk(
            $commands,
            function (Command $command) {
                static::assertEquals(1, preg_match('/^couchdb(_odm)?:test:/', $command->getName()));
            }
        );
    }
}
