<?php

/*
 * doctrine-manager-builder (https://github.com/juliangut/doctrine-manager-builder).
 * Doctrine2 managers builder.
 *
 * @license BSD-3-Clause
 * @link https://github.com/juliangut/doctrine-manager-builder
 * @author Julián Gutiérrez <juliangut@gmail.com>
 */

namespace Jgut\Doctrine\ManagerBuilder;

use Symfony\Component\Console\Application;

/**
 * Console builder.
 */
class ConsoleBuilder extends AbstractBuilderCollection
{
    /**
     * Get console application.
     *
     * @return Application
     */
    public function getApplication()
    {
        $application = new Application('Doctrine Manager Builder Command Line Interface');
        $application->setCatchExceptions(true);

        foreach ($this->builders as $builder) {
            $helperSet = $builder->getConsoleHelperSet();

            foreach ($builder->getConsoleCommands() as $command) {
                $application->add($command)->setHelperSet($helperSet);
            }
        }

        return $application;
    }
}
