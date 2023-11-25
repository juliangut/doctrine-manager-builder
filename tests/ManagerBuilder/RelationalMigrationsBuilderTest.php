<?php

/*
 * (c) 2016-2023 Julián Gutiérrez <juliangut@gmail.com>
 *
 * @license BSD-3-Clause
 * @link https://github.com/juliangut/doctrine-manager-builder
 */

declare(strict_types=1);

namespace Jgut\Doctrine\ManagerBuilder\Tests;

use Doctrine\Migrations\Configuration\Configuration;
use Doctrine\Migrations\Metadata\Storage\TableMetadataStorageConfiguration;
use Doctrine\Migrations\Tools\Console\Command\StatusCommand;
use Jgut\Doctrine\ManagerBuilder\ManagerBuilder;
use Jgut\Doctrine\ManagerBuilder\RelationalMigrationsBuilder;
use Jgut\Doctrine\ManagerBuilder\Tests\Stub\ConsoleOutputStub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\StringInput;

/**
 * @internal
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class RelationalMigrationsBuilderTest extends TestCase
{
    public function testConsoleCommandsWithConfigurationArray(): void
    {
        $builder = new RelationalMigrationsBuilder(['name' => 'builder-name']);
        $builder->setConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $builder->setMetadataMapping([
            [
                'type' => ManagerBuilder::METADATA_MAPPING_ATTRIBUTE,
                'path' => __DIR__ . '/Mapping/Files/Relational/Attribute',
                'namespace' => 'Jgut\Doctrine\ManagerBuilder\Tests\Mapping\Files\Relational\Attribute',
            ],
        ]);
        $builder->setMigrationsConfiguration([
            'migrations_paths' => [
                'App\Migrations' => __DIR__ . '/files/migrations',
            ],
            'table_storage' => [
                'table_name' => 'doctrine_migrations',
            ],
        ]);
        $logger = $this->getMockBuilder(LoggerInterface::class)
            ->getMock();
        $builder->setMigrationsLogger($logger);

        foreach ($builder->getConsoleCommands() as $command) {
            static::assertMatchesRegularExpression(
                '/^(dbal|orm|migrations)-builder-name:/',
                (string) $command->getName(),
            );

            if ($command instanceof StatusCommand) {
                $output = new ConsoleOutputStub();
                $command->run(new StringInput(''), $output);

                static::assertStringContainsString('doctrine_migrations', $output->getOutput());
                static::assertStringContainsString('App\Migrations\Version20230101000000', $output->getOutput());
                static::assertStringContainsString(__DIR__ . '/files/migrations', $output->getOutput());
            }
        }
    }

    public function testConsoleCommandsWithConfigurationObject(): void
    {
        $builder = new RelationalMigrationsBuilder(['name' => 'builder-name']);
        $builder->setConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $builder->setMetadataMapping([
            [
                'type' => ManagerBuilder::METADATA_MAPPING_ATTRIBUTE,
                'path' => __DIR__ . '/Mapping/Files/Relational/Attribute',
                'namespace' => 'Jgut\Doctrine\ManagerBuilder\Tests\Mapping\Files\Relational\Attribute',
            ],
        ]);
        $configuration = new Configuration();
        $configuration->addMigrationsDirectory('App\Migrations', __DIR__ . '/files/migrations');
        $storageConfiguration = new TableMetadataStorageConfiguration();
        $storageConfiguration->setTableName('doctrine_migrations');
        $configuration->setMetadataStorageConfiguration($storageConfiguration);
        $builder->setMigrationsConfiguration($configuration);
        $logger = $this->getMockBuilder(LoggerInterface::class)
            ->getMock();
        $builder->setMigrationsLogger($logger);

        foreach ($builder->getConsoleCommands() as $command) {
            static::assertMatchesRegularExpression(
                '/^(dbal|orm|migrations)-builder-name:/',
                (string) $command->getName(),
            );

            if ($command instanceof StatusCommand) {
                $output = new ConsoleOutputStub();
                $command->run(new StringInput(''), $output);

                static::assertStringContainsString('doctrine_migrations', $output->getOutput());
                static::assertStringContainsString('App\Migrations\Version20230101000000', $output->getOutput());
                static::assertStringContainsString(__DIR__ . '/files/migrations', $output->getOutput());
            }
        }
    }
}
