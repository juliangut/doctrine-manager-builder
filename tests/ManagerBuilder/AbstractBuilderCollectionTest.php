<?php

/*
 * (c) 2016-2023 Julián Gutiérrez <juliangut@gmail.com>
 *
 * @license BSD-3-Clause
 * @link https://github.com/juliangut/doctrine-manager-builder
 */

declare(strict_types=1);

namespace Jgut\Doctrine\ManagerBuilder\Tests;

use Jgut\Doctrine\ManagerBuilder\AbstractBuilderCollection;
use Jgut\Doctrine\ManagerBuilder\ManagerBuilder;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @internal
 */
class AbstractBuilderCollectionTest extends TestCase
{
    public function testAddRemoveBuilder(): void
    {
        $builder = $this->getMockBuilder(ManagerBuilder::class)
            ->getMock();
        $builder
            ->method('getName')
            ->willReturn('builder');

        $collection = new class () extends AbstractBuilderCollection {};
        $collection->addBuilders([$builder]);

        static::assertCount(1, $collection->getBuilders());
        static::assertEquals($builder, $collection->getBuilder('builder'));

        $collection->removeBuilder($builder);

        static::assertCount(0, $collection->getBuilders());
        static::assertNull($collection->getBuilder('builder'));
    }

    public function testAddUnnamedBuilder(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Only named manager builders allowed');

        $builder = $this->getMockBuilder(ManagerBuilder::class)
            ->getMock();

        $collection = new class () extends AbstractBuilderCollection {};
        $collection->addBuilder($builder);
    }

    public function testRemoveUnnamedBuilder(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Only named manager builders allowed');

        $builder = $this->getMockBuilder(ManagerBuilder::class)
            ->getMock();

        $collection = new class () extends AbstractBuilderCollection {};
        $collection->removeBuilder($builder);
    }

    public function testDuplicatedBuilder(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('"builder" manager builder is already registered');

        $builder = $this->getMockBuilder(ManagerBuilder::class)
            ->getMock();
        $builder
            ->expects(static::once())
            ->method('getName')
            ->willReturn('builder');

        $duplicatedBuilder = $this->getMockBuilder(ManagerBuilder::class)
            ->getMock();
        $duplicatedBuilder
            ->expects(static::once())
            ->method('getName')
            ->willReturn('builder');

        $collection = new class () extends AbstractBuilderCollection {};
        $collection->addBuilder($builder);
        $collection->addBuilder($duplicatedBuilder);
    }
}
