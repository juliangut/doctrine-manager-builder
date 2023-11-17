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

namespace Jgut\Doctrine\ManagerBuilder\Tests\Mapping\Files\MongoDB\Attribute;

use DateTimeImmutable;
use Doctrine\ODM\MongoDB\Mapping\Annotations\Document;
use Doctrine\ODM\MongoDB\Mapping\Annotations\Field;
use Doctrine\ODM\MongoDB\Mapping\Annotations\Id;

#[Document]
class Message
{
    public function __construct(
        #[Id(type: 'string')]
        private string $identifier,
        #[Field(type: 'string')]
        private string $text,
        #[Field(type: 'date_immutable')]
        private DateTimeImmutable $postedAt,
    ) {}
}
