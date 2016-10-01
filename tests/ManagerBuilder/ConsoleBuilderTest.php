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

use Jgut\Doctrine\ManagerBuilder\AbstractManagerBuilder;
use Jgut\Doctrine\ManagerBuilder\ConsoleBuilder;
use Jgut\Doctrine\ManagerBuilder\RelationalBuilder;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\HelperSet;

/**
 * Console builder tests.
 *
 * @group console
 */
class ConsoleBuilderTest extends \PHPUnit_Framework_TestCase
{
    public function testApplication()
    {
        $command = new Command('command');

        $builder = $this->getMockBuilder(RelationalBuilder::class)
            ->disableOriginalConstructor()
            ->getMock();
        $builder
            ->expects(self::once())
            ->method('getName')
            ->will(self::returnValue('command'));
        $builder
            ->expects(self::once())
            ->method('getConsoleCommands')
            ->will(self::returnValue([$command]));
        $builder
            ->expects(self::once())
            ->method('getConsoleHelperSet')
            ->will(self::returnValue(new HelperSet));
        /* @var AbstractManagerBuilder $builder */

        $consoleBuilder = new ConsoleBuilder();

        $consoleBuilder->addBuilder($builder);

        $application = $consoleBuilder->getApplication();
        self::assertInstanceOf(Application::class, $application);

        self::assertTrue($application->has('command'));
    }
}
