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
 * Interface for CouchDB document repository factories.
 */
interface RepositoryFactory
{
    /**
     * Gets the repository for a document class.
     *
     * @param DocumentManager $documentManager
     * @param string          $documentName
     *
     * @return \Doctrine\Common\Persistence\ObjectRepository
     */
    public function getRepository(DocumentManager $documentManager, $documentName);
}
