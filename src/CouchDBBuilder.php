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
use Doctrine\CouchDB\Tools\Console\Helper\CouchDBHelper;
use Doctrine\ODM\CouchDB\Configuration;
use Doctrine\ODM\CouchDB\DocumentRepository;
use Doctrine\ODM\CouchDB\Mapping\Driver\AnnotationDriver;
use Doctrine\ODM\CouchDB\Mapping\Driver\XmlDriver;
use Doctrine\ODM\CouchDB\Mapping\Driver\YamlDriver;
use Jgut\Doctrine\ManagerBuilder\CouchDB\DocumentManager;
use Jgut\Doctrine\ManagerBuilder\CouchDB\Repository\RepositoryFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\HelperSet;

/**
 * Doctrine CouchDB Document Manager builder.
 */
class CouchDBBuilder extends AbstractManagerBuilder
{
    /**
     * {@inheritdoc}
     */
    protected function getDefaultOptions()
    {
        return [
            'connection' => [], // Array or \Doctrine\CouchDB\CouchDBClient
            'proxies_namespace' => 'DoctrineCouchDBODMProxy',
            'metadata_cache_namespace' => 'DoctrineCouchDBODMMetadataCache',
            'default_repository_class' => DocumentRepository::class,
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
    }

    /**
     * {@inheritdoc}
     *
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

        $documentManager = DocumentManager::create($this->getOption('connection'), $config, $this->getEventManager());

        if ($this->getRepositoryFactory() !== null) {
            $documentManager->setRepositoryFactory($this->getRepositoryFactory());
        }

        if ($this->getDefaultRepositoryClass() !== null) {
            $documentManager->setDefaultRepositoryClassName($this->getDefaultRepositoryClass());
        }

        return $documentManager;
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

        $config->setMetadataCacheImpl($this->getMetadataCacheDriver());
    }

    /**
     * Set up manager specific configurations.
     *
     * @param Configuration $config
     */
    protected function setUpSpecificConfigurations(Configuration $config)
    {
        if ($this->getLuceneHandlerName() !== null) {
            $config->setLuceneHandlerName($this->getLuceneHandlerName());
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
                'Invalid factory class "%s". It must be a Jgut\Doctrine\ManagerBuilder\CouchDB\RepositoryFactory.',
                get_class($repositoryFactory)
            ));
        }

        return $repositoryFactory;
    }

    /**
     * Get Lucene handler name.
     *
     * @return string|null
     */
    protected function getLuceneHandlerName()
    {
        return $this->hasOption('lucene_handler_name') ? (string) $this->getOption('lucene_handler_name') : null;
    }

    /**
     * {@inheritdoc}
     *
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
            // CouchDB
            new \Doctrine\CouchDB\Tools\Console\Command\ReplicationStartCommand,
            new \Doctrine\CouchDB\Tools\Console\Command\ReplicationCancelCommand,
            new \Doctrine\CouchDB\Tools\Console\Command\ViewCleanupCommand,
            new \Doctrine\CouchDB\Tools\Console\Command\CompactDatabaseCommand,
            new \Doctrine\CouchDB\Tools\Console\Command\CompactViewCommand,
            new \Doctrine\CouchDB\Tools\Console\Command\MigrationCommand,

            // ODM
            new \Doctrine\ODM\CouchDB\Tools\Console\Command\GenerateProxiesCommand,
            new \Doctrine\ODM\CouchDB\Tools\Console\Command\UpdateDesignDocCommand,
        ];
        $commandPrefix = (string) $this->getName();

        if ($commandPrefix !== '') {
            $commands = array_map(
                function (Command $command) use ($commandPrefix) {
                    $commandNames = array_map(
                        function ($commandName) use ($commandPrefix) {
                            return preg_replace('/^couchdb:/', $commandPrefix . ':', $commandName);
                        },
                        array_merge([$command->getName()], $command->getAliases())
                    );

                    $command->setName(array_shift($commandNames));
                    $command->setAliases($commandNames);

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
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     * @throws \UnexpectedValueException
     */
    public function getConsoleHelperSet()
    {
        $documentManager = $this->getManager();

        return new HelperSet([
            'dm' => new CouchDBHelper($documentManager->getCouchDBClient(), $documentManager),
        ]);
    }
}
