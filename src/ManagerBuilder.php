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

use Doctrine\Common\Cache\CacheProvider;
use Doctrine\Common\EventManager;
use Doctrine\Common\Persistence\Mapping\Driver\MappingDriverChain;

/**
 * Doctrine Manager builder interface.
 */
interface ManagerBuilder
{
    const METADATA_MAPPING_ANNOTATION = 'annotation';
    const METADATA_MAPPING_XML = 'xml';
    const METADATA_MAPPING_YAML = 'yaml';
    const METADATA_MAPPING_PHP = 'php';

    /**
     * Get builder's name.
     *
     * @return string
     */
    public function getName();

    /**
     * Set builder's name.
     *
     * @param string|null $name
     */
    public function setName($name = null);

    /**
     * Verifies option existence.
     *
     * @param string $option
     *
     * @return bool
     */
    public function hasOption($option);

    /**
     * Retrieve builder option.
     *
     * @param string     $option
     * @param mixed|null $default
     *
     * @return mixed
     */
    public function getOption($option, $default = null);

    /**
     * Set builder option.
     *
     * @param string $option
     * @param mixed  $value
     */
    public function setOption($option, $value);

    /**
     * Get metadata mapping driver.
     *
     * @return MappingDriverChain
     */
    public function getMetadataMappingDriver();

    /**
     * Set metadata mapping driver.
     *
     * @param MappingDriverChain $mappingDriver
     */
    public function setMetadataMappingDriver(MappingDriverChain $mappingDriver);

    /**
     * Retrieve metadata cache driver.
     *
     * @return CacheProvider
     */
    public function getMetadataCacheDriver();

    /**
     * Set metadata cache driver.
     *
     * @param CacheProvider $metadataCacheDriver
     */
    public function setMetadataCacheDriver(CacheProvider $metadataCacheDriver);

    /**
     * Retrieve event manager.
     *
     * @return EventManager
     */
    public function getEventManager();

    /**
     * Set event manager.
     *
     * @param EventManager $eventManager
     */
    public function setEventManager(EventManager $eventManager);

    /**
     * Retrieve Doctrine object manager.
     *
     * @param bool $force
     *
     * @return \Doctrine\Common\Persistence\ObjectManager
     */
    public function getManager($force = false);

    /**
     * Get console commands.
     *
     * @return \Symfony\Component\Console\Command\Command[]
     */
    public function getConsoleCommands();

    /**
     * Get console helper set.
     *
     * @return \Symfony\Component\Console\Helper\HelperSet
     */
    public function getConsoleHelperSet();
}
