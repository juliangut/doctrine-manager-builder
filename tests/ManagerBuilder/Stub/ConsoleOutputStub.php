<?php

/*
 * (c) 2016-2024 Julián Gutiérrez <juliangut@gmail.com>
 *
 * @license BSD-3-Clause
 * @link https://github.com/juliangut/doctrine-manager-builder
 */

declare(strict_types=1);

namespace Jgut\Doctrine\ManagerBuilder\Tests\Stub;

use Symfony\Component\Console\Output\NullOutput;

/**
 * @internal
 */
class ConsoleOutputStub extends NullOutput
{
    private string $output = '';

    public function writeln($messages, $options = self::OUTPUT_NORMAL): void
    {
        $this->write($messages, true, $options);
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function write($messages, $newline = false, $options = self::OUTPUT_NORMAL): void
    {
        $messages = (array) $messages;

        foreach ($messages as $message) {
            $this->output .= $message . ($newline ? \PHP_EOL : '');
        }
    }

    public function getOutput(): string
    {
        return $this->output;
    }
}
