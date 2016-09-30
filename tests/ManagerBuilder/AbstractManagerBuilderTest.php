<?php
/**
 * doctrine-manager-builder (https://github.com/juliangut/doctrine-manager-builder)
 * Doctrine2 managers builder
 *
 * @license BSD-3-Clause
 * @author Julián Gutiérrez <juliangut@gmail.com>
 */

namespace Jgut\Doctrine\ManagerBuilder\Tests;

use Doctrine\Common\Cache\CacheProvider;
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
        /* @var AbstractManagerBuilder $objectBuilder */
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

        /* @var AbstractManagerBuilder $objectBuilder */
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
