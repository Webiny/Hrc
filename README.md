HRC - Http Request Cache
=====

This is a cache management system that provides control mechanisms around your cache. Caches are tied to the incoming HTTP request.
In practice that means that you can create caches based on a combination of a request path, cookies, sent headers, query strings and some custom callbacks.

### ... but I use memcache (or redis)

Sure, but that's not a cache management system, that's just a storage ... managing cache requires a lot of knowledge and 
 it's not an easy thing to do (especially with memcache).
 
A much smarter person than me, once stated: 

> There are only two hard things in Computer Science: cache invalidation and naming things.

> -- Phil Karlton

You can still use memcache, redis or any other cache storage system you want, just create a driver for it and hook it into Hrc. 
(see the guide down below)

## Installation

```
composer require webiny/hrc
```

Requires PHP 7.0 or later.

## How it works

```php
// define cache rules
$cacheRules = [
    'FooBar' => [
        'Ttl'   => 60,
        'Tags'  => ['cacheAll'],
        'Match' => [
            'Url' => '*'
        ]
    ]
];

// where and how to store cache files
$cacheStorage = new Webiny\Hrc\CacheStorage\FileSystem('/path/to/cache/folder/');
// where and how to keep a record of all stored cache files
$indexStorage = new Webiny\Hrc\IndexStorage\FileSystem('/path/to/index/folder');
// create Hrc instance
$hrc = new Hrc($cacheRules, $cacheStorage, $indexStorage);
// store something into cache
$hrc->save('entryName', 'some content');
// retrieve something from cache
$data = $hrc->read('entryName'); // some content
```

### 1. Define a set of cache rules

Cache rules define how and if something can be cached. 
For example, a simple cache rule looks something like this:

```php
$cacheRules = [
    'SimpleRule'    => [
        'Ttl'   => 60,
        'Tags'  => ['simple', 'rule'],
        'Match' => [
            'Url' => '/some/*'
        ]
    ],
];
```

`Ttl` (Time-to-live) defines for how long that entry should be kept in cache.

`Tags` are used to tag the content, so you can invalidate it easier.

`Match` is a set of match criterias that the rule needs to satisfy in order for you to be able to store content. They 
also define what is used to create a cache key.

#### Match criteria

There are several things you can use in your match criteria:
* Url path
* Query string
* Request headers
* Cookies
* Custom callback


Match options:
- `true`: the parameter needs to be present, but only the parameter name, and not its value, will be used in the cache key.
- `false`: the parameter must not be preset for the rule to match.
- `*`: the parameter needs to exists and needs to have some value, and it's value will be used in the cache key.
- `?`: the parameter is optional, if it exists it's value is used in the cache key.
- any PHP regex `preg_match` standard
- any fixed string for exact match


Here are some match examples:

```php
$mockRules = [
    'AdvancedMatch' => [
        'Ttl'   => 100,
        'Tags'  => ['advanced', 'one'],
        'Match' => [
            'Url'      => '/simple/url/([\w]+)/page/([\d]+)/(yes|no)/',
            'Cookie'   => [
                'X-Cart'          => 'cart value (\d+) currency ([\w]{2})',
                'X-CacheByCookie' => 'yes'
            ],
            'Header'   => [
                'X-Cache-Me'     => 'foo (\w+)',
                'X-Cache-Header' => '*'
            ],
            'Query'    => [
                'Cache'   => true,
                'foo'     => ?
            ],
            'Callback' => [
                'Webiny\Hrc\UnitTests\CacheRules\MockCallbacks::returnValue'
            ]
        ]
    ]
];
```

The `Callback` section is used to invoke a custom callback which is basically just an extension to the match rules. 
 The callback method should return a value, that value will be used to build the cache key. If the callback returns boolean `false`, 
 the rule will not match the request.

A callback method takes two parameters, `Webiny\Hrc\Request` and `Webiny\Hrc\CacheRules\CacheRule\CacheRule`:

```php
class MockCallbacks
{
    public static function returnTrue(Webiny\Hrc\Request $r, Webiny\Hrc\CacheRules\CacheRule\CacheRule $cr)
    {
        // do you thing here
    }
}
```

Although not recommended, instead of strictly specifying match options for `Cookie`, `Header` and `Query`, you can put a `*`, to simply take all received parameters (eg. all query parameters), and build a cache key for any variation in a request.

```php
$mockRules = [
    'GetAllParameters' => [
        'Ttl'   => 86400,
        'Tags'  => ['global', 'all'],
        'Match' => [
            'Url'      => '/match/all',
            'Cookie'   => '*',
            'Header'   => '*',
            'Query'   => '*'
        ]
    ]
];
```
 
### 2. Cache storage

Hrc is built in a way that you can store cache using any storage you want, from memcache to mongodb. By default
we provide a filesystem storage and MongoDb. If you write a driver for any other storage mechanism, send over a pull request, and we will gladly merge it.

Creating a storage drive is rather simple, just create a class and implement `Webiny\Hrc\CacheStorage\CacheStorageInterface`.
You have 3 simple methods to implement, and you're done.

### 3. Index storage

Index storage is used to store additional cache information, you can look at it as a combination of cache metadata
and taxonomy. The index is mainly used to achieve more possibilities around cache invalidation and faster cache invalidation times.

By default we provide a filesystem cache index  and a MongoDb cache index, to create a custom-one, just implement `Webiny\Hrc\IndexStorage\IndexStorageInterface`.

#### Mongo storage and index

If you plan to use the `Mongo` cache storage and cache index, make sure you run the `installCollections` method prior to using the driver,
otherwise the required indexes won't be created and the performance will be slow.

```php
$this->cacheStorage->installCollections();
$this->indexStorage->installCollections();
```

You need to run this only once. Alternative approach is to create the two collections and indexes manually:
 - `HrcCacheStorage` collection should have the following indexes
    - `key` => unique index on the `key` field, sparse should be false
    - `ttl` => index on ttl field with expireAfterSeconds set to 0 seconds
 - `HrcIndexStorage` collection should have the following indexes:
    - `key` => unique index on the `key` field, sparse should be false
    - `tags` => index on the `tags` field
    - `ttl` => index on ttl field with expireAfterSeconds set to 0 seconds

### 4. Matching a cache rule

When you call the `read` or `save` method, if you don't provide the name of the cache rule, the class will run through all
of defined cache rules, and will select the **first rule that matched the request**.
However if you provide the cache rule name, the cache rule match patterns still must match, but the check will only be done on that particular rule.
By providing a cache rule name, you can match multiple cache rules inside the same HTTP request.

```php
// use the first matched cache rule
$hrc->save('entryName', 'some content');
$data = $hrc->read('entryName');

// use fooBar cache rule
$hrc->save('block 122', 'some content', 'fooBar');
$data = $hrc->read('block 122');
```

### 5. Callbacks

There are two main callback events supported: 
- `beforeSave(SavePayload)` 
- `afterSave(SavePayload)`
- `beforeRead(ReadPayload)`
- `afterRead(ReadPayload)`

To register a callback for those events create a class that implements `\Webiny\Hrc\EventCallbackInterface`. You will have to implement all the callback methods. Both save methods receive 1 parameter, which is `SavePayload` instance. This instance contains all the relevant data about the current cache entry that is about to be created, or has been created. Similar is for the read methods, they receive the `ReadPayload` instance, which gives you access to the current cache entry as well as the option to set the purge flag, so that the content is actually purged and not retrieved from the database. 

The callback methods don't need to return anything, but since the `SavePayload` instance is an object, on `beforeSave` you can use it to manipulate your cache entry, by changing the cache content, adding or removing tags and similar. On `afterSave` callback you will get back the same object, but this is just a confirmation that the object was successfully saved.

 ```php
 // your Hrc instance
 $hrc = new Hrc($cacheRules, $cacheStorage, $indexStorage);
 
 // my callback handler -> must implement \Webiny\Hrc\EventCallbackInterface
 $handler = new MyCallbackHandler();
 
 // register callbacks
 $hrc->registerCallback($handler); 
 ```

## Cache purge

There are couple of ways you can purge cache:

### Purge by cache key

When you save a cache entry, the save method will return a cache key, using that key, you can purge that particular entry:

```php
// save the cache and get back the cache key
$key = $hrc->save('entryName', 'some content');
// purge that cache entry
$hrc->purgeByCacheKey($key);
```

### Purge by tag

Every cache rule has one or more tags associated. Using the same tags, you can purge multiple cache entries.
**Note:** when providing multiple tags, only entries that match ALL tags will be purged, or to put it in different words, 
we use logical AND condition between the tags.

```php
$hrc->purgeByTag(['tag1', 'tag2']);
```

### Purge by request

There is a flag that you can set, so that every matched cache entry, inside the current request, will automatically be purged.

```php
// first set the purge flag to true
$hrc->setPurgeFlag(true);

// once the flag is set to true, every `read` call, that has a cache hit, will actually do a purge
$hrc->read('entryName');
```

Another way of doing this is by sending a special request header inside your request. That header is `X-HRC-Purge` and it just 
needs to be defined, there is no need to set any value for it. Sending that header has the same effect as setting the purge flag to true, 
but this way you don't need to set the flag, and it's actually only valid for that particular request. 

#### Security

Based on the previous section, you might think that there is a big risk in having that header, because everybody can purge the cache and hit your
database/backend on every request...and that's true, but there's a built-in mechanism to prevent that. 
You can set a `control key`, so only requests that have a valid key can actually purge via the header.

```php
// set the control header
$hrc->setControlKey('someSecretString');
```

Once the control header is set, you now need to send a `X-HRC-Control-Key` request header, containing the same value. Only if both values
match, the purge request will be executed.


## Bugs and improvements

Just report them under issues, or even better, send a pull request :)

## License

MIT

## Resources

To run unit tests, you need to use the following command:
```
$ cd path/to/Webiny/Hrc/
$ composer install
$ phpunit
```
