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

use Jgut\Doctrine\ManagerBuilder\AbstractBuilderCollection;
use Jgut\Doctrine\ManagerBuilder\AbstractManagerBuilder;

/**
 * Abstract builder collection tests.
 */
class AbstractBuilderCollectionTest extends \PHPUnit_Framework_TestCase
{
    public function testAddRemoveBuilder()
    {
        $builder = $this->getMockBuilder(AbstractManagerBuilder::class)
            ->disableOriginalConstructor()
            ->setMethods(['getName'])
            ->getMockForAbstractClass();
        $builder
            ->expects(self::any())
            ->method('getName')
            ->will(self::returnValue('builder'));
        /* @var AbstractManagerBuilder $builder */

        /* @var AbstractBuilderCollection $collection */
        $collection = $this->getMockBuilder(AbstractBuilderCollection::class)
            ->disableOriginalConstructor()
            ->setMethodsExcept(['addBuilders', 'addBuilder', 'getBuilders', 'getBuilder', 'removeBuilder'])
            ->getMockForAbstractClass();

        $collection->addBuilders([$builder]);

        self::assertCount(1, $collection->getBuilders());
        self::assertEquals($builder, $collection->getBuilder('builder'));

        $collection->removeBuilder($builder);

        self::assertCount(0, $collection->getBuilders());
        self::assertNull($collection->getBuilder('builder'));
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Only named manager builders allowed
     */
    public function testAddUnnamedBuilder()
    {
        /* @var AbstractManagerBuilder $builder */
        $builder = $this->getMockBuilder(AbstractManagerBuilder::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();

        /* @var AbstractBuilderCollection $collection */
        $collection = $this->getMockBuilder(AbstractBuilderCollection::class)
            ->disableOriginalConstructor()
            ->setMethodsExcept(['addBuilder'])
            ->getMockForAbstractClass();

        $collection->addBuilder($builder);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Only named manager builders allowed
     */
    public function testRemoveUnnamedBuilder()
    {
        /* @var AbstractManagerBuilder $builder */
        $builder = $this->getMockBuilder(AbstractManagerBuilder::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();

        /* @var AbstractBuilderCollection $collection */
        $collection = $this->getMockBuilder(AbstractBuilderCollection::class)
            ->disableOriginalConstructor()
            ->setMethodsExcept(['removeBuilder'])
            ->getMockForAbstractClass();

        $collection->removeBuilder($builder);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage "builder" manager builder is already registered
     */
    public function testDuplicatedBuilder()
    {
        $builder = $this->getMockBuilder(AbstractManagerBuilder::class)
            ->disableOriginalConstructor()
            ->setMethods(['getName'])
            ->getMockForAbstractClass();
        $builder
            ->expects(self::once())
            ->method('getName')
            ->will(self::returnValue('builder'));
        /* @var AbstractManagerBuilder $builder */

        $duplicatedBuilder = $this->getMockBuilder(AbstractManagerBuilder::class)
            ->disableOriginalConstructor()
            ->setMethods(['getName'])
            ->getMockForAbstractClass();
        $duplicatedBuilder
            ->expects(self::once())
            ->method('getName')
            ->will(self::returnValue('builder'));
        /* @var AbstractManagerBuilder $duplicatedBuilder */

        /* @var AbstractBuilderCollection $collection */
        $collection = $this->getMockBuilder(AbstractBuilderCollection::class)
            ->disableOriginalConstructor()
            ->setMethodsExcept(['addBuilder'])
            ->getMockForAbstractClass();

        $collection->addBuilder($builder);
        $collection->addBuilder($duplicatedBuilder);
    }
}
