<?php

/*
 * doctrine-manager-builder (https://github.com/juliangut/doctrine-manager-builder).
 * Doctrine2 managers builder.
 *
 * @license BSD-3-Clause
 * @link https://github.com/juliangut/doctrine-manager-builder
 * @author Julián Gutiérrez <juliangut@gmail.com>
 */

namespace Jgut\Doctrine\ManagerBuilder\Util;

use Doctrine\Common\Cache\ApcuCache;
use Doctrine\Common\Cache\ArrayCache;
use Doctrine\Common\Cache\MemcacheCache;
use Doctrine\Common\Cache\RedisCache;
use Doctrine\Common\Cache\XcacheCache;

/**
 * Doctrine cache builder.
 */
class CacheBuilder
{
    /**
     * Retrieve a newly created doctrine cache driver.
     *
     * @return ApcuCache|ArrayCache|MemcacheCache|RedisCache|XcacheCache
     */
    public static function build()
    {
        switch (true) {
            // @codeCoverageIgnoreStart
            case extension_loaded('apc'):
                $cacheDriver = new ApcuCache;
                break;

            case extension_loaded('xcache'):
                $cacheDriver = new XcacheCache;
                break;

            case extension_loaded('memcache'):
                $memcache = new \Memcache;
                $memcache->connect('127.0.0.1');

                $cacheDriver = new MemcacheCache;
                $cacheDriver->setMemcache($memcache);
                break;

            case extension_loaded('redis'):
                $redis = new \Redis();
                $redis->connect('127.0.0.1');

                $cacheDriver = new RedisCache;
                $cacheDriver->setRedis($redis);
                break;
            // @codeCoverageIgnoreEnd

            default:
                $cacheDriver = new ArrayCache;
        }

        return $cacheDriver;
    }
}
