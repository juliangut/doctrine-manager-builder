<?php
/**
 * doctrine-manager-builder (https://github.com/juliangut/doctrine-manager-builder)
 * Doctrine2 managers builder
 *
 * @license BSD-3-Clause
 * @author Julián Gutiérrez <juliangut@gmail.com>
 */

namespace Jgut\Doctrine\ManagerBuilder;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Cache\Cache;
use Doctrine\Common\Proxy\AbstractProxyFactory;
use Doctrine\DBAL\Logging\SQLLogger;
use Doctrine\DBAL\Tools\Console\Helper\ConnectionHelper;
use Doctrine\DBAL\Types\Type;
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
use Doctrine\ORM\Tools\Console\Helper\EntityManagerHelper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\HelperSet;

/**
 * Doctrine RDBMS Entity Manager builder
 */
class RelationalBuilder extends AbstractManagerBuilder
{
    /**
     * Entity Manager.
     *
     * @var EntityManager
     */
    protected $manager;

    /**
     * Query cache driver.
     *
     * @var Cache
     */
    protected $queryCacheDriver;

    /**
     * Result cache driver.
     *
     * @var Cache
     */
    protected $resultCacheDriver;

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
            //'annotation_files' => [],
            //'annotation_namespaces' => [],
            //'annotation_autoloaders' => [],
            //'metadata_mapping' => [],
            //'proxies_path' => null,
            'proxies_namespace' => 'DoctrineRelationalORMProxy',
            'proxies_auto_generation' => AbstractProxyFactory::AUTOGENERATE_NEVER,
            //'cache_driver' => null,
            'cache_namespace' => 'dc2_rdbms_cache_',
            //'metadata_cache_driver' => null,
            'metadata_cache_namespace' => 'dc2_rdbms_metadata_cache_',
            //'query_cache_driver' => null,
            'query_cache_namespace' => 'dc2_rdbms_query_cache_',
            //'result_cache_driver' => null,
            'result_cache_namespace' => 'dc2_rdbms_result_cache_',
            'default_repository_class' => EntityRepository::class,
            //'event_manager' => null,
            //'naming_strategy' => null, // Doctrine\ORM\Mapping\UnderscoreNamingStrategy(CASE_LOWER)
            //'quote_strategy' => null, // Doctrine\ORM\Mapping\DefaultQuoteStrategy
            //'sql_logger' => null,
            //'custom_string_functions' => [],
            //'custom_numeric_functions' => [],
            //'custom_datetime_functions' => [],
            //'custom_types' => [],
        ];
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
    public function getManager($standalone = false, $force = false)
    {
        if ($force === true) {
            $this->wipe();
        }

        if (!$this->manager instanceof EntityManager) {
            $this->manager = $this->buildManager($standalone);
        }

        return $this->manager;
    }

    /**
     * {@inheritdoc}
     */
    protected function wipe()
    {
        parent::wipe();

        $this->manager = null;
        $this->queryCacheDriver = null;
        $this->resultCacheDriver = null;
        $this->namingStrategy = null;
        $this->quoteStrategy = null;
        $this->SQLLogger = null;
    }

    /**
     * Build new Doctrine entity manager.
     *
     * @param bool $standalone
     *
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     * @throws \UnexpectedValueException
     *
     * @return EntityManager
     */
    protected function buildManager($standalone = false)
    {
        $config = new Configuration();

        $this->setupAnnotationMetadata($standalone);
        $config->setMetadataDriverImpl($this->getMetadataMappingDriver());

        $config->setProxyDir($this->getProxiesPath());
        $config->setProxyNamespace($this->getProxiesNamespace());
        $config->setAutoGenerateProxyClasses($this->getProxiesAutoGeneration());

        $config->setMetadataCacheImpl($this->getMetadataCacheDriver());
        $config->setQueryCacheImpl($this->getQueryCacheDriver());
        $config->setResultCacheImpl($this->getResultCacheDriver());

        if ($this->getDefaultRepositoryClass() !== null) {
            $config->setDefaultRepositoryClassName($this->getDefaultRepositoryClass());
        }

        $config->setNamingStrategy($this->getNamingStrategy());
        $config->setQuoteStrategy($this->getQuoteStrategy());

        $config->setSQLLogger($this->getSQLLogger());
        $config->setCustomStringFunctions($this->getCustomStringFunctions());
        $config->setCustomNumericFunctions($this->getCustomNumericFunctions());
        $config->setCustomDatetimeFunctions($this->getCustomDateTimeFunctions());

        $entityManager = EntityManager::create($this->getOption('connection'), $config, $this->getEventManager());

        $platform = $entityManager->getConnection()->getDatabasePlatform();
        foreach ($this->getCustomTypes() as $type => $class) {
            Type::addType($type, $class);
            $platform->registerDoctrineTypeMapping($type, $type);
        }

        return $entityManager;
    }

    /**
     * {@inheritdoc}
     *
     * @return AnnotationDriver
     */
    protected function getAnnotationMetadataDriver(array $paths)
    {
        return new AnnotationDriver(new AnnotationReader, $paths);
    }

    /**
     * {@inheritdoc}
     *
     * @return XmlDriver
     */
    protected function getXmlMetadataDriver(array $paths, $extension = null)
    {
        return new XmlDriver($paths, $extension ?: XmlDriver::DEFAULT_FILE_EXTENSION);
    }

    /**
     * {@inheritdoc}
     *
     * @return YamlDriver
     */
    protected function getYamlMetadataDriver(array $paths, $extension = null)
    {
        return new YamlDriver($paths, $extension ?: YamlDriver::DEFAULT_FILE_EXTENSION);
    }

    /**
     * Retrieve query cache driver.
     *
     * @throws \InvalidArgumentException
     *
     * @return Cache
     */
    public function getQueryCacheDriver()
    {
        if (!$this->queryCacheDriver instanceof Cache) {
            $queryCacheDriver = $this->getOption('query_cache_driver');

            if (!$queryCacheDriver instanceof Cache) {
                $queryCacheDriver = $this->getCacheDriver();
            }

            if ($queryCacheDriver->getNamespace() === '') {
                $queryCacheDriver->setNamespace($this->getQueryCacheNamespace());
            }

            $this->queryCacheDriver = $queryCacheDriver;
        }

        return $this->queryCacheDriver;
    }

    /**
     * Set query cache driver.
     *
     * @param Cache $queryCacheDriver
     */
    public function setQueryCacheDriver(Cache $queryCacheDriver)
    {
        $this->queryCacheDriver = $queryCacheDriver;
    }

    /**
     * Retrieve query cache namespace.
     *
     * @return string
     */
    protected function getQueryCacheNamespace()
    {
        return (string) $this->getOption('query_cache_namespace', $this->getCacheDriverNamespace());
    }

    /**
     * Retrieve result cache driver.
     *
     * @throws \InvalidArgumentException
     *
     * @return Cache
     */
    public function getResultCacheDriver()
    {
        if (!$this->resultCacheDriver instanceof Cache) {
            $resultCacheDriver = $this->getOption('result_cache_driver');

            if (!$resultCacheDriver instanceof Cache) {
                $resultCacheDriver = $this->getCacheDriver();
            }

            if ($resultCacheDriver->getNamespace() === '') {
                $resultCacheDriver->setNamespace($this->getResultCacheNamespace());
            }

            $this->resultCacheDriver = $resultCacheDriver;
        }

        return $this->resultCacheDriver;
    }

    /**
     * Set result cache driver.
     *
     * @param Cache $resultCacheDriver
     */
    public function setResultCacheDriver(Cache $resultCacheDriver)
    {
        $this->resultCacheDriver = $resultCacheDriver;
    }

    /**
     * Retrieve result cache namespace.
     *
     * @return string
     */
    protected function getResultCacheNamespace()
    {
        return (string) $this->getOption('result_cache_namespace', $this->getCacheDriverNamespace());
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
            new \Doctrine\ORM\Tools\Console\Command\MappingDescribeCommand(),
        ];
        $commandPrefix = (string) $this->getName();

        // Rename commands
        return array_map(
            function (Command $command) use ($commandPrefix) {
                if ($commandPrefix !== '') {
                    $command->setName(preg_replace('/^(dbal|orm):/', $commandPrefix . ':$1:', $command->getName()));

                    $aliases = [];
                    foreach ($command->getAliases() as $alias) {
                        $aliases[] = preg_replace('/^(dbal|orm):/', $commandPrefix . ':$1:', $alias);
                    }
                    $command->setAliases($aliases);
                }

                return $command;
            },
            $commands
        );
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     * @throws \UnexpectedValueException
     */
    public function getConsoleHelperSet()
    {
        $entityManager = $this->getManager();

        return new HelperSet([
            'db' => new ConnectionHelper($entityManager->getConnection()),
            'em' => new EntityManagerHelper($entityManager),
        ]);
    }
}
