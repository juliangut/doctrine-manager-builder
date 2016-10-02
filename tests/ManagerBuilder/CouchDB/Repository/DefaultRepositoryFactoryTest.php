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

use Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\ODM\CouchDB\Mapping\ClassMetadata;
use Jgut\Doctrine\ManagerBuilder\CouchDB\DocumentManager;
use Jgut\Doctrine\ManagerBuilder\CouchDB\Repository\DefaultRepositoryFactory;

/**
 * CouchDB default repository factory tests.
 *
 * @group couchdb
 */
class DefaultRepositoryFactoryTest extends \PHPUnit_Framework_TestCase
{
    public function testRepositoryClassName()
    {
        $metadata = $this->getMockBuilder(ClassMetadata::class)
            ->disableOriginalConstructor()
            ->getMock();
        $metadata->expects(self::exactly(2))->method('getName')->will(self::returnValue(ObjectRepository::class));
        /* @var ClassMetadata $metadata */

        $manager = $this->getMockBuilder(DocumentManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $manager->expects(self::exactly(3))->method('getClassMetadata')->will(self::returnValue($metadata));
        $manager->expects(self::once())->method('getDefaultRepositoryClassName')
            ->will(self::returnValue('Doctrine\ODM\CouchDB\DocumentRepository'));
        /* @var DocumentManager $manager */

        $repository = new DefaultRepositoryFactory;

        self::assertInstanceOf(ObjectRepository::class, $repository->getRepository($manager, ObjectRepository::class));

        self::assertInstanceOf(ObjectRepository::class, $repository->getRepository($manager, ObjectRepository::class));
    }
}
