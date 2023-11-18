<?php

/*
 * (c) 2016-2023 Julián Gutiérrez <juliangut@gmail.com>
 *
 * @license BSD-3-Clause
 * @link https://github.com/juliangut/doctrine-manager-builder
 */

declare(strict_types=1);

namespace Jgut\Doctrine\ManagerBuilder;

use Doctrine\Common\Cache\Cache;
use Doctrine\Common\Cache\CacheProvider;
use Psr\Clock\ClockInterface;

class InMemoryCacheProvider extends CacheProvider
{
    /**
     * @var array<string, array{mixed, int|bool}>
     */
    private array $data = [];

    private int $hitsCount = 0;

    private int $missesCount = 0;

    private int $upTime;

    public function __construct(
        private ClockInterface $timeProvider,
    ) {
        $this->upTime = $timeProvider->now()
            ->getTimestamp();
    }

    protected function doFetch($key)
    {
        if (!$this->doContains($key)) {
            ++$this->missesCount;

            return false;
        }

        ++$this->hitsCount;

        return $this->data[$key][0];
    }

    protected function doContains($key): bool
    {
        if (!\array_key_exists($key, $this->data)) {
            return false;
        }

        $expiration = $this->data[$key][1];
        if (\is_int($expiration) && $expiration < $this->timeProvider->now()->getTimestamp()) {
            $this->doDelete($key);

            return false;
        }

        return true;
    }

    protected function doSave($key, $data, $lifeTime = 0): bool
    {
        $this->data[$key] = [$data, $lifeTime !== 0 ? $this->timeProvider->now()->getTimestamp() + $lifeTime : false];

        return true;
    }

    protected function doDelete($key): bool
    {
        unset($this->data[$key]);

        return true;
    }

    protected function doFlush(): bool
    {
        $this->data = [];

        return true;
    }

    /**
     * @return array{hits: int, misses: int, uptime: int, memory_usage: null, memory_available: null}
     */
    protected function doGetStats(): array
    {
        return [
            Cache::STATS_HITS => $this->hitsCount,
            Cache::STATS_MISSES => $this->missesCount,
            Cache::STATS_UPTIME => $this->upTime,
            Cache::STATS_MEMORY_USAGE => null,
            Cache::STATS_MEMORY_AVAILABLE => null,
        ];
    }
}
