<?php
/**
 * Webiny Hrc (https://github.com/Webiny/Hrc/)
 *
 * @copyright Copyright Webiny LTD
 */
namespace Webiny\Hrc\CacheStorage;

use MongoDB\BSON\UTCDatetime;
use MongoDB\Model\CollectionInfo;
use Webiny\Component\Mongo\Index\SingleIndex;

/**
 * Class Mongo
 * MongoDb cache storage.
 *
 * @package Webiny\Hrc\CacheStorage
 */
class Mongo implements CacheStorageInterface
{

    CONST collection = 'HrcCacheStorage';

    /**
     * @var \Webiny\Component\Mongo\Mongo
     */
    private $mongoInstance;

    /**
     * @var integer Remaining ttl of the current cache entry;
     */
    private $remainingTtl = 0;


    /**
     * Mongo constructor.
     *
     * @param \Webiny\Component\Mongo\Mongo $mongoInstance Webiny MongoDb connection instance.
     *
     */
    public function __construct($mongoInstance)
    {
        $this->mongoInstance = $mongoInstance;
    }

    /**
     * Read the cache for the given key.
     *
     * @param string $key Cache key
     *
     * @return string|bool Cache content, or bool false if the key is not found in the cache.
     */
    public function read($key)
    {
        $result = $this->mongoInstance->findOne(self::collection, ['key' => $key]);

        if (is_array($result) && isset($result['content'])) {
            $this->remainingTtl = $result['ttl']->toDateTime()->getTimestamp() - time();

            return $result['content'];
        }

        $this->remainingTtl = 0;
        return false;
    }

    /**
     * Save the given content into cache.
     *
     * @param string $key Cache key.
     * @param string $content Content that should be saved.
     * @param int    $ttl Cache time-to-live
     *
     * @return bool
     */
    public function save($key, $content, $ttl)
    {
        $this->mongoInstance->update(self::collection, ['key' => $key],
            ['$set' => ['key' => $key, 'ttl' => new UTCDatetime((time() + $ttl) * 1000), 'content' => $content]], ['upsert' => true]);

        return true;
    }

    /**
     * Purge (delete) the given key from cache.
     *
     * @param string $key Cache key that should be deleted.
     *
     * @return bool True if key was found and deleted, otherwise false.
     */
    public function purge($key)
    {
        $this->mongoInstance->findOneAndDelete(self::collection, ['key' => $key]);

        return true;
    }

    /**
     * Installs the required mongo collection for cache storage and creates the required indexes.
     *
     * @return bool
     */
    public function installCollections()
    {
        $collections = $this->mongoInstance->listCollections();

        foreach ($collections as $collection) {
            /* @var $collection CollectionInfo */
            if ($collection->getName() == self::collection) {
                return true;
            }
        }

        $this->mongoInstance->createCollection(self::collection);
        $this->mongoInstance->createIndex(self::collection, new SingleIndex('key', 'key', false, true));
        $this->mongoInstance->createIndex(self::collection, new SingleIndex('ttl', 'ttl', false, false, false, 0));

        return true;

    }

    /**
     * @return int Returns the remaining ttl of the matched cache rule.
     */
    public function getRemainingTtl()
    {
        return $this->remainingTtl;
    }
}