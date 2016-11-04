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

use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\Cache\CacheProvider;
use Doctrine\Common\EventManager;
use Doctrine\Common\Persistence\Mapping\Driver\MappingDriver;
use Doctrine\Common\Persistence\Mapping\Driver\MappingDriverChain;
use Doctrine\Common\Persistence\Mapping\Driver\PHPDriver;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Common\Proxy\AbstractProxyFactory;
use Jgut\Doctrine\ManagerBuilder\Util\CacheBuilder;
use Jgut\Doctrine\ManagerBuilder\Util\OptionsTrait;

/**
 * Abstract Doctrine Manager builder.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
abstract class AbstractManagerBuilder implements ManagerBuilder
{
    use OptionsTrait;

    /**
     * Manager builder's common default options.
     *
     * @var array
     */
    private $defaultOptions = [
        'proxies_auto_generation' => AbstractProxyFactory::AUTOGENERATE_NEVER,
    ];

    /**
     * Builder name.
     *
     * @var
     */
    protected $name;

    /**
     * Object Manager.
     *
     * @var ObjectManager
     */
    protected $manager;

    /**
     * Metadata mapping driver.
     *
     * @var MappingDriverChain
     */
    protected $mappingDriver;

    /**
     * Metadata cache driver.
     *
     * @var CacheProvider
     */
    protected $metadataCacheDriver;

    /**
     * Event manager.
     *
     * @var EventManager
     */
    protected $eventManager;

    /**
     * ManagerBuilder constructor.
     *
     * @param array       $options
     * @param string|null $name
     */
    public function __construct(array $options = [], $name = null)
    {
        $this->setOptions(array_merge($this->defaultOptions, $this->getDefaultOptions(), $options));
        $this->setName($name);
    }

    /**
     * Get manager builder's default options.
     *
     * @return array
     */
    abstract protected function getDefaultOptions();

    /**
     * {@inheritdoc}
     *
     * @return ObjectManager
     */
    public function getManager($force = false)
    {
        if ($force === true) {
            $this->wipe();
        }

        if (!$this->manager instanceof ObjectManager) {
            $this->manager = $this->buildManager();
        }

        return $this->manager;
    }

    /**
     * Unset created objects for rebuild.
     */
    abstract protected function wipe();

    /**
     * Build new Doctrine object manager.
     *
     * @return ObjectManager
     */
    abstract protected function buildManager();

    /**
     * {@inheritdoc}
     *
     * @return string|null
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * {@inheritdoc}
     *
     * @return $this
     */
    public function setName($name = null)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Set up annotation metadata.
     *
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    protected function setupAnnotationMetadata()
    {
        $annotationFiles = (array) $this->getOption('annotation_files');
        array_walk(
            $annotationFiles,
            function ($file) {
                if (!file_exists($file)) {
                    throw new \RuntimeException(sprintf('"%s" file does not exist', $file));
                }

                AnnotationRegistry::registerFile($file);
            }
        );

        AnnotationRegistry::registerAutoloadNamespaces($this->getAnnotationNamespaces());

        $annotationLoaders = (array) $this->getOption('annotation_autoloaders');
        array_walk(
            $annotationLoaders,
            function ($autoLoader) {
                AnnotationRegistry::registerLoader($autoLoader);
            }
        );
    }

    /**
     * Retrieve annotation namespaces.
     *
     * @return array
     */
    protected function getAnnotationNamespaces()
    {
        $namespaces = (array) $this->getOption('annotation_namespaces');

        return array_filter(
            $namespaces,
            function ($namespace) {
                return is_string($namespace);
            },
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * {@inheritdoc}
     *
     * @throws \RuntimeException
     * @throws \UnexpectedValueException
     */
    public function getMetadataMappingDriver()
    {
        if (!$this->mappingDriver instanceof MappingDriverChain) {
            $metadataDriverChain = new MappingDriverChain;

            $this->parseMetadataMapping($metadataDriverChain);

            if ($metadataDriverChain->getDefaultDriver() === null && count($metadataDriverChain->getDrivers()) === 0) {
                throw new \RuntimeException('No metadata mapping defined');
            }

            $this->mappingDriver = $metadataDriverChain;
        }

        return $this->mappingDriver;
    }

    /**
     * Parse metadata mapping configuration.
     *
     * @param MappingDriverChain $metadataDriverChain
     *
     * @throws \RuntimeException
     */
    protected function parseMetadataMapping(MappingDriverChain $metadataDriverChain)
    {
        foreach ((array) $this->getOption('metadata_mapping') as $metadataMapping) {
            if (!is_array($metadataMapping)) {
                $metadataMapping = ['driver' => $metadataMapping];
            }

            if (!array_key_exists('namespace', $metadataMapping) && $metadataDriverChain->getDefaultDriver() !== null) {
                throw new \RuntimeException(
                    'Only one default metadata mapping driver allowed, a namespace must be defined'
                );
            }

            $mappingDriver = $this->getMappingDriver($metadataMapping);

            if (array_key_exists('namespace', $metadataMapping)) {
                $metadataDriverChain->addDriver($mappingDriver, $metadataMapping['namespace']);
            } else {
                $metadataDriverChain->setDefaultDriver($mappingDriver);
            }
        }
    }

    /**
     * Retrieve mapping driver.
     *
     * @param array $metadataMapping
     *
     * @throws \UnexpectedValueException
     *
     * @return MappingDriver
     */
    protected function getMappingDriver(array $metadataMapping)
    {
        if (array_key_exists('driver', $metadataMapping)) {
            $mappingDriver = $metadataMapping['driver'];

            if (!$mappingDriver instanceof MappingDriver) {
                throw new \UnexpectedValueException(
                    sprintf('Provided driver should be of the type MappingDriver, "%s" given', gettype($mappingDriver))
                );
            }

            return $mappingDriver;
        }

        if (count(array_intersect(['type', 'path'], array_keys($metadataMapping))) === 2) {
            $metadataMapping = array_merge(['extension' => null], $metadataMapping);

            return $this->getMappingDriverImplementation(
                $metadataMapping['type'],
                (array) $metadataMapping['path'],
                $metadataMapping['extension']
            );
        }

        throw new \UnexpectedValueException(
            'metadata_mapping must be array with "driver" key or "type" and "path" keys'
        );
    }

    /**
     * Get metadata mapping driver implementation.
     *
     * @param string $type
     * @param array  $paths
     * @param string $extension
     *
     * @throws \UnexpectedValueException
     *
     * @return MappingDriver|PHPDriver
     */
    protected function getMappingDriverImplementation($type, $paths, $extension)
    {
        switch ($type) {
            case ManagerBuilder::METADATA_MAPPING_ANNOTATION:
                return $this->getAnnotationMappingDriver($paths);

            case ManagerBuilder::METADATA_MAPPING_XML:
                return $this->getXmlMappingDriver($paths, $extension);

            case ManagerBuilder::METADATA_MAPPING_YAML:
                return $this->getYamlMappingDriver($paths, $extension);

            case ManagerBuilder::METADATA_MAPPING_PHP:
                return $this->getPhpMappingDriver($paths);
        }

        throw new \UnexpectedValueException(
            sprintf('"%s" is not a valid metadata mapping type', $type)
        );
    }

    /**
     * Get annotation metadata driver.
     *
     * @param array $paths
     *
     * @return MappingDriver
     */
    abstract protected function getAnnotationMappingDriver(array $paths);

    /**
     * Get XML metadata driver.
     *
     * @param array       $paths
     * @param string|null $extension
     *
     * @return MappingDriver
     */
    abstract protected function getXmlMappingDriver(array $paths, $extension = null);

    /**
     * Get YAML metadata driver.
     *
     * @param array       $paths
     * @param string|null $extension
     *
     * @return MappingDriver
     */
    abstract protected function getYamlMappingDriver(array $paths, $extension = null);

    /**
     * Get PHP metadata driver.
     *
     * @param array $paths
     *
     * @return PHPDriver
     */
    protected function getPhpMappingDriver(array $paths)
    {
        return new PHPDriver($paths);
    }

    /**
     * {@inheritdoc}
     *
     * @return $this
     */
    public function setMetadataMappingDriver(MappingDriverChain $mappingDriver)
    {
        $this->mappingDriver = $mappingDriver;

        return $this;
    }

    /**
     * Get custom repository factory.
     */
    abstract protected function getRepositoryFactory();

    /**
     * Get default repository class name.
     *
     * @return string|null
     */
    protected function getDefaultRepositoryClass()
    {
        return array_key_exists('default_repository_class', $this->options)
            ? (string) $this->options['default_repository_class']
            : null;
    }

    /**
     * Retrieve proxies path.
     *
     * @return string
     */
    protected function getProxiesPath()
    {
        return (string) $this->getOption('proxies_path', sys_get_temp_dir());
    }

    /**
     * Retrieve proxies namespace.
     *
     * @return null|string
     */
    protected function getProxiesNamespace()
    {
        $proxyNamespace = $this->getOption('proxies_namespace');

        return is_string($proxyNamespace) ? $proxyNamespace : null;
    }

    /**
     * Retrieve proxy generation strategy.
     *
     * @return int
     */
    protected function getProxiesAutoGeneration()
    {
        return (int) $this->getOption('proxies_auto_generation');
    }

    /**
     * {@inheritdoc}
     *
     * @throws \InvalidArgumentException
     */
    public function getMetadataCacheDriver()
    {
        if (!$this->metadataCacheDriver instanceof CacheProvider) {
            $metadataCacheDriver = $this->getOption('metadata_cache_driver');

            if (!$metadataCacheDriver instanceof CacheProvider) {
                $metadataCacheDriver = CacheBuilder::build();
            }

            if ($metadataCacheDriver->getNamespace() === '') {
                $metadataCacheDriver->setNamespace((string) $this->getOption('metadata_cache_namespace'));
            }

            $this->metadataCacheDriver = $metadataCacheDriver;
        }

        return $this->metadataCacheDriver;
    }

    /**
     * {@inheritdoc}
     *
     * @return $this
     */
    public function setMetadataCacheDriver(CacheProvider $metadataCacheDriver)
    {
        $this->metadataCacheDriver = $metadataCacheDriver;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getEventManager()
    {
        if (!$this->eventManager instanceof EventManager) {
            $eventManager = $this->getOption('event_manager');

            if (!$eventManager instanceof EventManager) {
                $eventManager = new EventManager;
            }

            $this->eventManager = $eventManager;
        }

        return $this->eventManager;
    }

    /**
     * {@inheritdoc}
     *
     * @return $this
     */
    public function setEventManager(EventManager $eventManager)
    {
        $this->eventManager = $eventManager;

        return $this;
    }

    /**
     * Get event subscribers.
     *
     * @return array|null
     */
    protected function getEventSubscribers()
    {
        $eventSubscribers = $this->getOption('event_subscribers');

        if (is_null($eventSubscribers) || !is_array($eventSubscribers)) {
            return;
        }

        return array_filter(
            $eventSubscribers,
            function ($name) {
                return is_string($name);
            },
            ARRAY_FILTER_USE_KEY
        );
    }
}
