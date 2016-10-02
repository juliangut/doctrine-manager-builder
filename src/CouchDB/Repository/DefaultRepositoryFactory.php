<?php

/*
 * doctrine-manager-builder (https://github.com/juliangut/doctrine-manager-builder).
 * Doctrine2 managers builder.
 *
 * @license BSD-3-Clause
 * @link https://github.com/juliangut/doctrine-manager-builder
 * @author Julián Gutiérrez <juliangut@gmail.com>
 */

namespace Jgut\Doctrine\ManagerBuilder\CouchDB\Repository;

use Jgut\Doctrine\ManagerBuilder\CouchDB\DocumentManager;

/**
 * Default CouchDB document repository factory.
 */
class DefaultRepositoryFactory implements RepositoryFactory
{
    /**
     * The list of DocumentRepository instances.
     *
     * @var \Doctrine\Common\Persistence\ObjectRepository[]
     */
    private $repositoryList = [];

    /**
     * {@inheritdoc}
     */
    public function getRepository(DocumentManager $documentManager, $documentName)
    {
        $repositoryHash =
            $documentManager->getClassMetadata($documentName)->getName() . spl_object_hash($documentManager);

        if (isset($this->repositoryList[$repositoryHash])) {
            return $this->repositoryList[$repositoryHash];
        }

        $this->repositoryList[$repositoryHash] = $this->createRepository($documentManager, $documentName);

        return $this->repositoryList[$repositoryHash];
    }

    /**
     * Create a new repository instance for a document class.
     *
     * @param DocumentManager $documentManager
     * @param string          $documentName
     *
     * @return \Doctrine\Common\Persistence\ObjectRepository
     */
    private function createRepository(DocumentManager $documentManager, $documentName)
    {
        /* @var $metadata \Doctrine\ODM\CouchDB\Mapping\ClassMetadata */
        $metadata = $documentManager->getClassMetadata($documentName);
        $repositoryClassName = $metadata->customRepositoryClassName
            ?: $documentManager->getDefaultRepositoryClassName();

        return new $repositoryClassName($documentManager, $metadata);
    }
}
