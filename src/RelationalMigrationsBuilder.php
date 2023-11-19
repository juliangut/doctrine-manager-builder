<?php

/*
 * (c) 2016-2023 Julián Gutiérrez <juliangut@gmail.com>
 *
 * @license BSD-3-Clause
 * @link https://github.com/juliangut/doctrine-manager-builder
 */

declare(strict_types=1);

namespace Jgut\Doctrine\ManagerBuilder;

use Doctrine\Migrations\Configuration\Configuration;
use Doctrine\Migrations\Configuration\EntityManager\ExistingEntityManager;
use Doctrine\Migrations\Configuration\Migration\ConfigurationArray;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Tools\Console\Command\CurrentCommand;
use Doctrine\Migrations\Tools\Console\Command\DiffCommand;
use Doctrine\Migrations\Tools\Console\Command\DoctrineCommand;
use Doctrine\Migrations\Tools\Console\Command\DumpSchemaCommand;
use Doctrine\Migrations\Tools\Console\Command\ExecuteCommand;
use Doctrine\Migrations\Tools\Console\Command\GenerateCommand;
use Doctrine\Migrations\Tools\Console\Command\LatestCommand;
use Doctrine\Migrations\Tools\Console\Command\ListCommand;
use Doctrine\Migrations\Tools\Console\Command\MigrateCommand;
use Doctrine\Migrations\Tools\Console\Command\RollupCommand;
use Doctrine\Migrations\Tools\Console\Command\StatusCommand;
use Doctrine\Migrations\Tools\Console\Command\SyncMetadataCommand;
use Doctrine\Migrations\Tools\Console\Command\UpToDateCommand;
use Doctrine\Migrations\Tools\Console\Command\VersionCommand;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Helper\HelperSet;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.LongVariable)
 */
class RelationalMigrationsBuilder extends RelationalBuilder
{
    /**
     * @var array<string, mixed>
     */
    private array $migrationsConfiguration = [
        'table_storage' => [
            'table_name' => 'doctrine_migration_versions',
            'version_column_name' => 'version',
            'version_column_length' => 191,
            'executed_at_column_name' => 'executed_at',
            'execution_time_column_name' => 'execution_time',
        ],
        'all_or_nothing' => true,
        'transactional' => true,
        'check_database_platform' => true,
        'organize_migrations' => Configuration::VERSIONS_ORGANIZATION_NONE,
    ];

    private ?LoggerInterface $migrationsLogger = null;

    /**
     * @param array<string, mixed> $migrationsConfiguration
     */
    public function setMigrationsConfiguration(array $migrationsConfiguration): void
    {
        $this->migrationsConfiguration = array_replace_recursive(
            $this->migrationsConfiguration,
            $migrationsConfiguration,
        );
    }

    public function setMigrationsLogger(LoggerInterface $migrationLogger): void
    {
        $this->migrationsLogger = $migrationLogger;
    }

    public function getConsoleCommands(): array
    {
        $entityManager = $this->getManager();

        $dependencyFactory = DependencyFactory::fromEntityManager(
            new ConfigurationArray($this->migrationsConfiguration),
            new ExistingEntityManager($entityManager),
            $this->migrationsLogger,
        );

        $migrationCommands = [
            new CurrentCommand($dependencyFactory),
            new DumpSchemaCommand($dependencyFactory),
            new ExecuteCommand($dependencyFactory),
            new GenerateCommand($dependencyFactory),
            new LatestCommand($dependencyFactory),
            new MigrateCommand($dependencyFactory),
            new RollupCommand($dependencyFactory),
            new StatusCommand($dependencyFactory),
            new VersionCommand($dependencyFactory),
            new UpToDateCommand($dependencyFactory),
            new SyncMetadataCommand($dependencyFactory),
            new ListCommand($dependencyFactory),
            new DiffCommand($dependencyFactory),
        ];

        $commands = parent::getConsoleCommands();
        /** @var HelperSet $helperSet */
        $helperSet = $commands[array_key_first($commands)]->getHelperSet();
        $commandPrefix = (string) $this->getName();

        return array_merge(
            $commands,
            array_map(
                static function (DoctrineCommand $command) use ($helperSet, $commandPrefix): DoctrineCommand {
                    if ($commandPrefix !== '') {
                        $commandNames = array_map(
                            static fn(string $commandName): string
                                => (string) preg_replace(
                                    '/^migrations:/',
                                    'migrations-' . $commandPrefix . ':',
                                    $commandName,
                                ),
                            array_merge([$command->getName()], $command->getAliases()),
                        );

                        $command->setName(array_shift($commandNames));
                        $command->setAliases($commandNames);
                    }

                    $command->setHelperSet($helperSet);

                    return $command;
                },
                $migrationCommands,
            ),
        );
    }
}
