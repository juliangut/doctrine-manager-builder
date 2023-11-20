<?php

/*
 * (c) 2016-2023 Julián Gutiérrez <juliangut@gmail.com>
 *
 * @license BSD-3-Clause
 * @link https://github.com/juliangut/doctrine-manager-builder
 */

declare(strict_types=1);

namespace Jgut\Doctrine\ManagerBuilder;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Cache\CacheProvider;
use Doctrine\Common\Cache\Psr6\CacheAdapter;
use Doctrine\Common\EventManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Middleware as MiddlewareInterface;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Logging\Middleware;
use Doctrine\DBAL\Tools\Console\Command\ReservedWordsCommand;
use Doctrine\DBAL\Tools\Console\Command\RunSqlCommand;
use Doctrine\DBAL\Tools\Console\ConnectionProvider\SingleConnectionProvider;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Cache\CacheConfiguration;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\DefaultQuoteStrategy;
use Doctrine\ORM\Mapping\Driver\AnnotationDriver;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Mapping\Driver\XmlDriver;
use Doctrine\ORM\Mapping\Driver\YamlDriver;
use Doctrine\ORM\Mapping\NamingStrategy;
use Doctrine\ORM\Mapping\QuoteStrategy;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\Filter\SQLFilter;
use Doctrine\ORM\Repository\RepositoryFactory;
use Doctrine\ORM\Tools\Console\Command\ClearCache\CollectionRegionCommand;
use Doctrine\ORM\Tools\Console\Command\ClearCache\EntityRegionCommand;
use Doctrine\ORM\Tools\Console\Command\ClearCache\MetadataCommand;
use Doctrine\ORM\Tools\Console\Command\ClearCache\QueryCommand;
use Doctrine\ORM\Tools\Console\Command\ClearCache\QueryRegionCommand;
use Doctrine\ORM\Tools\Console\Command\ClearCache\ResultCommand;
use Doctrine\ORM\Tools\Console\Command\ConvertMappingCommand;
use Doctrine\ORM\Tools\Console\Command\EnsureProductionSettingsCommand;
use Doctrine\ORM\Tools\Console\Command\GenerateProxiesCommand;
use Doctrine\ORM\Tools\Console\Command\InfoCommand;
use Doctrine\ORM\Tools\Console\Command\MappingDescribeCommand;
use Doctrine\ORM\Tools\Console\Command\RunDqlCommand;
use Doctrine\ORM\Tools\Console\Command\SchemaTool\CreateCommand;
use Doctrine\ORM\Tools\Console\Command\SchemaTool\DropCommand;
use Doctrine\ORM\Tools\Console\Command\SchemaTool\UpdateCommand;
use Doctrine\ORM\Tools\Console\Command\ValidateSchemaCommand;
use Doctrine\ORM\Tools\Console\EntityManagerProvider\SingleManagerProvider;
use Doctrine\ORM\Tools\Console\Helper\EntityManagerHelper;
use InvalidArgumentException;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\HelperSet;
use UnexpectedValueException;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.LongVariable)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class RelationalBuilder extends AbstractManagerBuilder
{
    protected ?EntityManager $manager = null;

    /**
     * @var Connection|array<string, mixed>
     */
    protected Connection|array $connection = [];

    protected string $proxiesNamespace = 'DoctrineRDBMSORMProxy';

    protected ?RepositoryFactory $repositoryFactory = null;

    /**
     * @var class-string<EntityRepository<object>>
     */
    protected string $defaultRepositoryClass = EntityRepository::class;

    /**
     * @var CacheItemPoolInterface<mixed>|null
     */
    protected ?CacheItemPoolInterface $queryCache = null;

    /**
     * @var CacheItemPoolInterface<mixed>|null
     */
    protected ?CacheItemPoolInterface $resultCache = null;

    /**
     * @var CacheItemPoolInterface<mixed>|null
     */
    protected ?CacheItemPoolInterface $hydrationCache = null;

    protected ?NamingStrategy $namingStrategy = null;

    protected ?QuoteStrategy $quoteStrategy = null;

    protected ?CacheConfiguration $secondLevelCache = null;

    protected ?MiddlewareInterface $sqlLoggerMiddleware = null;

    /**
     * @var array<string, class-string<FunctionNode>|callable(string): FunctionNode>
     */
    protected array $customStringFunctions = [];

    /**
     * @var array<string, class-string<FunctionNode>|callable(string): FunctionNode>
     */
    protected array $customNumericFunctions = [];

    /**
     * @var array<string, class-string<FunctionNode>|callable(string): FunctionNode>
     */
    protected array $customDateTimeFunctions = [];

    /**
     * @var array<string, class-string<Type>>
     */
    protected array $customTypes = [];

    /**
     * @var array<string, string>
     */
    protected array $customMappingTypes = [];

    /**
     * @var array<string, class-string<SQLFilter>>
     */
    protected array $customFilters = [];

    public function getManager(bool $force = false): EntityManager
    {
        if ($force === true) {
            $this->wipe();
        }

        if ($this->manager === null) {
            $this->manager = $this->buildManager();
        }

        return $this->manager;
    }

    protected function wipe(): void
    {
        $this->manager = null;
        $this->mappingDriver = null;
        $this->metadataCache = null;
        $this->eventManager = null;
        $this->queryCache = null;
        $this->resultCache = null;
        $this->hydrationCache = null;
        $this->namingStrategy = null;
        $this->quoteStrategy = null;
        $this->sqlLoggerMiddleware = null;
    }

    /**
     * @throws ORMException
     * @throws InvalidArgumentException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    protected function buildManager(): EntityManager
    {
        $config = new Configuration();

        $this->setUpGeneralConfigurations($config);
        $this->setUpSpecificConfigurations($config);

        $eventManager = $this->eventManager ?? new EventManager();
        foreach ($this->eventSubscribers as $eventSubscriber) {
            $eventManager->addEventSubscriber($eventSubscriber);
        }

        $entityManager = new EntityManager($this->getConnection($config, $eventManager), $config);

        if (\count($this->customTypes) !== 0) {
            $platform = $entityManager->getConnection()
                ->getDatabasePlatform();

            foreach ($this->customTypes as $type => $class) {
                if (Type::hasType($type)) {
                    Type::overrideType($type, $class);
                } else {
                    Type::addType($type, $class);
                }

                $platform->registerDoctrineTypeMapping($type, $this->customMappingTypes[$type] ?? $type);
            }
        }

        return $entityManager;
    }

    protected function setUpGeneralConfigurations(Configuration $config): void
    {
        $config->setMetadataDriverImpl($this->getMetadataMappingDriver());
        $config->setProxyDir($this->proxiesPath ?? sys_get_temp_dir());
        $config->setProxyNamespace($this->proxiesNamespace);
        $config->setAutoGenerateProxyClasses($this->proxiesAutoGeneration);
        if ($this->repositoryFactory !== null) {
            $config->setRepositoryFactory($this->repositoryFactory);
        }
        $config->setDefaultRepositoryClassName($this->defaultRepositoryClass);
        $config->setMetadataCache($this->metadataCache ?? $this->getInMemoryDummyCache());
    }

    protected function setUpSpecificConfigurations(Configuration $config): void
    {
        $config->setQueryCache($this->queryCache ?? $this->getInMemoryDummyCache());
        $config->setResultCache($this->resultCache ?? $this->getInMemoryDummyCache());
        $config->setHydrationCache($this->hydrationCache ?? $this->getInMemoryDummyCache());
        $config->setNamingStrategy($this->namingStrategy ?? new UnderscoreNamingStrategy(\CASE_LOWER, true));
        $config->setQuoteStrategy($this->quoteStrategy ?? new DefaultQuoteStrategy());

        if ($this->secondLevelCache !== null) {
            $config->setSecondLevelCacheEnabled();
            $config->setSecondLevelCacheConfiguration($this->secondLevelCache);
        }

        if ($this->sqlLoggerMiddleware !== null) {
            $config->setMiddlewares([$this->sqlLoggerMiddleware]);
        }
        $config->setCustomStringFunctions($this->customStringFunctions);
        $config->setCustomNumericFunctions($this->customNumericFunctions);
        $config->setCustomDatetimeFunctions($this->customDateTimeFunctions);

        foreach ($this->customFilters as $name => $filterClass) {
            $config->addFilter($name, $filterClass);
        }
    }

    /**
     * @param Connection|array<string, mixed> $connection
     */
    public function setConnection(Connection|array $connection): void
    {
        $this->connection = $connection;
    }

    public function setRepositoryFactory(RepositoryFactory $repositoryFactory): void
    {
        $this->repositoryFactory = $repositoryFactory;
    }

    /**
     * @param class-string<EntityRepository<object>> $defaultRepositoryClass
     *
     * @throws InvalidArgumentException
     */
    public function setDefaultRepositoryClass(string $defaultRepositoryClass): void
    {
        if (!class_exists($defaultRepositoryClass)
            || !is_a($defaultRepositoryClass, EntityRepository::class, true)
        ) {
            throw new InvalidArgumentException(
                sprintf('Repository class should be a "%s".', EntityRepository::class),
            );
        }

        $this->defaultRepositoryClass = $defaultRepositoryClass;
    }

    /**
     * @param CacheItemPoolInterface<mixed>|CacheProvider $queryCache
     */
    public function setQueryCache(CacheItemPoolInterface|CacheProvider $queryCache): void
    {
        if ($queryCache instanceof CacheProvider) {
            $queryCache = CacheAdapter::wrap($queryCache);
        }

        $this->queryCache = $queryCache;
    }

    /**
     * @param CacheItemPoolInterface<mixed>|CacheProvider $resultCache
     */
    public function setResultCache(CacheItemPoolInterface|CacheProvider $resultCache): void
    {
        if ($resultCache instanceof CacheProvider) {
            $resultCache = CacheAdapter::wrap($resultCache);
        }

        $this->resultCache = $resultCache;
    }

    /**
     * @param CacheItemPoolInterface<mixed>|CacheProvider $hydrationCache
     */
    public function setHydrationCache(CacheItemPoolInterface|CacheProvider $hydrationCache): void
    {
        if ($hydrationCache instanceof CacheProvider) {
            $hydrationCache = CacheAdapter::wrap($hydrationCache);
        }

        $this->hydrationCache = $hydrationCache;
    }

    public function setNamingStrategy(NamingStrategy $namingStrategy): void
    {
        $this->namingStrategy = $namingStrategy;
    }

    public function setQuoteStrategy(QuoteStrategy $quoteStrategy): void
    {
        $this->quoteStrategy = $quoteStrategy;
    }

    public function setSecondLevelCache(CacheConfiguration $secondLevelCache): void
    {
        $this->secondLevelCache = $secondLevelCache;
    }

    public function setSqlLogger(LoggerInterface $sqlLogger): void
    {
        $this->sqlLoggerMiddleware = new Middleware($sqlLogger);
    }

    /**
     * @param array<string, class-string<FunctionNode>|callable(string): FunctionNode> $stringFunctions
     */
    public function setCustomStringFunctions(array $stringFunctions): void
    {
        $this->customStringFunctions = $stringFunctions;
    }

    /**
     * @param array<string, class-string<FunctionNode>|callable(string): FunctionNode> $numericFunctions
     */
    public function setCustomNumericFunctions(array $numericFunctions): void
    {
        $this->customNumericFunctions = $numericFunctions;
    }

    /**
     * @param array<string, class-string<FunctionNode>|callable(string): FunctionNode> $dateTimeFunctions
     */
    public function setCustomDateTimeFunctions(array $dateTimeFunctions): void
    {
        $this->customDateTimeFunctions = $dateTimeFunctions;
    }

    /**
     * @param array<string, class-string<Type>> $types
     */
    public function setCustomTypes(array $types): void
    {
        $this->customTypes = $types;
    }

    /**
     * @param array<string, string> $mappingTypes
     */
    public function setCustomMappingTypes(array $mappingTypes): void
    {
        $this->customMappingTypes = $mappingTypes;
    }

    /**
     * @param array<string, class-string<SQLFilter>> $filters
     */
    public function setCustomFilters(array $filters): void
    {
        $this->customFilters = $filters;
    }

    /**
     * @throws ORMException
     * @throws InvalidArgumentException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     *
     * @return array<Command>
     */
    public function getConsoleCommands(): array
    {
        $entityManager = $this->getManager();

        $connectionProvider = new SingleConnectionProvider($entityManager->getConnection());
        $entityManagerProvider = new SingleManagerProvider($entityManager);

        $commands = [
            // DBAL Commands
            new ReservedWordsCommand($connectionProvider),
            new RunSqlCommand($connectionProvider),

            // ORM Commands
            new ConvertMappingCommand($entityManagerProvider),
            new EnsureProductionSettingsCommand($entityManagerProvider),
            new GenerateProxiesCommand($entityManagerProvider),
            new InfoCommand($entityManagerProvider),
            new MappingDescribeCommand($entityManagerProvider),
            new RunDqlCommand($entityManagerProvider),
            new ValidateSchemaCommand($entityManagerProvider),
            new CollectionRegionCommand($entityManagerProvider),
            new EntityRegionCommand($entityManagerProvider),
            new MetadataCommand($entityManagerProvider),
            new QueryCommand($entityManagerProvider),
            new QueryRegionCommand($entityManagerProvider),
            new ResultCommand($entityManagerProvider),
            new CreateCommand($entityManagerProvider),
            new DropCommand($entityManagerProvider),
            new UpdateCommand($entityManagerProvider),
        ];

        $helperSet = $this->getConsoleHelperSet($entityManager);
        $commandPrefix = (string) $this->getName();

        return array_map(
            static function (Command $command) use ($helperSet, $commandPrefix): Command {
                if ($commandPrefix !== '') {
                    $commandNames = array_map(
                        static fn(string $commandName): string
                            => (string) preg_replace('/^(dbal|orm):/', '$1-' . $commandPrefix . ':', $commandName),
                        array_merge([$command->getName()], $command->getAliases()),
                    );

                    $command->setName(array_shift($commandNames));
                    $command->setAliases($commandNames);
                }

                $command->setHelperSet($helperSet);

                return $command;
            },
            $commands,
        );
    }

    protected function getConsoleHelperSet(EntityManager $entityManager): HelperSet
    {
        return new HelperSet([
            'em' => new EntityManagerHelper($entityManager),
        ]);
    }

    protected function getConnection(Configuration $config, EventManager $eventManager): Connection
    {
        $connection = $this->connection;
        if (\is_array($connection)) {
            $connection = DriverManager::getConnection($connection, $config, $eventManager);

            $this->connection = $connection;
        }

        return $connection;
    }

    /**
     * @param list<string> $paths
     */
    protected function getAttributeMappingDriver(array $paths): AttributeDriver
    {
        return new AttributeDriver($paths);
    }

    /**
     * @param list<string> $paths
     */
    protected function getAnnotationMappingDriver(array $paths): AnnotationDriver
    {
        return new AnnotationDriver(new AnnotationReader(), $paths);
    }

    /**
     * @param list<string> $paths
     */
    protected function getXmlMappingDriver(array $paths, ?string $extension = null): XmlDriver
    {
        return new XmlDriver($paths, $extension ?? XmlDriver::DEFAULT_FILE_EXTENSION);
    }

    /**
     * @param list<string> $paths
     */
    protected function getYamlMappingDriver(array $paths, ?string $extension = null): YamlDriver
    {
        return new YamlDriver($paths, $extension ?? YamlDriver::DEFAULT_FILE_EXTENSION);
    }
}
