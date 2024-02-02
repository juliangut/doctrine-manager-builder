<?php

/*
 * (c) 2016-2024 Julián Gutiérrez <juliangut@gmail.com>
 *
 * @license BSD-3-Clause
 * @link https://github.com/juliangut/doctrine-manager-builder
 */

declare(strict_types=1);

namespace Jgut\Doctrine\ManagerBuilder;

use RuntimeException;

abstract class AbstractBuilderCollection
{
    /**
     * @var array<string, ManagerBuilder>
     */
    protected array $builders = [];

    /**
     * @return array<string, ManagerBuilder>
     */
    public function getBuilders(): array
    {
        return $this->builders;
    }

    public function getBuilder(string $builderName): ?ManagerBuilder
    {
        return $this->builders[$builderName] ?? null;
    }

    /**
     * @param list<ManagerBuilder> $builders
     *
     * @throws RuntimeException
     */
    public function addBuilders(array $builders): void
    {
        foreach ($builders as $builder) {
            $this->addBuilder($builder);
        }
    }

    /**
     * @throws RuntimeException
     */
    public function addBuilder(ManagerBuilder $builder): void
    {
        $builderName = $builder->getName();
        if ($builderName === null || $builderName === '') {
            throw new RuntimeException('Only named manager builders allowed.');
        }

        if (\array_key_exists($builderName, $this->builders)) {
            throw new RuntimeException(sprintf('"%s" manager builder is already registered.', $builderName));
        }

        $this->builders[$builderName] = $builder;
    }

    /**
     * @throws RuntimeException
     */
    public function removeBuilder(ManagerBuilder $builder): void
    {
        $builderName = $builder->getName();
        if ($builderName === null || $builderName === '') {
            throw new RuntimeException('Only named manager builders allowed.');
        }

        if (\array_key_exists($builderName, $this->builders)) {
            unset($this->builders[$builderName]);
        }
    }
}
