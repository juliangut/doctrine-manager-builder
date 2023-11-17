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

namespace Jgut\Doctrine\ManagerBuilder\Tests\Mapping\Files\Relational\Annotation;

use DateTimeImmutable;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;

/**
 * @Entity
 */
class Message
{
    public function __construct(
        /**
         * @Id
         *
         * @GeneratedValue(strategy="NONE")
         *
         * @Column(type="string")
         */
        private string $identifier,
        /**
         * @Column(type="string", length=140)
         */
        private string $text,
        /**
         * @Column(type="datetimetz_immutable")
         */
        private DateTimeImmutable $postedAt,
    ) {}
}
