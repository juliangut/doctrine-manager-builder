<?php
/**
 * doctrine-manager-builder (https://github.com/juliangut/doctrine-manager-builder)
 * Doctrine2 managers builder
 *
 * @license BSD-3-Clause
 * @author Julián Gutiérrez <juliangut@gmail.com>
 */

namespace Jgut\Doctrine\ManagerBuilder;

use Symfony\Component\Console\Application;

/**
 * Console builder.
 */
class ConsoleBuilder
{
    /**
     * @var ManagerBuilder[]
     */
    protected $builders = [];

    /**
     * Get registered builders.
     *
     * @return ManagerBuilder[]
     */
    public function getBuilders()
    {
        return array_values($this->builders);
    }

    /**
     * Add builder.
     *
     * @param ManagerBuilder $builder
     *
     * @return $this
     */
    public function addBuilder(ManagerBuilder $builder)
    {
        $this->builders[$builder->getName()] = $builder;

        return $this;
    }

    /**
     * Get console application.
     *
     * @return Application
     */
    public function getApplication()
    {
        $application = new Application('Doctrine Command Line Interface');
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
