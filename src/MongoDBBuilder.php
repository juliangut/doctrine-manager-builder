<?php

/*
 * (c) 2016-2023 Julián Gutiérrez <juliangut@gmail.com>
 *
 * @license BSD-3-Clause
 * @link https://github.com/juliangut/doctrine-manager-builder
 */

declare(strict_types=1);

namespace Jgut\Doctrine\ManagerBuilder;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\EventManager;
use Doctrine\Common\Proxy\AbstractProxyFactory;
use Doctrine\ODM\MongoDB\Configuration;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Mapping\Driver\AnnotationDriver;
use Doctrine\ODM\MongoDB\Mapping\Driver\AttributeDriver;
use Doctrine\ODM\MongoDB\Mapping\Driver\XmlDriver;
use Doctrine\ODM\MongoDB\Query\Filter\BsonFilter;
use Doctrine\ODM\MongoDB\Repository\DocumentRepository;
use Doctrine\ODM\MongoDB\Repository\RepositoryFactory;
use Doctrine\ODM\MongoDB\Tools\Console\Command\ClearCache\MetadataCommand;
use Doctrine\ODM\MongoDB\Tools\Console\Command\GenerateHydratorsCommand;
use Doctrine\ODM\MongoDB\Tools\Console\Command\GeneratePersistentCollectionsCommand;
use Doctrine\ODM\MongoDB\Tools\Console\Command\GenerateProxiesCommand;
use Doctrine\ODM\MongoDB\Tools\Console\Command\QueryCommand;
use Doctrine\ODM\MongoDB\Tools\Console\Command\Schema\CreateCommand;
use Doctrine\ODM\MongoDB\Tools\Console\Command\Schema\DropCommand;
use Doctrine\ODM\MongoDB\Tools\Console\Command\Schema\ShardCommand;
use Doctrine\ODM\MongoDB\Tools\Console\Command\Schema\UpdateCommand;
use Doctrine\ODM\MongoDB\Tools\Console\Command\Schema\ValidateCommand;
use Doctrine\ODM\MongoDB\Tools\Console\Helper\DocumentManagerHelper;
use Doctrine\ODM\MongoDB\Types\Type;
use Doctrine\Persistence\Mapping\Driver\MappingDriver;
use InvalidArgumentException;
use Jgut\Doctrine\ManagerBuilder\Console\Command\MongoDB\InfoCommand;
use MongoDB\Client;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\HelperSet;
use UnexpectedValueException;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.LongVariable)
 */
class MongoDBBuilder extends AbstractManagerBuilder
{
    protected ?DocumentManager $manager = null;

    /**
     * @var Client|array{uri?: string, uriOptions?: array<string, mixed>, driverOptions?: array<string, mixed>}
     */
    protected Client|array $client = [];

    protected string $proxiesNamespace = 'DoctrineMongoDBODMProxy';

    /**
     * @var int<0, 4>
     */
    protected int $proxiesAutoGeneration = AbstractProxyFactory::AUTOGENERATE_FILE_NOT_EXISTS;

    protected ?RepositoryFactory $repositoryFactory = null;

    /**
     * @var class-string<DocumentRepository<object>>
     */
    protected string $defaultRepositoryClass = DocumentRepository::class;

    protected ?string $hydrationPath = null;

    protected string $hydrationNamespace = 'DoctrineMongoDBODMHydration';

    /**
     * @var int<0, 3>
     */
    protected int $hydrationAutoGeneration = Configuration::AUTOGENERATE_NEVER;

    protected ?string $persistentCollectionPath = null;

    protected string $persistentCollectionNamespace = 'DoctrineMongoDBODMPersistentCollection';

    /**
     * @var int<0, 3>
     */
    protected int $persistentCollectionAutoGeneration = Configuration::AUTOGENERATE_NEVER;

    protected ?string $defaultDatabase = null;

    /**
     * @var array<string, class-string<Type>>
     */
    protected array $customTypes = [];

    /**
     * @var array<string, class-string<BsonFilter>>
     */
    protected array $customFilters = [];

    public function getManager(bool $force = false): DocumentManager
    {
        if ($force === true) {
            $this->wipe();
        }

        if ($this->manager === null) {
            $this->manager = $this->buildManager();
        }

        return $this->manager;
    }

    protected function wipe(): void
    {
        $this->manager = null;
        $this->mappingDriver = null;
        $this->metadataCache = null;
        $this->eventManager = null;
    }

    /**
     * @throws InvalidArgumentException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    protected function buildManager(): DocumentManager
    {
        $config = new Configuration();

        $this->setUpGeneralConfigurations($config);
        $this->setUpSpecificConfigurations($config);

        $eventManager = $this->eventManager ?? new EventManager();
        foreach ($this->eventSubscribers as $eventSubscriber) {
            $eventManager->addEventSubscriber($eventSubscriber);
        }

        $documentManager = DocumentManager::create($this->getClient(), $config, $eventManager);

        foreach ($this->customTypes as $type => $class) {
            if (Type::hasType($type)) {
                Type::overrideType($type, $class);
            } else {
                Type::addType($type, $class);
            }
        }

        return $documentManager;
    }

    protected function setUpGeneralConfigurations(Configuration $config): void
    {
        $config->setMetadataDriverImpl($this->getMetadataMappingDriver());
        $config->setProxyDir($this->proxiesPath ?? sys_get_temp_dir() . '/doctrine/odm/proxies');
        $config->setProxyNamespace($this->proxiesNamespace);
        $config->setAutoGenerateProxyClasses($this->proxiesAutoGeneration);
        if ($this->repositoryFactory !== null) {
            $config->setRepositoryFactory($this->repositoryFactory);
        }
        $config->setDefaultDocumentRepositoryClassName($this->defaultRepositoryClass);
        $config->setMetadataCache($this->metadataCache ?? $this->getInMemoryDummyCache());
    }

    protected function setUpSpecificConfigurations(Configuration $config): void
    {
        $config->setHydratorDir($this->hydrationPath ?? sys_get_temp_dir() . '/doctrine/odm/hydrators');
        $config->setHydratorNamespace($this->hydrationNamespace);
        $config->setAutoGenerateHydratorClasses($this->hydrationAutoGeneration);
        if ($this->persistentCollectionPath !== null) {
            $config->setPersistentCollectionDir($this->persistentCollectionPath);
        }
        $config->setPersistentCollectionNamespace($this->persistentCollectionNamespace);
        $config->setAutoGeneratePersistentCollectionClasses($this->persistentCollectionAutoGeneration);
        if ($this->defaultDatabase !== null) {
            $config->setDefaultDB($this->defaultDatabase);
        }

        foreach ($this->customFilters as $filter => $class) {
            $config->addFilter($filter, $class);
        }
    }

    /**
     * @param Client|array{uri?: string, uriOptions?: array<string, mixed>, driverOptions?: array<string, mixed>} $client
     */
    public function setClient(array|Client $client): void
    {
        $this->client = $client;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function setRepositoryFactory(RepositoryFactory $repositoryFactory): void
    {
        $this->repositoryFactory = $repositoryFactory;
    }

    /**
     * @param class-string<DocumentRepository<object>> $defaultRepositoryClass
     *
     * @throws InvalidArgumentException
     */
    public function setDefaultRepositoryClass(string $defaultRepositoryClass): void
    {
        if (!class_exists($defaultRepositoryClass)
            || !is_a($defaultRepositoryClass, DocumentRepository::class, true)
        ) {
            throw new InvalidArgumentException(
                sprintf('Repository class should be a "%s".', DocumentRepository::class),
            );
        }

        $this->defaultRepositoryClass = $defaultRepositoryClass;
    }

    public function setHydrationPath(string $hydrationPath): void
    {
        $this->hydrationPath = $hydrationPath;
    }

    public function getHydrationNamespace(string $hydrationNamespace): void
    {
        $this->hydrationNamespace = $hydrationNamespace;
    }

    /**
     * @param int<0, 3> $autoGeneration
     *
     * @throws InvalidArgumentException
     */
    public function setHydrationAutoGeneration(int $autoGeneration): void
    {
        $autoGenerationValues = [
            Configuration::AUTOGENERATE_ALWAYS,
            Configuration::AUTOGENERATE_NEVER,
            Configuration::AUTOGENERATE_FILE_NOT_EXISTS,
            Configuration::AUTOGENERATE_EVAL,
        ];
        if (!\in_array($autoGeneration, $autoGenerationValues, true)) {
            throw new InvalidArgumentException(
                sprintf('Invalid hydration auto generation value "%d".', $autoGeneration),
            );
        }

        $this->hydrationAutoGeneration = $autoGeneration;
    }

    public function setPersistentCollectionPath(string $persistentCollectionPath): void
    {
        $this->persistentCollectionPath = $persistentCollectionPath;
    }

    public function getPersistentCollectionNamespace(string $persistentCollectionNamespace): void
    {
        $this->persistentCollectionNamespace = $persistentCollectionNamespace;
    }

    /**
     * @param int<0, 3> $autoGeneration
     *
     * @throws InvalidArgumentException
     */
    public function setPersistentCollectionAutoGeneration(int $autoGeneration): void
    {
        $autoGenerationValues = [
            Configuration::AUTOGENERATE_ALWAYS,
            Configuration::AUTOGENERATE_NEVER,
            Configuration::AUTOGENERATE_FILE_NOT_EXISTS,
            Configuration::AUTOGENERATE_EVAL,
        ];
        if (!\in_array($autoGeneration, $autoGenerationValues, true)) {
            throw new InvalidArgumentException(
                sprintf('Invalid persist collection auto generation value "%d".', $autoGeneration),
            );
        }

        $this->persistentCollectionAutoGeneration = $autoGeneration;
    }

    public function setDefaultDatabase(string $defaultDatabase): void
    {
        $this->defaultDatabase = $defaultDatabase;
    }

    /**
     * @param array<string, class-string<Type>> $types
     */
    public function setCustomTypes(array $types): void
    {
        $this->customTypes = $types;
    }

    /**
     * @param array<string, class-string<BsonFilter>> $filters
     */
    public function setCustomFilters(array $filters): void
    {
        $this->customFilters = $filters;
    }

    /**
     * @throws InvalidArgumentException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     *
     * @return array<Command>
     */
    public function getConsoleCommands(): array
    {
        $commands = [
            // ODM
            new GenerateProxiesCommand(),
            new GenerateHydratorsCommand(),
            new GeneratePersistentCollectionsCommand(),
            new InfoCommand(),
            new ValidateCommand(),
            new MetadataCommand(),
            new ShardCommand(),
            new QueryCommand(),
            new CreateCommand(),
            new UpdateCommand(),
            new DropCommand(),
        ];

        if (class_exists(ValidateCommand::class)) {
            $commands[] = new ValidateCommand();
        }

        $helperSet = $this->getConsoleHelperSet($this->getManager());
        $commandPrefix = (string) $this->getName();

        return array_map(
            static function (Command $command) use ($helperSet, $commandPrefix): Command {
                if ($commandPrefix !== '') {
                    $commandNames = array_map(
                        static fn(string $commandName): string
                            => (string) preg_replace('/^odm:/', 'odm-' . $commandPrefix . ':', $commandName),
                        array_merge([$command->getName()], $command->getAliases()),
                    );

                    $command->setName(array_shift($commandNames));
                    $command->setAliases($commandNames);
                }

                $command->setHelperSet($helperSet);

                return $command;
            },
            $commands,
        );
    }

    protected function getConsoleHelperSet(DocumentManager $documentManager): HelperSet
    {
        return new HelperSet([
            'dm' => new DocumentManagerHelper($documentManager),
        ]);
    }

    /**
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    protected function getClient(): Client
    {
        $client = $this->client;
        if (\is_array($client)) {
            $client = new Client(
                $client['uri'] ?? 'mongodb://127.0.0.1/',
                $client['uriOptions'] ?? [],
                $client['driverOptions'] ?? [],
            );

            $this->client = $client;
        }

        return $client;
    }

    /**
     * @param list<string> $paths
     */
    protected function getAttributeMappingDriver(array $paths): AttributeDriver
    {
        return new AttributeDriver($paths);
    }

    /**
     * @param list<string> $paths
     */
    protected function getAnnotationMappingDriver(array $paths): AnnotationDriver
    {
        return new AnnotationDriver(new AnnotationReader(), $paths);
    }

    /**
     * @param list<string> $paths
     */
    protected function getXmlMappingDriver(array $paths, ?string $extension = null): XmlDriver
    {
        return new XmlDriver($paths, $extension ?? XmlDriver::DEFAULT_FILE_EXTENSION);
    }

    /**
     * @param list<string> $paths
     *
     * @throws RuntimeException
     */
    protected function getYamlMappingDriver(array $paths, ?string $extension = null): MappingDriver
    {
        throw new RuntimeException('Yaml driver is no longer available.');
    }
}
