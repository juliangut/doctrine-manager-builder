<?php

/*
 * (c) 2016-2024 Julián Gutiérrez <juliangut@gmail.com>
 *
 * @license BSD-3-Clause
 * @link https://github.com/juliangut/doctrine-manager-builder
 */

declare(strict_types=1);

namespace Jgut\Doctrine\ManagerBuilder\Tests;

use InvalidArgumentException;
use Jgut\Doctrine\ManagerBuilder\AbstractManagerBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class AbstractManagerBuilderTest extends TestCase
{
    public function testInvalidConfigurationOption(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown configuration "option".');

        $this->getMockBuilder(AbstractManagerBuilder::class)
            ->setConstructorArgs([['option' => 'value']])
            ->getMockForAbstractClass();
    }

    public function testConfigurationName(): void
    {
        $objectBuilder = $this->getMockBuilder(AbstractManagerBuilder::class)
            ->setConstructorArgs([['name' => 'Object_Builder']])
            ->getMockForAbstractClass();

        static::assertEquals('Object_Builder', $objectBuilder->getName());
    }
}
