<?php

/*
 * doctrine-manager-builder (https://github.com/juliangut/doctrine-manager-builder).
 * Doctrine2 managers builder.
 *
 * @license BSD-3-Clause
 * @link https://github.com/juliangut/doctrine-manager-builder
 * @author Julián Gutiérrez <juliangut@gmail.com>
 */

declare(strict_types=1);

namespace Jgut\Doctrine\ManagerBuilder;

use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Console\Command\Command;

interface ManagerBuilder
{
    public const METADATA_MAPPING_ATTRIBUTE = 'attribute';
    public const METADATA_MAPPING_ANNOTATION = 'annotation';
    public const METADATA_MAPPING_XML = 'xml';
    public const METADATA_MAPPING_YAML = 'yaml';
    public const METADATA_MAPPING_PHP = 'php';

    public function getName(): ?string;

    public function getManager(bool $force = false): ObjectManager;

    /**
     * @return array<Command>
     */
    public function getConsoleCommands(): array;
}
