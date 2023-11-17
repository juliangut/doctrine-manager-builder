<?php

/*
 * doctrine-manager-builder (https://github.com/juliangut/doctrine-manager-builder).
 * Doctrine2 managers builder.
 *
 * @license BSD-3-Clause
 * @link https://github.com/juliangut/doctrine-manager-builder
 * @author Julián Gutiérrez <juliangut@gmail.com>
 */

declare(strict_types=1);

namespace Jgut\Doctrine\ManagerBuilder;

use DateTimeZone;
use Doctrine\Common\Cache\CacheProvider;
use Doctrine\Common\Cache\Psr6\CacheAdapter;
use Doctrine\Common\EventManager;
use Doctrine\Common\EventSubscriber;
use Doctrine\Common\Proxy\AbstractProxyFactory;
use Doctrine\Persistence\Mapping\Driver\MappingDriver;
use Doctrine\Persistence\Mapping\Driver\MappingDriverChain;
use Doctrine\Persistence\Mapping\Driver\PHPDriver;
use Doctrine\Persistence\ObjectManager;
use Ergebnis\Clock\SystemClock;
use InvalidArgumentException;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Clock\ClockInterface;
use RuntimeException;
use UnexpectedValueException;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.LongVariable)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
abstract class AbstractManagerBuilder implements ManagerBuilder
{
    /**
     * @var list<MappingDriver|array{driver?: MappingDriver, namespace?: string, type?: string, path?: string|list<string>, extension?: string}>
     */
    protected array $metadataMapping = [];

    protected ?string $proxiesPath = null;

    protected string $proxiesNamespace;

    /**
     * @var int<0, 4>
     */
    protected int $proxiesAutoGeneration = AbstractProxyFactory::AUTOGENERATE_NEVER;

    protected ?string $name = null;

    protected ?MappingDriverChain $mappingDriver = null;

    /**
     * @var CacheItemPoolInterface<mixed>|null
     */
    protected ?CacheItemPoolInterface $metadataCache = null;

    protected ?EventManager $eventManager = null;

    /**
     * @var list<EventSubscriber>
     */
    protected array $eventSubscribers = [];

    private ?ClockInterface $timeProvider = null;

    /**
     * @param array<string, mixed> $options
     *
     * @throws InvalidArgumentException
     */
    public function __construct(array $options = [])
    {
        foreach ($options as $option => $value) {
            $method = 'set' . ucfirst($option);
            if (!method_exists($this, $method)) {
                throw new InvalidArgumentException(sprintf('Unknown configuration "%s".', $option));
            }

            /** @var callable(mixed): void $callable */
            $callable = [$this, $method];

            $callable($value);
        }
    }

    abstract protected function buildManager(): ObjectManager;

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function setMetadataMappingDriver(MappingDriverChain $mappingDriver): void
    {
        $this->mappingDriver = $mappingDriver;
    }

    /**
     * @throws RuntimeException
     */
    public function getMetadataMappingDriver(): MappingDriverChain
    {
        if ($this->mappingDriver === null) {
            $metadataDriverChain = new MappingDriverChain();

            $this->parseMetadataMapping($metadataDriverChain);

            if ($metadataDriverChain->getDefaultDriver() === null && \count($metadataDriverChain->getDrivers()) === 0) {
                throw new RuntimeException('No metadata mapping defined.');
            }

            $this->mappingDriver = $metadataDriverChain;
        }

        return $this->mappingDriver;
    }

    /**
     * @param list<MappingDriver|array{driver?: MappingDriver, namespace?: string, type?: string, path?: string|list<string>, extension?: string}> $metadataMapping
     */
    public function setMetadataMapping(array $metadataMapping): void
    {
        $this->metadataMapping = $metadataMapping;
    }

    /**
     * @throws RuntimeException
     */
    protected function parseMetadataMapping(MappingDriverChain $metadataDriverChain): void
    {
        foreach ($this->metadataMapping as $metadataMapping) {
            if (!\is_array($metadataMapping)) {
                $metadataMapping = ['driver' => $metadataMapping];
            }

            if (!\array_key_exists('namespace', $metadataMapping)
                && $metadataDriverChain->getDefaultDriver() !== null
            ) {
                throw new RuntimeException(
                    'Only one default metadata mapping driver allowed, a namespace must be defined.',
                );
            }

            $mappingDriver = $this->getMappingDriver($metadataMapping);

            if (\array_key_exists('namespace', $metadataMapping)) {
                $metadataDriverChain->addDriver($mappingDriver, $metadataMapping['namespace']);
            } else {
                $metadataDriverChain->setDefaultDriver($mappingDriver);
            }
        }
    }

    /**
     * @param array{driver?: ?mixed, type?: string, path?: string|list<string>, extension?: string} $metadataMapping
     *
     * @throws UnexpectedValueException
     */
    protected function getMappingDriver(array $metadataMapping): MappingDriver
    {
        if (\array_key_exists('driver', $metadataMapping)) {
            $mappingDriver = $metadataMapping['driver'];

            if (!$mappingDriver instanceof MappingDriver) {
                throw new UnexpectedValueException(
                    sprintf(
                        'Provided driver should be an instance of "%s", "%s" given.',
                        MappingDriver::class,
                        \is_object($mappingDriver) ? $mappingDriver::class : \gettype($mappingDriver),
                    ),
                );
            }

            return $mappingDriver;
        }

        if (\count(array_intersect(['type', 'path'], array_keys($metadataMapping))) !== 2) {
            throw new UnexpectedValueException(
                'Metadata mapping must be array with "driver" key or "type" and "path" keys.',
            );
        }

        /** @var array{type: string, path: string|list<string>, extension: ?string} $mapping */
        $mapping = array_merge(['extension' => null], $metadataMapping);

        $path = $mapping['path'];
        if (!\is_array($path)) {
            $path = [$path];
        }

        return $this->getMappingDriverImplementation($mapping['type'], $path, $mapping['extension']);
    }

    /**
     * @param list<string> $paths
     *
     * @throws UnexpectedValueException
     */
    protected function getMappingDriverImplementation(
        string $type,
        array $paths,
        ?string $extension = null,
    ): MappingDriver {
        switch ($type) {
            case ManagerBuilder::METADATA_MAPPING_ATTRIBUTE:
                return $this->getAttributeMappingDriver($paths);

            case ManagerBuilder::METADATA_MAPPING_ANNOTATION:
                return $this->getAnnotationMappingDriver($paths);

            case ManagerBuilder::METADATA_MAPPING_XML:
                return $this->getXmlMappingDriver($paths, $extension);

            case ManagerBuilder::METADATA_MAPPING_YAML:
                return $this->getYamlMappingDriver($paths, $extension);

            case ManagerBuilder::METADATA_MAPPING_PHP:
                return $this->getPhpMappingDriver($paths);
        }

        throw new UnexpectedValueException(
            sprintf('"%s" is not a valid metadata mapping type.', $type),
        );
    }

    /**
     * @param list<string> $paths
     */
    abstract protected function getAttributeMappingDriver(array $paths): MappingDriver;

    /**
     * @param list<string> $paths
     */
    abstract protected function getAnnotationMappingDriver(array $paths): MappingDriver;

    /**
     * @param list<string> $paths
     */
    abstract protected function getXmlMappingDriver(array $paths, ?string $extension = null): MappingDriver;

    /**
     * @param list<string> $paths
     */
    abstract protected function getYamlMappingDriver(array $paths, ?string $extension = null): MappingDriver;

    /**
     * @param list<string> $paths
     */
    protected function getPhpMappingDriver(array $paths): PHPDriver
    {
        return new PHPDriver($paths);
    }

    public function setProxiesPath(string $proxiesPath): void
    {
        $this->proxiesPath = $proxiesPath;
    }

    public function setProxiesNamespace(string $proxiesNamespace): void
    {
        $this->proxiesNamespace = $proxiesNamespace;
    }

    /**
     * @param int<0, 4> $autoGeneration
     *
     * @throws InvalidArgumentException
     */
    public function setProxiesAutoGeneration(int $autoGeneration): void
    {
        $autoGenerationValues = [
            AbstractProxyFactory::AUTOGENERATE_ALWAYS,
            AbstractProxyFactory::AUTOGENERATE_NEVER,
            AbstractProxyFactory::AUTOGENERATE_FILE_NOT_EXISTS,
            AbstractProxyFactory::AUTOGENERATE_EVAL,
            AbstractProxyFactory::AUTOGENERATE_FILE_NOT_EXISTS_OR_CHANGED,
        ];

        if (!\in_array($autoGeneration, $autoGenerationValues, true)) {
            throw new InvalidArgumentException(
                sprintf('Invalid proxies auto generation value "%d".', $autoGeneration),
            );
        }

        $this->proxiesAutoGeneration = $autoGeneration;
    }

    /**
     * @param CacheItemPoolInterface<mixed>|CacheProvider $metadataCache
     */
    public function setMetadataCache(CacheItemPoolInterface|CacheProvider $metadataCache): void
    {
        if ($metadataCache instanceof CacheProvider) {
            $metadataCache = CacheAdapter::wrap($metadataCache);
        }

        $this->metadataCache = $metadataCache;
    }

    public function setEventManager(EventManager $eventManager): void
    {
        $this->eventManager = $eventManager;
    }

    /**
     * @param list<EventSubscriber> $eventSubscribers
     */
    public function setEventSubscribers(array $eventSubscribers): void
    {
        $this->eventSubscribers = $eventSubscribers;
    }

    /**
     * @return CacheItemPoolInterface<mixed>
     */
    protected function getInMemoryDummyCache(): CacheItemPoolInterface
    {
        if ($this->timeProvider === null) {
            $this->timeProvider = new SystemClock(new DateTimeZone(date_default_timezone_get()));
        }

        return CacheAdapter::wrap(new InMemoryCacheProvider($this->timeProvider));
    }
}
