<?php

/*
 * (c) 2016-2023 Julián Gutiérrez <juliangut@gmail.com>
 *
 * @license BSD-3-Clause
 * @link https://github.com/juliangut/doctrine-manager-builder
 */

declare(strict_types=1);

namespace Jgut\Doctrine\ManagerBuilder\Tests;

use Doctrine\Common\EventSubscriber;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Query\Filter\BsonFilter;
use Doctrine\ODM\MongoDB\Repository\DefaultRepositoryFactory;
use Doctrine\ODM\MongoDB\Types\BooleanType;
use Doctrine\ODM\MongoDB\Types\StringType;
use InvalidArgumentException;
use Jgut\Doctrine\ManagerBuilder\Console\Command\MongoDB\InfoCommand;
use Jgut\Doctrine\ManagerBuilder\ManagerBuilder;
use Jgut\Doctrine\ManagerBuilder\MongoDBBuilder;
use Jgut\Doctrine\ManagerBuilder\Tests\Stub\ConsoleOutputStub;
use MongoDB\Client;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Symfony\Component\Console\Input\StringInput;

/**
 * @internal
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class MongoDBBuilderTest extends TestCase
{
    public function testBadRepositoryClass(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/^Repository class should implement ".+\\DocumentRepository"\.$/');

        $builder = new MongoDBBuilder();
        $builder->setDefaultRepositoryClass(stdClass::class);
    }

    public function testBadHydrationAutoGeneration(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid hydration auto generation value "-1".');

        $builder = new MongoDBBuilder();
        $builder->setHydrationAutoGeneration(-1);
    }

    public function testBadPersistCollectionAutoGeneration(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid persist collection auto generation value "-1".');

        $builder = new MongoDBBuilder();
        $builder->setPersistentCollectionAutoGeneration(-1);
    }

    public function testUnsupportedYamlMapping(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Yaml driver is no longer available.');

        $builder = new MongoDBBuilder();
        $builder->setMetadataMapping([
            ['type' => ManagerBuilder::METADATA_MAPPING_YAML, 'path' => __DIR__],
        ]);

        $builder->getManager(true);
    }

    public function testManager(): void
    {
        $eventSubscriber = $this->getMockBuilder(EventSubscriber::class)
            ->disableOriginalConstructor()
            ->getMock();
        $eventSubscriber
            ->method('getSubscribedEvents')
            ->willReturn('event');

        $builder = new MongoDBBuilder();
        $builder->setClient(new Client('mongodb://localhost'));
        $builder->setMetadataMapping([
            ['type' => ManagerBuilder::METADATA_MAPPING_ATTRIBUTE, 'path' => __DIR__],
        ]);
        $builder->setRepositoryFactory(new DefaultRepositoryFactory());
        $builder->setDefaultDatabase('ddbb');
        $builder->setEventSubscribers([$eventSubscriber]);
        $builder->setCustomTypes(['string' => StringType::class, 'fake_type' => BooleanType::class]);
        $builder->setCustomFilters(['filter' => BsonFilter::class]);

        static::assertInstanceOf(DocumentManager::class, $builder->getManager());
    }

    public function testConsoleCommands(): void
    {
        $builder = new MongoDBBuilder(['name' => 'builder-name']);
        $builder->setMetadataMapping([
            ['type' => ManagerBuilder::METADATA_MAPPING_ATTRIBUTE, 'path' => __DIR__],
        ]);
        $builder->setMetadataMapping([
            [
                'type' => ManagerBuilder::METADATA_MAPPING_ATTRIBUTE,
                'path' => __DIR__ . '/Mapping/Files/MongoDB/Attribute',
                'namespace' => 'Jgut\Doctrine\ManagerBuilder\Tests\Mapping\Files\MongoDB\Attribute',
            ],
            [
                'type' => ManagerBuilder::METADATA_MAPPING_ANNOTATION,
                'path' => __DIR__ . '/Mapping/Files/MongoDB/Annotation',
                'namespace' => 'Jgut\Doctrine\ManagerBuilder\Tests\Mapping\Files\MongoDB\Annotation',
            ],
        ]);

        foreach ($builder->getConsoleCommands() as $command) {
            static::assertMatchesRegularExpression('/^odm-builder-name:/', (string) $command->getName());

            if ($command instanceof InfoCommand) {
                $output = new ConsoleOutputStub();
                $command->run(new StringInput(''), $output);

                static::assertStringContainsString(
                    'Jgut\Doctrine\ManagerBuilder\Tests\Mapping\Files\MongoDB\Attribute',
                    $output->getOutput(),
                );
                static::assertStringContainsString(
                    'Jgut\Doctrine\ManagerBuilder\Tests\Mapping\Files\MongoDB\Annotation',
                    $output->getOutput(),
                );
            }
        }
    }
}
