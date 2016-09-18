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
     * @throws \RuntimeException
     *
     * @return $this
     */
    public function addBuilder(ManagerBuilder $builder)
    {
        $builderName = (string) $builder->getName();

        if ($builderName === '') {
            throw new \RuntimeException('Only named manager builders allowed');
        }

        if (array_key_exists($builderName, $this->builders)) {
            throw new \RuntimeException(sprintf('"%s" manager builder is already registered', $builderName));
        }

        $this->builders[$builderName] = $builder;

        return $this;
    }

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
