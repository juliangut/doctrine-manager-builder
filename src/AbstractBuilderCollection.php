<?php
/**
 * doctrine-manager-builder (https://github.com/juliangut/doctrine-manager-builder)
 * Doctrine2 managers builder
 *
 * @license BSD-3-Clause
 * @author Julián Gutiérrez <juliangut@gmail.com>
 */

namespace Jgut\Doctrine\ManagerBuilder;

/**
 * Builder collection.
 */
abstract class AbstractBuilderCollection
{
    /**
     * Builders.
     *
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
     * Get registered builder by name.
     *
     * @param string $builderName
     *
     * @return ManagerBuilder|null
     */
    public function getBuilder($builderName)
    {
        if (array_key_exists($builderName, $this->builders)) {
            return $this->builders[$builderName];
        }

        return;
    }

    /**
     * Add builders.
     *
     * @param ManagerBuilder[] $builders
     *
     * @throws \RuntimeException
     */
    public function addBuilders(array $builders)
    {
        foreach ($builders as $builder) {
            $this->addBuilder($builder);
        }
    }

    /**
     * Add builder.
     *
     * @param ManagerBuilder $builder
     *
     * @throws \RuntimeException
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
    }

    /**
     * Remove registered builder.
     *
     * @param ManagerBuilder $builder
     *
     * @throws \RuntimeException
     */
    public function removeBuilder(ManagerBuilder $builder)
    {
        $builderName = (string) $builder->getName();
        if ($builderName === '') {
            throw new \RuntimeException('Only named manager builders allowed');
        }

        if (array_key_exists($builderName, $this->builders)) {
            unset($this->builders[$builderName]);
        }
    }
}
