<?php

/*
 * doctrine-manager-builder (https://github.com/juliangut/doctrine-manager-builder).
 * Doctrine2 managers builder.
 *
 * @license BSD-3-Clause
 * @link https://github.com/juliangut/doctrine-manager-builder
 * @author Julián Gutiérrez <juliangut@gmail.com>
 */

namespace Jgut\Doctrine\ManagerBuilder\Tests\CouchDB;

use Doctrine\CouchDB\CouchDBClient;
use Jgut\Doctrine\ManagerBuilder\CouchDB\DocumentManager;
use Jgut\Doctrine\ManagerBuilder\CouchDB\Repository\DefaultRepositoryFactory;

/**
 * CouchDB custom document manager tests.
 *
 * @group couchdb
 */
class DocumentManagerTest extends \PHPUnit_Framework_TestCase
{
    public function testRepositoryClassName()
    {
        $client = $this->getMockBuilder(CouchDBClient::class)
            ->disableOriginalConstructor()
            ->getMock();
        /* @var CouchDBClient $client */

        $documentManager = DocumentManager::create($client);

        self::assertEquals('Doctrine\ODM\CouchDB\DocumentRepository', $documentManager->getDefaultRepositoryClassName());

        $documentManager->setDefaultRepositoryClassName('Doctrine\Common\Persistence\ObjectRepository');
        self::assertEquals('Doctrine\Common\Persistence\ObjectRepository', $documentManager->getDefaultRepositoryClassName());
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessageRegExp /^Invalid repository class ".+"\. It must be a Doctrine\\Common\\Persistence\\ObjectRepository\.$/
     */
    public function testBadRepositoryClassName()
    {
        $client = $this->getMockBuilder(CouchDBClient::class)
            ->disableOriginalConstructor()
            ->getMock();
        /* @var CouchDBClient $client */

        $documentManager = DocumentManager::create($client);

        $documentManager->setDefaultRepositoryClassName('Doctrine\ORM\EntityManager');
    }

    public function testRepositoryFactory()
    {
        $client = $this->getMockBuilder(CouchDBClient::class)
            ->disableOriginalConstructor()
            ->getMock();
        /* @var CouchDBClient $client */

        $documentManager = DocumentManager::create($client);

        self::assertInstanceOf(DefaultRepositoryFactory::class, $documentManager->getRepositoryFactory());

        $repositoryFactory = new DefaultRepositoryFactory;
        $documentManager->setRepositoryFactory($repositoryFactory);
        self::assertEquals($repositoryFactory, $documentManager->getRepositoryFactory());
    }

    public function testGetRepository()
    {
        $client = $this->getMockBuilder(CouchDBClient::class)
            ->disableOriginalConstructor()
            ->getMock();
        /* @var CouchDBClient $client */

        $factory = $this->getMockBuilder(DefaultRepositoryFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $factory->expects(self::once())->method('getRepository')->will(self::returnValue(new \stdClass));
        /* @var DefaultRepositoryFactory $factory */

        $documentManager = DocumentManager::create($client);
        $documentManager->setRepositoryFactory($factory);

        self::assertInstanceOf(\stdClass::class, $documentManager->getRepository('RepoName'));
    }
}
