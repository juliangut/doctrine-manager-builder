<?php

/*
 * (c) 2016-2023 Julián Gutiérrez <juliangut@gmail.com>
 *
 * @license BSD-3-Clause
 * @link https://github.com/juliangut/doctrine-manager-builder
 */

declare(strict_types=1);

namespace Jgut\Doctrine\ManagerBuilder\Tests\Mapping\Files\Relational\Attribute;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;

#[Entity]
class Message
{
    public function __construct(
        #[Id]
        #[GeneratedValue(strategy: 'NONE')]
        #[Column(type: Types::STRING)]
        private string $identifier,
        #[Column(type: Types::STRING, length: 140)]
        private string $text,
        #[Column(type: Types::DATETIMETZ_IMMUTABLE)]
        private DateTimeImmutable $postedAt,
    ) {}
}
