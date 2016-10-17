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
use Doctrine\Common\Cache\ApcuCache;
use Doctrine\Common\Cache\ArrayCache;
use Doctrine\Common\Cache\CacheProvider;
use Doctrine\Common\Cache\MemcacheCache;
use Doctrine\Common\Cache\RedisCache;
use Doctrine\Common\Cache\XcacheCache;
use Doctrine\Common\EventManager;
use Doctrine\Common\Persistence\Mapping\Driver\MappingDriver;
use Doctrine\Common\Persistence\Mapping\Driver\MappingDriverChain;
use Doctrine\Common\Persistence\Mapping\Driver\PHPDriver;
use Doctrine\Common\Proxy\AbstractProxyFactory;

/**
 * Abstract Doctrine Manager builder.
 */
abstract class AbstractManagerBuilder implements ManagerBuilder
{
    /**
     * Manager builder's common default options.
     *
     * @var array
     */
    private $defaultOptions = [
        'proxies_auto_generation' => AbstractProxyFactory::AUTOGENERATE_NEVER,
    ];

    /**
     * Builder options.
     *
     * @var array
     */
    protected $options = [];

    /**
     * Builder name.
     *
     * @var
     */
    protected $name;

    /**
     * Metadata mapping driver.
     *
     * @var MappingDriverChain
     */
    protected $mappingDriver;

    /**
     * General cache driver.
     *
     * @var CacheProvider
     */
    protected $cacheDriver;

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
     * Unset created objects for rebuild.
     */
    protected function wipe()
    {
        $this->mappingDriver = null;
        $this->cacheDriver = null;
        $this->metadataCacheDriver = null;
        $this->eventManager = null;
    }

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
     * Retrieve builder options.
     *
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * {@inheritdoc}
     */
    public function getOption($option, $default = null)
    {
        return array_key_exists($option, $this->options) ? $this->options[$option] : $default;
    }

    /**
     * {@inheritdoc}
     */
    public function hasOption($option)
    {
        return array_key_exists($option, $this->options);
    }

    /**
     * Set builder options.
     *
     * @param array $options
     *
     * @return $this
     */
    public function setOptions(array $options)
    {
        foreach ($options as $option => $value) {
            $this->setOption($option, $value);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @return $this
     */
    public function setOption($option, $value)
    {
        $this->options[$option] = $value;

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
            $metadataDriver = new MappingDriverChain;

            foreach ((array) $this->getOption('metadata_mapping') as $metadataMapping) {
                if (!is_array($metadataMapping)) {
                    $metadataMapping = ['driver' => $metadataMapping];
                }

                if (!array_key_exists('namespace', $metadataMapping) && $metadataDriver->getDefaultDriver() !== null) {
                    throw new \RuntimeException(
                        'Only one default metadata mapping driver allowed, a namespace must be defined'
                    );
                }

                $mappingDriver = $this->getMappingDriver($metadataMapping);

                if (array_key_exists('namespace', $metadataMapping)) {
                    $metadataDriver->addDriver($mappingDriver, $metadataMapping['namespace']);
                } else {
                    $metadataDriver->setDefaultDriver($mappingDriver);
                }
            }

            if ($metadataDriver->getDefaultDriver() === null && count($metadataDriver->getDrivers()) === 0) {
                throw new \RuntimeException('No metadata mapping defined');
            }

            $this->mappingDriver = $metadataDriver;
        }

        return $this->mappingDriver;
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

            $paths = (array) $metadataMapping['path'];
            $extension = $metadataMapping['extension'];

            switch ($metadataMapping['type']) {
                case ManagerBuilder::METADATA_MAPPING_ANNOTATION:
                    return $this->getAnnotationMetadataDriver($paths);

                case ManagerBuilder::METADATA_MAPPING_XML:
                    return $this->getXmlMetadataDriver($paths, $extension);

                case ManagerBuilder::METADATA_MAPPING_YAML:
                    return $this->getYamlMetadataDriver($paths, $extension);

                case ManagerBuilder::METADATA_MAPPING_PHP:
                    return $this->getPhpMetadataDriver($paths);
            }

            throw new \UnexpectedValueException(
                sprintf('"%s" is not a valid metadata mapping type', $metadataMapping['type'])
            );
        }

        throw new \UnexpectedValueException(
            'metadata_mapping must be array with "driver" key or "type" and "path" keys'
        );
    }

    /**
     * Get annotation metadata driver.
     *
     * @param array $paths
     *
     * @return MappingDriver
     */
    abstract protected function getAnnotationMetadataDriver(array $paths);

    /**
     * Get XML metadata driver.
     *
     * @param array       $paths
     * @param string|null $extension
     *
     * @return MappingDriver
     */
    abstract protected function getXmlMetadataDriver(array $paths, $extension = null);

    /**
     * Get YAML metadata driver.
     *
     * @param array       $paths
     * @param string|null $extension
     *
     * @return MappingDriver
     */
    abstract protected function getYamlMetadataDriver(array $paths, $extension = null);

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
     * Get PHP metadata driver.
     *
     * @param array $paths
     *
     * @return PHPDriver
     */
    protected function getPhpMetadataDriver(array $paths)
    {
        return new PHPDriver($paths);
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
                $metadataCacheDriver = $this->createNewCacheDriver();
            }

            if ($metadataCacheDriver->getNamespace() === '') {
                $metadataCacheDriver->setNamespace((string) $this->getOption('metadata_cache_namespace'));
            }

            $this->metadataCacheDriver = $metadataCacheDriver;
        }

        return $this->metadataCacheDriver;
    }

    /**
     * Retrieve a newly created cache driver.
     *
     * @return ApcuCache|ArrayCache|MemcacheCache|RedisCache|XcacheCache
     */
    private function createNewCacheDriver()
    {
        switch (true) {
            // @codeCoverageIgnoreStart
            case extension_loaded('apc'):
                $cacheDriver = new ApcuCache;
                break;

            case extension_loaded('xcache'):
                $cacheDriver = new XcacheCache;
                break;

            case extension_loaded('memcache'):
                $memcache = new \Memcache;
                $memcache->connect('127.0.0.1');

                $cacheDriver = new MemcacheCache;
                $cacheDriver->setMemcache($memcache);
                break;

            case extension_loaded('redis'):
                $redis = new \Redis();
                $redis->connect('127.0.0.1');

                $cacheDriver = new RedisCache;
                $cacheDriver->setRedis($redis);
                break;
            // @codeCoverageIgnoreEnd

            default:
                $cacheDriver = new ArrayCache;
        }

        return $cacheDriver;
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

        if (!is_null($eventSubscribers) && !is_array($eventSubscribers)) {
            $eventSubscribers = [$eventSubscribers];
        }

        return $eventSubscribers;
    }
}
