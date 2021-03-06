<?php

/*
 * doctrine-manager-builder (https://github.com/juliangut/doctrine-manager-builder).
 * Doctrine2 managers builder.
 *
 * @license BSD-3-Clause
 * @link https://github.com/juliangut/doctrine-manager-builder
 * @author Julián Gutiérrez <juliangut@gmail.com>
 */

namespace Jgut\Doctrine\ManagerBuilder;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Cache\CacheProvider;
use Doctrine\DBAL\Logging\SQLLogger;
use Doctrine\DBAL\Tools\Console\Helper\ConnectionHelper;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Cache\CacheConfiguration;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\DefaultQuoteStrategy;
use Doctrine\ORM\Mapping\Driver\AnnotationDriver;
use Doctrine\ORM\Mapping\Driver\XmlDriver;
use Doctrine\ORM\Mapping\Driver\YamlDriver;
use Doctrine\ORM\Mapping\NamingStrategy;
use Doctrine\ORM\Mapping\QuoteStrategy;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\Repository\RepositoryFactory;
use Doctrine\ORM\Tools\Console\Helper\EntityManagerHelper;
use Doctrine\ORM\Version;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\HelperSet;

/**
 * Doctrine RDBMS Entity Manager builder.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class RelationalBuilder extends AbstractManagerBuilder
{
    /**
     * Query cache driver.
     *
     * @var CacheProvider
     */
    protected $queryCacheDriver;

    /**
     * Result cache driver.
     *
     * @var CacheProvider
     */
    protected $resultCacheDriver;

    /**
     * Hydrator cache driver.
     *
     * @var CacheProvider
     */
    protected $hydratorCacheDriver;

    /**
     * Naming strategy.
     *
     * @var NamingStrategy
     */
    protected $namingStrategy;

    /**
     * Quote strategy.
     *
     * @var QuoteStrategy
     */
    protected $quoteStrategy;

    /**
     * Second level cache configuration.
     *
     * @var CacheConfiguration
     */
    protected $secondCacheConfig;

    /**
     * SQL logger.
     *
     * @var SQLLogger
     */
    protected $SQLLogger;

    /**
     * {@inheritdoc}
     */
    protected function getDefaultOptions()
    {
        return [
            'connection' => [], // Array or \Doctrine\DBAL\Connection
            'proxies_namespace' => 'DoctrineRDBMSORMProxy',
            'metadata_cache_namespace' => 'DoctrineRDBMSORMMetadataCache',
            'query_cache_namespace' => 'DoctrineRDBMSORMQueryCache',
            'result_cache_namespace' => 'DoctrineRDBMSORMResultCache',
            'hydrator_cache_namespace' => 'DoctrineRDBMSORMHydratorCache',
            'default_repository_class' => EntityRepository::class,
            'second_level_cache_enable' => false,
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function wipe()
    {
        $this->manager = null;
        $this->mappingDriver = null;
        $this->metadataCacheDriver = null;
        $this->eventManager = null;
        $this->queryCacheDriver = null;
        $this->resultCacheDriver = null;
        $this->namingStrategy = null;
        $this->quoteStrategy = null;
        $this->SQLLogger = null;
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     * @throws \UnexpectedValueException
     *
     * @return EntityManager
     */
    protected function buildManager()
    {
        $config = new Configuration();

        $this->setUpGeneralConfigurations($config);
        $this->setUpSpecificConfigurations($config);

        $eventManager = $this->getEventManager();
        if ($this->getEventSubscribers() !== null) {
            /* @var array $eventSubscribers */
            $eventSubscribers = $this->getEventSubscribers();

            foreach ($eventSubscribers as $eventSubscriber) {
                $eventManager->addEventSubscriber($eventSubscriber);
            }
        }

        $entityManager = EntityManager::create($this->getOption('connection'), $config, $eventManager);

        $customMappingTypes = $this->getCustomMappingTypes();

        $platform = $entityManager->getConnection()->getDatabasePlatform();
        foreach ($this->getCustomTypes() as $type => $class) {
            if (Type::hasType($type)) {
                Type::overrideType($type, $class);
            } else {
                Type::addType($type, $class);
            }

            $platform->registerDoctrineTypeMapping(
                $type,
                array_key_exists($type, $customMappingTypes) ? $customMappingTypes[$type] : $type
            );
        }

        return $entityManager;
    }

    /**
     * Set up general manager configurations.
     *
     * @param Configuration $config
     */
    protected function setUpGeneralConfigurations(Configuration $config)
    {
        $this->setupAnnotationMetadata();
        $config->setMetadataDriverImpl($this->getMetadataMappingDriver());

        $config->setProxyDir($this->getProxiesPath());
        $config->setProxyNamespace($this->getProxiesNamespace());
        $config->setAutoGenerateProxyClasses($this->getProxiesAutoGeneration());

        if ($this->getRepositoryFactory() !== null) {
            $config->setRepositoryFactory($this->getRepositoryFactory());
        }

        if ($this->getDefaultRepositoryClass() !== null) {
            $config->setDefaultRepositoryClassName($this->getDefaultRepositoryClass());
        }

        $config->setMetadataCacheImpl($this->getMetadataCacheDriver());
    }

    /**
     * Set up manager specific configurations.
     *
     * @param Configuration $config
     */
    protected function setUpSpecificConfigurations(Configuration $config)
    {
        $config->setQueryCacheImpl($this->getQueryCacheDriver());
        $config->setResultCacheImpl($this->getResultCacheDriver());
        $config->setHydrationCacheImpl($this->getHydratorCacheDriver());

        $config->setNamingStrategy($this->getNamingStrategy());
        $config->setQuoteStrategy($this->getQuoteStrategy());

        if ($this->getSecondLevelCacheConfiguration() !== null) {
            $config->setSecondLevelCacheEnabled(true);
            $config->setSecondLevelCacheConfiguration($this->getSecondLevelCacheConfiguration());
        }

        $config->setSQLLogger($this->getSQLLogger());
        $config->setCustomStringFunctions($this->getCustomStringFunctions());
        $config->setCustomNumericFunctions($this->getCustomNumericFunctions());
        $config->setCustomDatetimeFunctions($this->getCustomDateTimeFunctions());

        foreach ($this->getCustomFilters() as $name => $filterClass) {
            $config->addFilter($name, $filterClass);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function getAnnotationMappingDriver(array $paths)
    {
        return new AnnotationDriver(new AnnotationReader, $paths);
    }

    /**
     * {@inheritdoc}
     */
    protected function getXmlMappingDriver(array $paths, $extension = null)
    {
        $extension = $extension ?: XmlDriver::DEFAULT_FILE_EXTENSION;

        return new XmlDriver($paths, $extension);
    }

    /**
     * {@inheritdoc}
     */
    protected function getYamlMappingDriver(array $paths, $extension = null)
    {
        $extension = $extension ?: YamlDriver::DEFAULT_FILE_EXTENSION;

        return new YamlDriver($paths, $extension);
    }

    /**
     * {@inheritdoc}
     *
     * @throws \InvalidArgumentException
     *
     * @return RepositoryFactory|null
     */
    protected function getRepositoryFactory()
    {
        if (!array_key_exists('repository_factory', $this->options)) {
            return;
        }

        $repositoryFactory = $this->options['repository_factory'];

        if (!$repositoryFactory instanceof RepositoryFactory) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid factory class "%s". It must be a Doctrine\ORM\Repository\RepositoryFactory.',
                get_class($repositoryFactory)
            ));
        }

        return $repositoryFactory;
    }

    /**
     * Retrieve query cache driver.
     *
     * @throws \InvalidArgumentException
     *
     * @return CacheProvider
     */
    public function getQueryCacheDriver()
    {
        if (!$this->queryCacheDriver instanceof CacheProvider) {
            $this->queryCacheDriver = $this->getCacheDriver(
                (string) $this->getOption('query_cache_namespace'),
                $this->getOption('query_cache_driver')
            );
        }

        return $this->queryCacheDriver;
    }

    /**
     * Set query cache driver.
     *
     * @param CacheProvider $queryCacheDriver
     */
    public function setQueryCacheDriver(CacheProvider $queryCacheDriver)
    {
        $this->queryCacheDriver = $queryCacheDriver;
    }

    /**
     * Retrieve result cache driver.
     *
     * @throws \InvalidArgumentException
     *
     * @return CacheProvider
     */
    public function getResultCacheDriver()
    {
        if (!$this->resultCacheDriver instanceof CacheProvider) {
            $this->resultCacheDriver = $this->getCacheDriver(
                (string) $this->getOption('result_cache_namespace'),
                $this->getOption('result_cache_driver')
            );
        }

        return $this->resultCacheDriver;
    }

    /**
     * Set result cache driver.
     *
     * @param CacheProvider $resultCacheDriver
     */
    public function setResultCacheDriver(CacheProvider $resultCacheDriver)
    {
        $this->resultCacheDriver = $resultCacheDriver;
    }

    /**
     * Retrieve hydrator cache driver.
     *
     * @throws \InvalidArgumentException
     *
     * @return CacheProvider
     */
    public function getHydratorCacheDriver()
    {
        if (!$this->hydratorCacheDriver instanceof CacheProvider) {
            $this->hydratorCacheDriver = $this->getCacheDriver(
                (string) $this->getOption('hydrator_cache_namespace'),
                $this->getOption('hydrator_cache_driver')
            );
        }

        return $this->hydratorCacheDriver;
    }

    /**
     * Set hydrator cache driver.
     *
     * @param CacheProvider $hydratorCacheDriver
     */
    public function setHydratorCacheDriver(CacheProvider $hydratorCacheDriver)
    {
        $this->hydratorCacheDriver = $hydratorCacheDriver;
    }

    /**
     * Get cache driver.
     *
     * @param string             $cacheNamespace
     * @param CacheProvider|null $cacheDriver
     *
     * @return CacheProvider
     */
    protected function getCacheDriver($cacheNamespace, CacheProvider $cacheDriver = null)
    {
        if (!$cacheDriver instanceof CacheProvider) {
            $cacheDriver = clone $this->getMetadataCacheDriver();
            $cacheDriver->setNamespace($cacheNamespace);
        }

        if ($cacheDriver->getNamespace() === '') {
            $cacheDriver->setNamespace($cacheNamespace);
        }

        return $cacheDriver;
    }

    /**
     * Retrieve naming strategy.
     *
     * @return NamingStrategy
     */
    protected function getNamingStrategy()
    {
        if (!$this->namingStrategy instanceof NamingStrategy) {
            $namingStrategy = $this->getOption('naming_strategy');

            if (!$namingStrategy instanceof NamingStrategy) {
                $namingStrategy = new UnderscoreNamingStrategy(CASE_LOWER);
            }

            $this->namingStrategy = $namingStrategy;
        }

        return $this->namingStrategy;
    }

    /**
     * Retrieve quote strategy.
     *
     * @throws \InvalidArgumentException
     *
     * @return QuoteStrategy
     */
    protected function getQuoteStrategy()
    {
        if (!$this->quoteStrategy instanceof QuoteStrategy) {
            $quoteStrategy = $this->getOption('quote_strategy');

            if (!$quoteStrategy instanceof QuoteStrategy) {
                $quoteStrategy = new DefaultQuoteStrategy;
            }

            $this->quoteStrategy = $quoteStrategy;
        }

        return $this->quoteStrategy;
    }

    /**
     * Retrieve second level cache configuration.
     *
     * @return CacheConfiguration|null
     */
    protected function getSecondLevelCacheConfiguration()
    {
        if (!$this->secondCacheConfig instanceof CacheConfiguration) {
            $secondCacheConfig = $this->getOption('second_level_cache_configuration');

            if ($secondCacheConfig instanceof CacheConfiguration) {
                $this->secondCacheConfig = $secondCacheConfig;
            }
        }

        return $this->secondCacheConfig;
    }

    /**
     * Retrieve SQL logger.
     *
     * @return SQLLogger|null
     */
    protected function getSQLLogger()
    {
        if (!$this->SQLLogger instanceof SQLLogger) {
            $sqlLogger = $this->getOption('sql_logger');

            if ($sqlLogger instanceof SQLLogger) {
                $this->SQLLogger = $sqlLogger;
            }
        }

        return $this->SQLLogger;
    }

    /**
     * Retrieve custom DQL string functions.
     *
     * @return array
     */
    protected function getCustomStringFunctions()
    {
        $functions = (array) $this->getOption('custom_string_functions');

        return array_filter(
            $functions,
            function ($name) {
                return is_string($name);
            },
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * Retrieve custom DQL numeric functions.
     *
     * @return array
     */
    protected function getCustomNumericFunctions()
    {
        $functions = (array) $this->getOption('custom_numeric_functions');

        return array_filter(
            $functions,
            function ($name) {
                return is_string($name);
            },
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * Retrieve custom DQL date time functions.
     *
     * @return array
     */
    protected function getCustomDateTimeFunctions()
    {
        $functions = (array) $this->getOption('custom_datetime_functions');

        return array_filter(
            $functions,
            function ($name) {
                return is_string($name);
            },
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * Retrieve custom DBAL types.
     *
     * @return array
     */
    protected function getCustomTypes()
    {
        $types = (array) $this->getOption('custom_types');

        return array_filter(
            $types,
            function ($name) {
                return is_string($name);
            },
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * Retrieve custom DBAL mapping types.
     *
     * @return array
     */
    protected function getCustomMappingTypes()
    {
        $mappingTypes = (array) $this->getOption('custom_mapping_types');

        return array_filter(
            $mappingTypes,
            function ($name) {
                return is_string($name);
            },
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * Get custom registered filters.
     *
     * @return array
     */
    protected function getCustomFilters()
    {
        $filters = (array) $this->getOption('custom_filters');

        return array_filter(
            $filters,
            function ($name) {
                return is_string($name);
            },
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
     * @throws \Symfony\Component\Console\Exception\LogicException
     * @throws \UnexpectedValueException
     *
     * @return Command[]
     */
    public function getConsoleCommands()
    {
        $commands = [
            // DBAL
            new \Doctrine\DBAL\Tools\Console\Command\RunSqlCommand(),
            new \Doctrine\DBAL\Tools\Console\Command\ImportCommand(),

            // ORM
            new \Doctrine\ORM\Tools\Console\Command\ClearCache\MetadataCommand(),
            new \Doctrine\ORM\Tools\Console\Command\ClearCache\ResultCommand(),
            new \Doctrine\ORM\Tools\Console\Command\ClearCache\QueryCommand(),
            new \Doctrine\ORM\Tools\Console\Command\SchemaTool\CreateCommand(),
            new \Doctrine\ORM\Tools\Console\Command\SchemaTool\UpdateCommand(),
            new \Doctrine\ORM\Tools\Console\Command\SchemaTool\DropCommand(),
            new \Doctrine\ORM\Tools\Console\Command\EnsureProductionSettingsCommand(),
            new \Doctrine\ORM\Tools\Console\Command\ConvertDoctrine1SchemaCommand(),
            new \Doctrine\ORM\Tools\Console\Command\GenerateRepositoriesCommand(),
            new \Doctrine\ORM\Tools\Console\Command\GenerateEntitiesCommand(),
            new \Doctrine\ORM\Tools\Console\Command\GenerateProxiesCommand(),
            new \Doctrine\ORM\Tools\Console\Command\ConvertMappingCommand(),
            new \Doctrine\ORM\Tools\Console\Command\RunDqlCommand(),
            new \Doctrine\ORM\Tools\Console\Command\ValidateSchemaCommand(),
            new \Doctrine\ORM\Tools\Console\Command\InfoCommand(),
        ];

        if (Version::compare('2.5') <= 0) {
            $commands[] = new \Doctrine\ORM\Tools\Console\Command\MappingDescribeCommand();
        }

        $helperSet = $this->getConsoleHelperSet();
        $commandPrefix = (string) $this->getName();

        $commands = array_map(
            function (Command $command) use ($helperSet, $commandPrefix) {
                if ($commandPrefix !== '') {
                    $commandNames = array_map(
                        function ($commandName) use ($commandPrefix) {
                            return preg_replace('/^(dbal|orm):/', '$1:' . $commandPrefix . ':', $commandName);
                        },
                        array_merge([$command->getName()], $command->getAliases())
                    );

                    $command->setName(array_shift($commandNames));
                    $command->setAliases($commandNames);
                }

                $command->setHelperSet($helperSet);

                return $command;
            },
            $commands
        );

        return $commands;
    }

    /**
     * Get console helper set.
     *
     * @return \Symfony\Component\Console\Helper\HelperSet
     */
    protected function getConsoleHelperSet()
    {
        /* @var EntityManager $entityManager */
        $entityManager = $this->getManager();

        return new HelperSet([
            'db' => new ConnectionHelper($entityManager->getConnection()),
            'em' => new EntityManagerHelper($entityManager),
        ]);
    }
}
