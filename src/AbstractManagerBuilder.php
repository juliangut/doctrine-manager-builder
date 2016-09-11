<?php
/**
 * doctrine-manager-builder (https://github.com/juliangut/doctrine-manager-builder)
 * Doctrine2 managers builder
 *
 * @license BSD-3-Clause
 * @author Julián Gutiérrez <juliangut@gmail.com>
 */

namespace Jgut\Doctrine\ManagerBuilder;

use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\Cache\ApcuCache;
use Doctrine\Common\Cache\ArrayCache;
use Doctrine\Common\Cache\Cache;
use Doctrine\Common\Cache\MemcacheCache;
use Doctrine\Common\Cache\RedisCache;
use Doctrine\Common\Cache\XcacheCache;
use Doctrine\Common\EventManager;
use Doctrine\Common\Persistence\Mapping\Driver\MappingDriver;
use Doctrine\Common\Persistence\Mapping\Driver\MappingDriverChain;
use Doctrine\Common\Persistence\Mapping\Driver\PHPDriver;

/**
 * Abstract Doctrine Manager builder.
 */
abstract class AbstractManagerBuilder implements ManagerBuilder
{
    /**
     * Builder name.
     *
     * @var
     */
    protected $name;

    /**
     * Builder options.
     *
     * @var array
     */
    protected $options = [];

    /**
     * Metadata mapping driver.
     *
     * @var MappingDriverChain
     */
    protected $mappingDriver;

    /**
     * General cache driver.
     *
     * @var Cache
     */
    protected $cacheDriver;

    /**
     * Metadata cache driver.
     *
     * @var Cache
     */
    protected $metadataCacheDriver;

    /**
     * Mapping driver chain.
     *
     * @var MappingDriverChain
     */
    protected $mappingDriverChain;

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
        $this->setOptions(array_merge($this->getDefaultOptions(), $options));

        if (is_string($name)) {
            $this->setName($name);
        }
    }

    /**
     * Unset created objects for rebuild.
     */
    protected function wipe()
    {
        $this->mappingDriver = null;
        $this->cacheDriver = null;
        $this->metadataCacheDriver = null;
        $this->mappingDriverChain = null;
        $this->eventManager = null;
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set builder's name.
     *
     * @param string $name
     *
     * @return $this
     */
    public function setName($name)
    {
        $this->name = strtolower(trim($name));

        return $this;
    }

    /**
     * Get default options.
     *
     * @return array
     */
    abstract protected function getDefaultOptions();

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
     * Retrieve builder option.
     *
     * @param string     $option
     * @param mixed|null $default
     *
     * @return mixed
     */
    public function getOption($option, $default = null)
    {
        return array_key_exists($option, $this->options) ? $this->options[$option] : $default;
    }

    /**
     * Verifies option existence.
     *
     * @param string $option
     *
     * @return bool
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
     * Set builder option.
     *
     * @param string $option
     * @param mixed  $value
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
     * @param bool $registerDefault
     *
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    protected function setupAnnotationMetadata($registerDefault = false)
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

        if ($registerDefault === true) {
            AnnotationRegistry::registerLoader('class_exists');
        }
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
     * Create metadata mapping driver.
     *
     * @throws \RuntimeException
     * @throws \UnexpectedValueException
     *
     * @return MappingDriverChain
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
        } elseif (array_key_exists('type', $metadataMapping) && array_key_exists('path', $metadataMapping)) {
            return $this->getMetadataDriver(
                $metadataMapping['type'],
                (array) $metadataMapping['path'],
                array_key_exists('extension', $metadataMapping) ? $metadataMapping['extension'] : null
            );
        }

        throw new \UnexpectedValueException(
            'metadata_mapping must be array with "driver" key or "type" and "path" keys'
        );
    }

    /**
     * Retrieve metadata driver implementation.
     *
     * @param string      $type
     * @param array       $paths
     * @param string|null $extension
     *
     * @throws \UnexpectedValueException
     *
     * @return MappingDriver
     */
    protected function getMetadataDriver($type, array $paths, $extension = null)
    {
        switch ($type) {
            case ManagerBuilder::METADATA_MAPPING_ANNOTATION:
                $metadataDriver = $this->getAnnotationMetadataDriver($paths);
                break;

            case ManagerBuilder::METADATA_MAPPING_XML:
                $metadataDriver = $this->getXmlMetadataDriver($paths, $extension);
                break;

            case ManagerBuilder::METADATA_MAPPING_YAML:
                $metadataDriver = $this->getYamlMetadataDriver($paths, $extension);
                break;

            case ManagerBuilder::METADATA_MAPPING_PHP:
                $metadataDriver = new PHPDriver($paths);
                break;

            default:
                throw new \UnexpectedValueException(sprintf('"%s" is not a valid metadata mapping type', $type));
        }

        return $metadataDriver;
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
     * Retrieve metadata cache driver.
     *
     * @throws \InvalidArgumentException
     *
     * @return Cache
     */
    public function getMetadataCacheDriver()
    {
        if (!$this->metadataCacheDriver instanceof Cache) {
            $metadataCacheDriver = $this->getOption('metadata_cache_driver');

            if (!$metadataCacheDriver instanceof Cache) {
                $metadataCacheDriver = $this->getCacheDriver();
            }

            if ($metadataCacheDriver->getNamespace() === '') {
                $metadataCacheDriver->setNamespace($this->getMetadataCacheNamespace());
            }

            $this->metadataCacheDriver = $metadataCacheDriver;
        }

        return $this->metadataCacheDriver;
    }

    /**
     * Set metadata cache driver.
     *
     * @param Cache $metadataCacheDriver
     */
    public function setMetadataCacheDriver(Cache $metadataCacheDriver)
    {
        $this->metadataCacheDriver = $metadataCacheDriver;
    }

    /**
     * Retrieve metadata cache namespace.
     *
     * @return string
     */
    protected function getMetadataCacheNamespace()
    {
        return (string) $this->getOption('metadata_cache_namespace', $this->getCacheDriverNamespace());
    }

    /**
     * Retrieve general cache driver.
     *
     * @throws \InvalidArgumentException
     *
     * @return Cache
     */
    public function getCacheDriver()
    {
        if (!$this->cacheDriver instanceof Cache) {
            $cacheNamespace = $this->getCacheDriverNamespace();
            $cacheDriver = $this->getOption('cache_driver');

            if ($cacheDriver === null) {
                $cacheDriver = $this->createNewCacheDriver($cacheNamespace);
            }

            if (!$cacheDriver instanceof Cache) {
                throw new \InvalidArgumentException('Cache Driver provided is not valid');
            }

            if ($cacheDriver->getNamespace() === '') {
                $cacheDriver->setNamespace($cacheNamespace);
            }

            $this->cacheDriver = $cacheDriver;
        }

        return $this->cacheDriver;
    }

    /**
     * Retrieve a newly created cache driver.
     *
     * @param string $namespace
     *
     * @return ApcuCache|ArrayCache|MemcacheCache|RedisCache|XcacheCache
     */
    private function createNewCacheDriver($namespace)
    {
        // @codeCoverageIgnoreStart
        if (extension_loaded('apc')) {
            $cacheDriver = new ApcuCache;
        } elseif (extension_loaded('xcache')) {
            $cacheDriver = new XcacheCache;
        } elseif (extension_loaded('memcache')) {
            $memcache = new \Memcache;
            $memcache->connect('127.0.0.1');

            $cacheDriver = new MemcacheCache;
            $cacheDriver->setMemcache($memcache);
        } elseif (extension_loaded('redis')) {
            $redis = new \Redis();
            $redis->connect('127.0.0.1');

            $cacheDriver = new RedisCache;
            $cacheDriver->setRedis($redis);
        } else {
            $cacheDriver = new ArrayCache;
        }
        // @codeCoverageIgnoreEnd

        $cacheDriver->setNamespace($namespace);

        return $cacheDriver;
    }

    /**
     * Set general cache driver.
     *
     * @param Cache $cacheDriver
     */
    public function setCacheDriver(Cache $cacheDriver)
    {
        $this->cacheDriver = $cacheDriver;
    }

    /**
     * Retrieve general cache driver namespace.
     *
     * @return string
     */
    protected function getCacheDriverNamespace()
    {
        return (string) $this->getOption('cache_driver_namespace', 'dc2_' . sha1(sys_get_temp_dir()) . '_');
    }

    /**
     * Get default repository class name
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
     * Retrieve event manager.
     *
     * @return EventManager
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
     * Set event manager.
     *
     * @param EventManager $eventManager
     */
    public function setEventManager(EventManager $eventManager)
    {
        $this->eventManager = $eventManager;
    }
}
