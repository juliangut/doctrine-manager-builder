<?php

/*
 * (c) 2016-2023 Julián Gutiérrez <juliangut@gmail.com>
 *
 * @license BSD-3-Clause
 * @link https://github.com/juliangut/doctrine-manager-builder
 */

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * @internal
 */
final class Version20230101000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    /**
     * @SuppressWarnings(PHPMD.ShortMethodName)
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function up(Schema $schema): void
    {
        // This up() migration is auto-generated, please modify it to your needs
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function down(Schema $schema): void
    {
        // This down() migration is auto-generated, please modify it to your needs
    }
}
