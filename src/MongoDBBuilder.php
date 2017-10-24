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
use Doctrine\Common\Proxy\AbstractProxyFactory;
use Doctrine\MongoDB\Connection;
use Doctrine\ODM\MongoDB\Configuration;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\DocumentRepository;
use Doctrine\ODM\MongoDB\Mapping\Driver\AnnotationDriver;
use Doctrine\ODM\MongoDB\Mapping\Driver\XmlDriver;
use Doctrine\ODM\MongoDB\Mapping\Driver\YamlDriver;
use Doctrine\ODM\MongoDB\Repository\RepositoryFactory;
use Doctrine\ODM\MongoDB\Tools\Console\Helper\DocumentManagerHelper;
use Doctrine\ODM\MongoDB\Types\Type;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\HelperSet;

/**
 * Doctrine MongoDB Document Manager builder.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class MongoDBBuilder extends AbstractManagerBuilder
{
    /**
     * Logger callable.
     *
     * @var callable
     */
    protected $loggerCallable;

    /**
     * {@inheritdoc}
     */
    protected function getDefaultOptions()
    {
        return [
            'connection' => [], // Array or \Doctrine\MongoDB\Connection
            'proxies_namespace' => 'DoctrineMongoDBODMProxy',
            'metadata_cache_namespace' => 'DoctrineMongoDBODMMetadataCache',
            'default_repository_class' => DocumentRepository::class,
            'hydrators_namespace' => 'DoctrineMongoDBODMHydrator',
            'hydrators_auto_generation' => AbstractProxyFactory::AUTOGENERATE_NEVER,
            'persistent_collections_namespace' => 'DoctrineMongoDBODMPersistentCollection',
            'persistent_collections_auto_generation' => AbstractProxyFactory::AUTOGENERATE_NEVER,
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
        $this->loggerCallable = null;
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Doctrine\ODM\MongoDB\MongoDBException
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     * @throws \UnexpectedValueException
     *
     * @return DocumentManager
     */
    protected function buildManager()
    {
        $config = new Configuration;

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

        return DocumentManager::create($this->getConnection($config), $config, $eventManager);
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
        $config->setHydratorDir($this->getHydratorsPath());
        $config->setHydratorNamespace($this->getHydratorsNamespace());
        $config->setAutoGenerateHydratorClasses($this->getHydratorsAutoGeneration());

        $config->setPersistentCollectionDir($this->getPersistentCollectionPath());
        $config->setPersistentCollectionNamespace($this->getPersistentCollectionNamespace());
        $config->setAutoGeneratePersistentCollectionClasses($this->getAutoGeneratePersistentCollection());

        if ($this->getDefaultDatabase() !== null) {
            $config->setDefaultDB($this->getDefaultDatabase());
        }

        if ($this->getLoggerCallable() !== null) {
            $config->setLoggerCallable($this->getLoggerCallable());
        }

        foreach ($this->getCustomTypes() as $type => $class) {
            if (Type::hasType($type)) {
                Type::overrideType($type, $class);
            } else {
                Type::addType($type, $class);
            }
        }

        foreach ($this->getCustomFilters() as $name => $filterClass) {
            $config->addFilter($name, $filterClass);
        }
    }

    /**
     * Create MongoDB Connection.
     *
     * @param Configuration $config
     *
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     *
     * @return Connection
     */
    protected function getConnection(Configuration $config)
    {
        $connection = $this->getOption('connection');

        switch (true) {
            case is_array($connection):
                $connection = new Connection(
                    array_key_exists('server', $connection) ? $connection['server'] : null,
                    array_key_exists('options', $connection) ? $connection['options'] : [],
                    $config,
                    $this->getEventManager()
                );
                break;

            case $connection instanceof Connection:
                if ($connection->getEventManager() !== $this->getEventManager()) {
                    throw new \RuntimeException(
                        'Cannot use different EventManager instances for DocumentManager and Connection.'
                    );
                }
                break;

            default:
                throw new \InvalidArgumentException('Invalid argument: ' . $connection);
        }

        return $connection;
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
                'Invalid factory class "%s". It must be a Doctrine\ODM\MongoDB\Repository\RepositoryFactory.',
                get_class($repositoryFactory)
            ));
        }

        return $repositoryFactory;
    }

    /**
     * Retrieve hydrators path.
     *
     * @return string
     */
    protected function getHydratorsPath()
    {
        return (string) $this->getOption('hydrators_path', sys_get_temp_dir());
    }

    /**
     * Retrieve hydrators namespace.
     *
     * @return null|string
     */
    protected function getHydratorsNamespace()
    {
        $proxyNamespace = $this->getOption('hydrators_namespace');

        return is_string($proxyNamespace) ? $proxyNamespace : null;
    }

    /**
     * Retrieve hydrators generation strategy.
     *
     * @return int
     */
    protected function getHydratorsAutoGeneration()
    {
        return (int) $this->getOption('hydrators_auto_generation');
    }

    /**
     * Retrieve persistent collections path.
     *
     * @return string
     */
    protected function getPersistentCollectionPath()
    {
        return (string) $this->getOption('persistent_collections_path', sys_get_temp_dir());
    }

    /**
     * Retrieve persistent collections namespace.
     *
     * @return null|string
     */
    protected function getPersistentCollectionNamespace()
    {
        $collectionNamespace = $this->getOption('persistent_collections_namespace');

        return is_string($collectionNamespace) ? $collectionNamespace : null;
    }

    /**
     * Retrieve persistent collections generation strategy.
     *
     * @return int
     */
    protected function getAutoGeneratePersistentCollection()
    {
        return (int) $this->getOption('persistent_collections_auto_generation');
    }

    /**
     * Get default database.
     *
     * @return string|null
     */
    protected function getDefaultDatabase()
    {
        return $this->hasOption('default_database') ? (string) $this->getOption('default_database') : null;
    }

    /**
     * Retrieve logger callable.
     *
     * @return callable|null
     */
    protected function getLoggerCallable()
    {
        if (!is_callable($this->loggerCallable)) {
            $loggerCallable = $this->getOption('logger_callable');

            if (is_callable($loggerCallable)) {
                $this->loggerCallable = $loggerCallable;
            }
        }

        return $this->loggerCallable;
    }

    /**
     * Retrieve custom types.
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
     * @throws \Doctrine\ODM\MongoDB\MongoDBException
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
            // ODM
            new \Doctrine\ODM\MongoDB\Tools\Console\Command\GenerateDocumentsCommand,
            new \Doctrine\ODM\MongoDB\Tools\Console\Command\GenerateHydratorsCommand,
            new \Doctrine\ODM\MongoDB\Tools\Console\Command\GenerateProxiesCommand,
            new \Doctrine\ODM\MongoDB\Tools\Console\Command\GenerateRepositoriesCommand,
            new \Doctrine\ODM\MongoDB\Tools\Console\Command\QueryCommand,
            new \Doctrine\ODM\MongoDB\Tools\Console\Command\ClearCache\MetadataCommand,
            new \Doctrine\ODM\MongoDB\Tools\Console\Command\Schema\CreateCommand,
            new \Doctrine\ODM\MongoDB\Tools\Console\Command\Schema\DropCommand,
            new \Doctrine\ODM\MongoDB\Tools\Console\Command\Schema\UpdateCommand,
        ];

        $helperSet = $this->getConsoleHelperSet();
        $commandPrefix = (string) $this->getName();

        $commands = array_map(
            function (Command $command) use ($helperSet, $commandPrefix) {
                if ($commandPrefix !== '') {
                    $commandNames = array_map(
                        function ($commandName) use ($commandPrefix) {
                            return preg_replace('/^odm:/', 'odm:' . $commandPrefix . ':', $commandName);
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
        /* @var DocumentManager $documentManager */
        $documentManager = $this->getManager();

        return new HelperSet([
            'dm' => new DocumentManagerHelper($documentManager),
        ]);
    }
}
