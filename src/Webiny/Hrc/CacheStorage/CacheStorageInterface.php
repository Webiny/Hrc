<?php
/**
 * Webiny Hrc (https://github.com/Webiny/Hrc/)
 *
 * @copyright Copyright Webiny LTD
 */
namespace Webiny\Hrc\CacheStorage;

/**
 * Interface CacheStorageInterface - every cache storage driver must implement this interface.
 *
 * @package Webiny\Hrc\CacheStorage
 */
interface CacheStorageInterface
{
    /**
     * Read the cache for the given key.
     * Note: on read, the storage should invalidate the cache if it has expired.
     *
     * @param string $key Cache key
     *
     * @return string|bool Cache content, or bool false if the key is not found in the cache.
     */
    public function read($key);

    /**
     * @return int Returns the remaining ttl of the matched cache rule.
     */
    public function getRemainingTtl();

    /**
     * Save the given content into cache.
     *
     * @param string $key     Cache key.
     * @param string $content Content that should be saved.
     * @param string $ttl     Cache time-to-live
     *
     * @return bool
     */
    public function save($key, $content, $ttl);

    /**
     * Purge (delete) the given key from cache.
     *
     * @param string $key Cache key that should be deleted.
     *
     * @return bool True if key was found and deleted, otherwise false.
     */
    public function purge($key);
}