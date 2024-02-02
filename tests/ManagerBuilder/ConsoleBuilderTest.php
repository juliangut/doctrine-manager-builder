<?php

/*
 * (c) 2016-2024 Julián Gutiérrez <juliangut@gmail.com>
 *
 * @license BSD-3-Clause
 * @link https://github.com/juliangut/doctrine-manager-builder
 */

declare(strict_types=1);

namespace Jgut\Doctrine\ManagerBuilder\Tests;

use Jgut\Doctrine\ManagerBuilder\ConsoleBuilder;
use Jgut\Doctrine\ManagerBuilder\ManagerBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\HelperSet;

/**
 * @internal
 */
class ConsoleBuilderTest extends TestCase
{
    public function testApplication(): void
    {
        $helperSet = $this->getMockBuilder(HelperSet::class)
            ->disableOriginalConstructor()
            ->getMock();

        $command = new Command('command');
        $command->setHelperSet($helperSet);

        $builder = $this->getMockBuilder(ManagerBuilder::class)
            ->getMock();
        $builder
            ->method('getName')
            ->willReturn('command');
        $builder
            ->method('getConsoleCommands')
            ->willReturn([$command]);

        $consoleBuilder = new ConsoleBuilder();

        $consoleBuilder->addBuilder($builder);

        $application = $consoleBuilder->getApplication('prefix');

        static::assertTrue($application->has('prefix:command'));
    }
}
