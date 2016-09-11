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
use Doctrine\CouchDB\Tools\Console\Helper\CouchDBHelper;
use Doctrine\ODM\CouchDB\Configuration;
use Doctrine\ODM\CouchDB\DocumentManager;
use Doctrine\ODM\CouchDB\Mapping\Driver\AnnotationDriver;
use Doctrine\ODM\CouchDB\Mapping\Driver\XmlDriver;
use Doctrine\ODM\CouchDB\Mapping\Driver\YamlDriver;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\HelperSet;

/**
 * Doctrine CouchDB Document Manager builder
 */
class CouchDBBuilder extends AbstractManagerBuilder
{
    /**
     * Document Manager.
     *
     * @var DocumentManager
     */
    protected $manager;

    /**
     * {@inheritdoc}
     */
    protected function getDefaultOptions()
    {
        return [
            'connection' => [], // Array or \Doctrine\CouchDB\CouchDBClient
            //'annotation_files' => [],
            //'annotation_namespaces' => [],
            //'annotation_autoloaders' => [],
            //'metadata_mapping' => [],
            //'proxies_path' => null,
            'proxies_namespace' => 'DoctrineCouchDBODMProxy',
            'proxies_auto_generation' => AbstractProxyFactory::AUTOGENERATE_NEVER,
            //'cache_driver' => null,
            'cache_namespace' => 'dc2_couchdb_cache_',
            //'metadata_cache_driver' => null,
            'metadata_cache_namespace' => 'dc2_couchdb_metadata_cache_',
            //'event_manager' => null,
            //'lucene_handler_name' => null,
        ];
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
    }

    /**
     * Build Doctrine CouchDB Document Manager.
     *
     * @param bool $standalone
     *
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

        $config->setMetadataCacheImpl($this->getMetadataCacheDriver());

        if ($this->getLuceneHandlerName() !== null) {
            $config->setLuceneHandlerName($this->getLuceneHandlerName());
        }

        return DocumentManager::create($this->getOption('connection'), $config, $this->getEventManager());
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

        // Rename commands
        return array_map(
            function (Command $command) use ($commandPrefix) {
                if ($commandPrefix !== '') {
                    $command->setName(preg_replace('/^couchdb:/', $commandPrefix . ':', $command->getName()));

                    $aliases = [];
                    // @codeCoverageIgnoreStart
                    foreach ($command->getAliases() as $alias) {
                        $aliases[] = preg_replace('/^couchdb:/', $commandPrefix . ':$1:', $alias);
                    }
                    // @codeCoverageIgnoreEnd
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
