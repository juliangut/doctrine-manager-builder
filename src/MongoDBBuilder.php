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
use Doctrine\Common\Proxy\AbstractProxyFactory;
use Doctrine\MongoDB\Connection;
use Doctrine\ODM\MongoDB\Configuration;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\DocumentRepository;
use Doctrine\ODM\MongoDB\Mapping\Driver\AnnotationDriver;
use Doctrine\ODM\MongoDB\Mapping\Driver\XmlDriver;
use Doctrine\ODM\MongoDB\Mapping\Driver\YamlDriver;
use Doctrine\ODM\MongoDB\Tools\Console\Helper\DocumentManagerHelper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\HelperSet;

/**
 * Doctrine MongoDB Document Manager builder
 */
class MongoDBBuilder extends AbstractManagerBuilder
{
    /**
     * Document Manager.
     *
     * @var DocumentManager
     */
    protected $manager;

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
            //'connection' => null, // Array or \Doctrine\MongoDB\Connection
            //'annotation_files' => [],
            //'annotation_namespaces' => [],
            //'annotation_autoloaders' => [],
            //'metadata_mapping' => [],
            //'proxies_path' => null,
            'proxies_namespace' => 'DoctrineMongoDBODMProxy',
            'proxies_auto_generation' => AbstractProxyFactory::AUTOGENERATE_NEVER,
            //'cache_driver' => null,
            'cache_namespace' => 'dc2_mongodb_cache_',
            //'metadata_cache_driver' => null,
            'metadata_cache_namespace' => 'dc2_mongodb_metadata_cache_',
            'default_repository_class' => DocumentRepository::class,
            //'event_manager' => null,
            //'hydrators_path' => null,
            'hydrators_namespace' => 'DoctrineMongoDBODMHydrator',
            'hydrators_auto_generation' => AbstractProxyFactory::AUTOGENERATE_NEVER,
            //'persistent_collections_path' => '',
            'persistent_collections_namespace' => 'DoctrineMongoDBODMPersistentCollection',
            'auto_generate_persistent_collections' => AbstractProxyFactory::AUTOGENERATE_NEVER,
            //'default_database' => null,
            //'logger_callable' => null,
        ];
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
    public function getManager($standalone = false, $force = false)
    {
        if ($force === true) {
            $this->wipe();
        }

        if (!$this->manager instanceof DocumentManager) {
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
        $this->loggerCallable = null;
    }

    /**
     * Build Doctrine MongoDB Document Manager.
     *
     * @param bool $standalone
     *
     * @throws \Doctrine\ODM\MongoDB\MongoDBException
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     * @throws \UnexpectedValueException
     *
     * @return DocumentManager
     */
    protected function buildManager($standalone = false)
    {
        $config = new Configuration;

        $this->setupAnnotationMetadata($standalone);
        $config->setMetadataDriverImpl($this->getMetadataMappingDriver());

        $config->setProxyDir($this->getProxiesPath());
        $config->setProxyNamespace($this->getProxiesNamespace());
        $config->setAutoGenerateProxyClasses($this->getProxiesAutoGeneration());

        $config->setHydratorDir($this->getHydratorsPath());
        $config->setHydratorNamespace($this->getHydratorsNamespace());
        $config->setAutoGenerateHydratorClasses($this->getHydratorsAutoGeneration());

        $config->setPersistentCollectionDir($this->getPersistentCollectionPath());
        $config->setPersistentCollectionNamespace($this->getPersistentCollectionNamespace());
        $config->setAutoGeneratePersistentCollectionClasses($this->getAutoGeneratePersistentCollection());

        $config->setMetadataCacheImpl($this->getMetadataCacheDriver());

        if ($this->getDefaultRepositoryClass() !== null) {
            $config->setDefaultRepositoryClassName($this->getDefaultRepositoryClass());
        }

        if ($this->getDefaultDatabase() !== null) {
            $config->setDefaultDB($this->getDefaultDatabase());
        }

        if ($this->getLoggerCallable() !== null) {
            $config->setLoggerCallable($this->getLoggerCallable());
        }

        return DocumentManager::create($this->getConnection($config), $config, $this->getEventManager());
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
            case (is_array($connection)):
                $connection = new Connection(
                    array_key_exists('server', $connection) ? $connection['server'] : null,
                    array_key_exists('options', $connection) ? $connection['options'] : [],
                    $config,
                    $this->getEventManager()
                );
                break;

            case ($connection instanceof Connection):
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
        return (int) $this->getOption('auto_generate_persistent_collections');
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
        $commandPrefix = (string) $this->getName();

        if ($commandPrefix !== '') {
            $commands = array_map(
                function (Command $command) use ($commandPrefix) {
                    $command->setName(preg_replace('/^odm:/', $commandPrefix . ':odm:', $command->getName()));

                    $aliases = [];
                    // @codeCoverageIgnoreStart
                    foreach ($command->getAliases() as $alias) {
                        $aliases[] = preg_replace('/^odm:/', $commandPrefix . ':$1:', $alias);
                    }
                    // @codeCoverageIgnoreEnd
                    $command->setAliases($aliases);

                    return $command;
                },
                $commands
            );
        }

        return $commands;
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Doctrine\ODM\MongoDB\MongoDBException
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     * @throws \UnexpectedValueException
     */
    public function getConsoleHelperSet()
    {
        return new HelperSet([
            'dm' => new DocumentManagerHelper($this->getManager()),
        ]);
    }
}
