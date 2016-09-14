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
use Doctrine\Common\EventManager;
use Doctrine\Common\Proxy\AbstractProxyFactory;
use Jgut\Doctrine\ManagerBuilder\AbstractManagerBuilder;

/**
 * Abstract manager builder tests.
 */
class AbstractManagerBuilderTest extends \PHPUnit_Framework_TestCase
{
    public function testName()
    {
        /** @var AbstractManagerBuilder $objectBuilder */
        $objectBuilder = $this->getMockBuilder(AbstractManagerBuilder::class)
            ->disableOriginalConstructor()
            ->setMethodsExcept(['getName', 'setName'])
            ->getMockForAbstractClass();

        $objectBuilder->setName('Object_Builder');

        self::assertEquals('Object_Builder', $objectBuilder->getName());
    }

    public function testOptions()
    {
        $options = [
            'proxies_namespace' => 'MyTestProxyNamespace',
            'proxies_auto_generation' => AbstractProxyFactory::AUTOGENERATE_ALWAYS,
        ];

        /** @var AbstractManagerBuilder $objectBuilder */
        $objectBuilder = $this->getMockBuilder(AbstractManagerBuilder::class)
            ->disableOriginalConstructor()
            ->setMethodsExcept(['getOptions', 'getOption', 'hasOption', 'setOptions', 'setOption'])
            ->getMockForAbstractClass();

        $objectBuilder->setOptions($options);

        self::assertEquals($options, $objectBuilder->getOptions());
        self::assertTrue($objectBuilder->hasOption('proxies_auto_generation'));
        self::assertEquals('MyTestProxyNamespace', $objectBuilder->getOption('proxies_namespace'));

        $objectBuilder->setOption('proxies_path', [__DIR__]);
        self::assertEquals([__DIR__], $objectBuilder->getOption('proxies_path'));
    }

    public function testCache()
    {
        /** @var AbstractManagerBuilder $objectBuilder */
        $objectBuilder = $this->getMockBuilder(AbstractManagerBuilder::class)
            ->disableOriginalConstructor()
            ->setMethodsExcept(['getCacheDriver', 'setCacheDriver'])
            ->getMockForAbstractClass();

        self::assertInstanceOf(Cache::class, $objectBuilder->getCacheDriver());

        /** @var Cache $cacheDriver */
        $cacheDriver = $this->getMockBuilder(VoidCache::class)
            ->disableOriginalConstructor()
            ->getMock();
        $objectBuilder->setCacheDriver($cacheDriver);

        self::assertEquals($cacheDriver, $objectBuilder->getCacheDriver());
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Cache Driver provided is not valid
     */
    public function testWrongCache()
    {
        /** @var AbstractManagerBuilder $objectBuilder */
        $objectBuilder = $this->getMockBuilder(AbstractManagerBuilder::class)
            ->disableOriginalConstructor()
            ->setMethodsExcept(['getOption', 'setOption', 'getCacheDriver'])
            ->getMockForAbstractClass();

        $objectBuilder->setOption('cache_driver', 'NotCacheDriver');

        $objectBuilder->getCacheDriver();

        self::assertEquals('', $objectBuilder->getCacheDriver());
    }

    public function testMetadataCache()
    {
        /** @var AbstractManagerBuilder $objectBuilder */
        $objectBuilder = $this->getMockBuilder(AbstractManagerBuilder::class)
            ->disableOriginalConstructor()
            ->setMethodsExcept([
                'getOption',
                'setOption',
                'getCacheDriver',
                'setCacheDriver',
                'getMetadataCacheDriver',
                'setMetadataCacheDriver',
            ])
            ->getMockForAbstractClass();

        $cacheDriver = $this->getMockBuilder(VoidCache::class)
            ->disableOriginalConstructor()
            ->getMock();
        $cacheDriver
            ->expects(static::once())
            ->method('getNamespace')
            ->will(static::returnValue(''));
        /* @var Cache $cacheDriver */
        $objectBuilder->setCacheDriver($cacheDriver);

        $objectBuilder->setOption('metadata_cache_driver', 'NotCacheDriver');

        self::assertEquals($cacheDriver, $objectBuilder->getMetadataCacheDriver());

        /* @var Cache $metadataCacheDriver */
        $metadataCacheDriver = $this->getMockBuilder(VoidCache::class)
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
