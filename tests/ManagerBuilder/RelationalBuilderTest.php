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

namespace Jgut\Doctrine\ManagerBuilder\Tests;

use Doctrine\Common\EventSubscriber;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\DBAL\Types\BooleanType;
use Doctrine\DBAL\Types\StringType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Cache\CacheConfiguration;
use Doctrine\ORM\Cache\CacheFactory;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Query\AST\Functions\CountFunction;
use Doctrine\ORM\Query\AST\Functions\DateDiffFunction;
use Doctrine\ORM\Query\AST\Functions\LowerFunction;
use Doctrine\ORM\Query\Filter\SQLFilter;
use Doctrine\ORM\Repository\DefaultRepositoryFactory;
use Doctrine\ORM\Tools\Console\Command\InfoCommand;
use Doctrine\Persistence\Mapping\Driver\PHPDriver;
use InvalidArgumentException;
use Jgut\Doctrine\ManagerBuilder\ManagerBuilder;
use Jgut\Doctrine\ManagerBuilder\RelationalBuilder;
use Jgut\Doctrine\ManagerBuilder\Tests\Stub\ConsoleOutputStub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use stdClass;
use Symfony\Component\Console\Input\StringInput;
use UnexpectedValueException;

/**
 * @internal
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class RelationalBuilderTest extends TestCase
{
    public function testBadRepositoryClass(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/^Repository class should implement ".+\\EntityRepository"\.$/');

        $builder = new RelationalBuilder();
        $builder->setDefaultRepositoryClass(stdClass::class);
    }

    public function testManagerNoMetadataMapping(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No metadata mapping defined.');

        $builder = new RelationalBuilder();
        $builder->getManager();
    }

    public function testManagerNoMappingDriver(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Metadata mapping must be array with "driver" key or "type" and "path" keys.');

        $builder = new RelationalBuilder();
        $builder->setMetadataMapping([[]]);

        $builder->getManager();
    }

    public function testManagerWrongMappingDriver(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/^Provided driver should be an instance of ".+", "string" given\.$/');

        $builder = new RelationalBuilder();
        $builder->setMetadataMapping([__DIR__]);

        $builder->getManager();
    }

    public function testManagerWrongMappingType(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessageMatches('/^".+" is not a valid metadata mapping type\.$/');

        $builder = new RelationalBuilder();
        $builder->setMetadataMapping([['type' => 'unknown', 'path' => __DIR__]]);

        $builder->getManager();
    }

    public function testManagerSingleDefaultMapping(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Only one default metadata mapping driver allowed, a namespace must be defined.');

        $builder = new RelationalBuilder();
        $builder->setMetadataMapping([
            ['driver' => new PHPDriver([__DIR__])],
            ['type' => ManagerBuilder::METADATA_MAPPING_XML, 'path' => __DIR__, 'namespace' => 'namespace'],
            ['type' => ManagerBuilder::METADATA_MAPPING_YAML, 'path' => __DIR__],
        ]);

        $builder->getManager();
    }

    public function testManagerNoConnection(): void
    {
        $this->expectException(DBALException::class);
        $this->expectExceptionMessageMatches(
            '/^The options \'driver\' or \'driverClass\' are mandatory if no PDO instance/',
        );

        $builder = new RelationalBuilder();
        $builder->setMetadataMapping([
            ['type' => ManagerBuilder::METADATA_MAPPING_ATTRIBUTE, 'path' => __DIR__, 'namespace' => 'annotation'],
            ['type' => ManagerBuilder::METADATA_MAPPING_XML, 'path' => __DIR__, 'namespace' => 'xml'],
            ['type' => ManagerBuilder::METADATA_MAPPING_YAML, 'path' => __DIR__, 'namespace' => 'yaml'],
            ['type' => ManagerBuilder::METADATA_MAPPING_PHP, 'path' => __DIR__, 'namespace' => 'php'],
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

        $cacheFactory = $this->getMockBuilder(CacheFactory::class)
            ->disableOriginalConstructor()
            ->getMock();

        $cacheConfiguration = $this->getMockBuilder(CacheConfiguration::class)
            ->disableOriginalConstructor()
            ->getMock();

        $cacheConfiguration->expects(static::once())
            ->method('getCacheFactory')
            ->willReturn($cacheFactory);

        $logger = $this->getMockBuilder(LoggerInterface::class)
            ->getMock();

        $builder = new RelationalBuilder();
        $builder->setConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $builder->setMetadataMapping([['type' => ManagerBuilder::METADATA_MAPPING_ATTRIBUTE, 'path' => __DIR__]]);
        $builder->setRepositoryFactory(new DefaultRepositoryFactory());
        $builder->setSecondLevelCache($cacheConfiguration);
        $builder->setSqlLogger($logger);
        $builder->setCustomStringFunctions(['lower' => LowerFunction::class]);
        $builder->setCustomNumericFunctions(['count' => CountFunction::class]);
        $builder->setCustomDateTimeFunctions(['diff' => DateDiffFunction::class]);
        $builder->setCustomTypes(['string' => StringType::class, 'fake_type' => BooleanType::class]);
        $builder->setCustomMappingTypes(['string' => Types::STRING, 'fake_type' => Types::BOOLEAN]);
        $builder->setCustomFilters(['filter' => SQLFilter::class]);
        $builder->setEventSubscribers([$eventSubscriber]);

        static::assertInstanceOf(EntityManager::class, $builder->getManager());
    }

    public function testConsoleCommands(): void
    {
        $builder = new RelationalBuilder(['name' => 'builder-name']);
        $builder->setConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $builder->setMetadataMapping([
            [
                'type' => ManagerBuilder::METADATA_MAPPING_ATTRIBUTE,
                'path' => __DIR__ . '/Mapping/Files/Relational/Attribute',
                'namespace' => 'Jgut\Doctrine\ManagerBuilder\Tests\Mapping\Files\Relational\Attribute',
            ],
            [
                'type' => ManagerBuilder::METADATA_MAPPING_ANNOTATION,
                'path' => __DIR__ . '/Mapping/Files/Relational/Annotation',
                'namespace' => 'Jgut\Doctrine\ManagerBuilder\Tests\Mapping\Files\Relational\Annotation',
            ],
        ]);

        foreach ($builder->getConsoleCommands() as $command) {
            static::assertMatchesRegularExpression('/^(dbal|orm)-builder-name:/', (string) $command->getName());

            if ($command instanceof InfoCommand) {
                $output = new ConsoleOutputStub();
                $command->run(new StringInput(''), $output);

                static::assertStringContainsString(
                    'Jgut\Doctrine\ManagerBuilder\Tests\Mapping\Files\Relational\Attribute',
                    $output->getOutput(),
                );
                static::assertStringContainsString(
                    'Jgut\Doctrine\ManagerBuilder\Tests\Mapping\Files\Relational\Annotation',
                    $output->getOutput(),
                );
            }
        }
    }
}
