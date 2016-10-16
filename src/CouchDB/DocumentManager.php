<?php

/*
 * doctrine-manager-builder (https://github.com/juliangut/doctrine-manager-builder).
 * Doctrine2 managers builder.
 *
 * @license BSD-3-Clause
 * @link https://github.com/juliangut/doctrine-manager-builder
 * @author Julián Gutiérrez <juliangut@gmail.com>
 */

namespace Jgut\Doctrine\ManagerBuilder\CouchDB;

use Doctrine\Common\EventManager;
use Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\CouchDB\CouchDBClient;
use Doctrine\ODM\CouchDB\Configuration;
use Doctrine\ODM\CouchDB\DocumentManager as BaseDocumentManager;
use Jgut\Doctrine\ManagerBuilder\CouchDB\Repository\DefaultRepositoryFactory;
use Jgut\Doctrine\ManagerBuilder\CouchDB\Repository\RepositoryFactory;

/**
 * Custom Doctrine CouchDB Document Manager.
 */
class DocumentManager extends BaseDocumentManager
{
    /**
     * Repository factory.
     *
     * @var RepositoryFactory
     */
    protected $repositoryFactory;

    /**
     * Default repository class name.
     *
     * @var string
     */
    protected $repositoryClassName = 'Doctrine\ODM\CouchDB\DocumentRepository';

    /**
     * Get repository factory.
     *
     * @return DefaultRepositoryFactory
     */
    public function getRepositoryFactory()
    {
        if ($this->repositoryFactory === null) {
            $this->repositoryFactory = new DefaultRepositoryFactory();
        }

        return $this->repositoryFactory;
    }

    /**
     * Set repository factory.
     *
     * @param RepositoryFactory $repositoryFactory
     */
    public function setRepositoryFactory(RepositoryFactory $repositoryFactory)
    {
        $this->repositoryFactory = $repositoryFactory;
    }

    /**
     * Get default repository class name.
     *
     * @return string
     */
    public function getDefaultRepositoryClassName()
    {
        return $this->repositoryClassName;
    }

    /**
     * Set default repository class name.
     *
     * @param string $className
     *
     * @throws \InvalidArgumentException
     */
    public function setDefaultRepositoryClassName($className)
    {
        $reflectionClass = new \ReflectionClass($className);

        if (!$reflectionClass->implementsInterface(ObjectRepository::class)) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid repository class "%s". It must be a Doctrine\Common\Persistence\ObjectRepository.',
                $className
            ));
        }

        $this->repositoryClassName = $className;
    }

    /**
     * Gets the repository for a class.
     *
     * @param string $documentName
     *
     * @return \Doctrine\ODM\CouchDB\DocumentRepository
     */
    public function getRepository($documentName)
    {
        return $this->getRepositoryFactory()->getRepository($this, $documentName);
    }

    /**
     * {@inheritdoc}
     */
    public static function create($couchParams, Configuration $config = null, EventManager $evm = null)
    {
        if (is_array($couchParams)) {
            $couchClient = CouchDBClient::create($couchParams);
        } elseif ($couchParams instanceof CouchDBClient) {
            $couchClient = $couchParams;
        } else {
            throw new \InvalidArgumentException(
                'Expecting array of instance of CouchDBClient as first argument to DocumentManager::create().'
            );
        }

        return new static($couchClient, $config, $evm);
    }
}
