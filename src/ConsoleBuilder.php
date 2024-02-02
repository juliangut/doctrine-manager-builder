<?php

/*
 * (c) 2016-2024 Julián Gutiérrez <juliangut@gmail.com>
 *
 * @license BSD-3-Clause
 * @link https://github.com/juliangut/doctrine-manager-builder
 */

declare(strict_types=1);

namespace Jgut\Doctrine\ManagerBuilder;

use Symfony\Component\Console\Application;

class ConsoleBuilder extends AbstractBuilderCollection
{
    public function getApplication(?string $commandPrefix = null): Application
    {
        $application = new Application('Doctrine Manager Builder Command Line Interface');
        $application->setAutoExit(true);
        $application->setCatchExceptions(true);

        foreach ($this->builders as $builder) {
            foreach ($builder->getConsoleCommands() as $command) {
                if ($commandPrefix !== null) {
                    $command->setName(sprintf('%s:%s', rtrim($commandPrefix, ':'), $command->getName()));
                }

                $application->add($command);
            }
        }

        return $application;
    }
}
