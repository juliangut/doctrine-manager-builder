<?php

/*
 * (c) 2016-2024 Julián Gutiérrez <juliangut@gmail.com>
 *
 * @license BSD-3-Clause
 * @link https://github.com/juliangut/doctrine-manager-builder
 */

declare(strict_types=1);

namespace Jgut\Doctrine\ManagerBuilder\Tests\Mapping\Files\MongoDB\Annotation;

use DateTimeImmutable;
use Doctrine\ODM\MongoDB\Mapping\Annotations\Document;
use Doctrine\ODM\MongoDB\Mapping\Annotations\Field;
use Doctrine\ODM\MongoDB\Mapping\Annotations\Id;

/**
 * @Document
 */
class Message
{
    public function __construct(
        /**
         * @Id(type="string")
         */
        private string $identifier,
        /**
         * @Field(type="string")
         */
        private string $text,
        /**
         * @Field(type="date_immutable")
         */
        private DateTimeImmutable $postedAt,
    ) {}
}
