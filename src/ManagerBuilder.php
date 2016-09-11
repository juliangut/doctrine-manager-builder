<?php
/**
 * doctrine-manager-builder (https://github.com/juliangut/doctrine-manager-builder)
 * Doctrine2 managers builder
 *
 * @license BSD-3-Clause
 * @author Julián Gutiérrez <juliangut@gmail.com>
 */

namespace Jgut\Doctrine\ManagerBuilder;

use Symfony\Component\Console\Helper\HelperSet;

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
     * Retrieve Doctrine object manager.
     *
     * @param bool $standalone
     * @param bool $force
     *
     * @return \Doctrine\Common\Persistence\ObjectManager
     */
    public function getManager($standalone = false, $force = false);

    /**
     * Get console commands.
     *
     * @return \Symfony\Component\Console\Command\Command[]
     */
    public function getConsoleCommands();

    /**
     * Get console helper set.
     *
     * @return HelperSet
     */
    public function getConsoleHelperSet();
}
