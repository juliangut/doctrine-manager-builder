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

use Doctrine\Common\Cache\CacheProvider;
use Doctrine\Common\EventManager;
use Doctrine\Common\Persistence\Mapping\Driver\MappingDriverChain;
use Jgut\Doctrine\ManagerBuilder\AbstractManagerBuilder;

/**
 * Abstract manager builder tests.
 */
class AbstractManagerBuilderTest extends \PHPUnit_Framework_TestCase
{
    public function testName()
    {
        /* @var AbstractManagerBuilder $objectBuilder */
        $objectBuilder = $this->getMockBuilder(AbstractManagerBuilder::class)
            ->disableOriginalConstructor()
            ->setMethodsExcept(['getName', 'setName'])
            ->getMockForAbstractClass();

        $objectBuilder->setName('Object_Builder');

        self::assertEquals('Object_Builder', $objectBuilder->getName());
    }

    public function testMetadataMappingDriver()
    {
        /* @var AbstractManagerBuilder $objectBuilder */
        $objectBuilder = $this->getMockBuilder(AbstractManagerBuilder::class)
            ->disableOriginalConstructor()
            ->setMethodsExcept([
                'getOption',
                'setOption',
                'getMetadataMappingDriver',
                'setMetadataMappingDriver',
            ])
            ->getMockForAbstractClass();

        /* @var MappingDriverChain $mappingDriver */
        $mappingDriver = $this->getMockBuilder(MappingDriverChain::class)
            ->disableOriginalConstructor()
            ->getMock();
        $objectBuilder->setMetadataMappingDriver($mappingDriver);

        self::assertEquals($mappingDriver, $objectBuilder->getMetadataMappingDriver());
    }

    public function testMetadataCache()
    {
        /* @var AbstractManagerBuilder $objectBuilder */
        $objectBuilder = $this->getMockBuilder(AbstractManagerBuilder::class)
            ->disableOriginalConstructor()
            ->setMethodsExcept([
                'getOption',
                'setOption',
                'getMetadataCacheDriver',
                'setMetadataCacheDriver',
            ])
            ->getMockForAbstractClass();

        self::assertInstanceOf(CacheProvider::class, $objectBuilder->getMetadataCacheDriver());

        /* @var CacheProvider $metadataCacheDriver */
        $metadataCacheDriver = $this->getMockBuilder(CacheProvider::class)
            ->disableOriginalConstructor()
            ->getMock();
        $objectBuilder->setMetadataCacheDriver($metadataCacheDriver);

        self::assertEquals($metadataCacheDriver, $objectBuilder->getMetadataCacheDriver());
    }

    public function testEventManager()
    {
        /* @var AbstractManagerBuilder $objectBuilder */
        $objectBuilder = $this->getMockBuilder(AbstractManagerBuilder::class)
            ->disableOriginalConstructor()
            ->setMethodsExcept(['getEventManager', 'setEventManager'])
            ->getMockForAbstractClass();

        self::assertInstanceOf(EventManager::class, $objectBuilder->getEventManager());

        /* @var EventManager $eventManager */
        $eventManager = $this->getMockBuilder(EventManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $objectBuilder->setEventManager($eventManager);

        self::assertEquals($eventManager, $objectBuilder->getEventManager());
    }
}
