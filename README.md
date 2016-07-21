[![MIT license](http://img.shields.io/badge/license-MIT-brightgreen.svg)](http://opensource.org/licenses/MIT)
[![Latest Stable Version](https://poser.pugx.org/cheprasov/php-memcached-tags/v/stable)](https://packagist.org/packages/cheprasov/php-memcached-tags)
[![Total Downloads](https://poser.pugx.org/cheprasov/php-memcached-tags/downloads)](https://packagist.org/packages/cheprasov/php-memcached-tags)

# MemcachedTags v1.0.3 for PHP >= 5.5

## About
MemcachedTags for PHP is a mechanism for adding tags to keys in Memcached. It is very useful, if you need to select or delete some keys by tags. And tags are really useful for group invalidation.

## Main features
- Data modification functions such as delete/add/set use [Locks](https://github.com/cheprasov/php-memcached-lock) to prevent losing data.
- MemcachedTags does not affect original keys. It creates own keys for service tags.

## Usage

```php
<?php
require ('./vendor/autoload.php');
use MemcachedTags\MemcachedTags;

// Example 1. Create new Instance

$Memcached = new \Memcached();
$Memcached->addServer('127.0.0.1', '11211');

$MemcachedTags = new MemcachedTags($Memcached);

// Example 2. Adding some tags to key

// some test data
$Memcached->set('user:1', '{"name": "Alexander", "sex": "m", "country": "UK",     "city": "London"}');
$Memcached->set('user:2', '{"name": "Irina",     "sex": "f", "country": "UK",     "city": "London"}');
$Memcached->set('user:3', '{"name": "Ilya",      "sex": "m", "country": "Russia", "city": "Petersburg"}');
$Memcached->set('user:4', '{"name": "Dima",      "sex": "m", "country": "Russia", "city": "Murmansk"}');
$Memcached->set('user:5', '{"name": "Dom",       "sex": "m", "country": "UK",     "city": "London"}');

// Add tags to keys

$MemcachedTags->addTagsToKeys(['city:London', 'country:UK'], ['user:1', 'user:2', 'user:5']);
$MemcachedTags->addTagsToKeys(['city:Murmansk', 'country:Russia'], 'user:4');
$MemcachedTags->addTagsToKeys(['city:Petersburg', 'country:Russia'], 'user:3');

$MemcachedTags->addTagsToKeys('sex:m', ['user:1', 'user:3', 'user:4', 'user:5']);
$MemcachedTags->addTagsToKeys('sex:f', 'user:2');

$MemcachedTags->addTagsToKeys('all', ['user:1','user:2', 'user:3', 'user:4', 'user:5']);

// or you can create key with tags

$MemcachedTags->setKeyWithTags('user:1', 'Alexander', ['country:UK', 'city:London', 'sex:m', 'all']);

// Example 3. Get keys by tags

// Get users with tag <all>
var_dump(
    $MemcachedTags->getKeysByTag('all')
);
//    array(2) {
//      [0]=> string(6) "user:1"
//      [1]=> string(6) "user:2"
//      [2]=> string(6) "user:3"
//      [3]=> string(6) "user:4"
//      [4]=> string(6) "user:5"
//    }

// Get users with tag <country:UK>
var_dump(
    $MemcachedTags->getKeysByTag('country:UK')
);
//    array(2) {
//      [0]=> string(6) "user:1"
//      [1]=> string(6) "user:2"
//      [2]=> string(6) "user:5"
//    }

// Get users with tag <city:Petersburg> OR <city:Murmansk>
var_dump(
    $MemcachedTags->getKeysByTags(['city:Petersburg', 'city:Murmansk'], MemcachedTags::COMPILATION_OR)
);
//    array(2) {
//      [0]=> string(6) "user:3"
//      [1]=> string(6) "user:4"
//    }

// Get users with tags <country:UK> AND <sex:m>
var_dump(
    $MemcachedTags->getKeysByTags(['country:UK', 'sex:m'], MemcachedTags::COMPILATION_AND)
);
//    array(3) {
//      [0]=> string(6) "user:1"
//      [1]=> string(6) "user:5"
//    }

// Get users with tag <country:UK> AND WITHOUT <sex:m>
var_dump(
    $MemcachedTags->getKeysByTags(['country:UK', 'sex:m'], MemcachedTags::COMPILATION_XOR)
);
//    array(3) {
//      [0]=> string(6) "user:2"
//    }

// Example 4. Delete keys by tags

// Delete keys with tag <city:Murmansk>
var_dump(
    $MemcachedTags->deleteKeysByTag('city:Murmansk')
);
// int(1) - Count of deleted keys

// Delete keys with tag <city:London> WITHOUT <sex:f>
var_dump(
    $MemcachedTags->deleteKeysByTags(['city:London', 'sex:f'], MemcachedTags::COMPILATION_XOR)
);
// int(2) - Count of deleted keys

```

## Methods

#### MemcachedTags :: __construct ( `\Memcached` **$Memcached** , `array` **$config** = null )
---
Create a new instance of MemcachedTags.

##### Method Parameters

1. \Memcached **$Memcached** - Instance of [Memcached](http://php.net/manual/en/book.memcached.php)
2. array **$config**, default = null
    * `prefix` - is a prefix for service keys in Memcached storage, like namespace.
    * `separator` - special char(s) that , by default `||`. It is service parameter for the gluing of tags to the Memcached. This value should not use in the name tags or keys.

##### Example

```php
$Lock = new MemcachedTags($Memcached);
// or
$Lock = new MemcachedTags($Memcached, [
    'prefix' => 'myTag',
    'separator' => '<;>',
]);

```


#### `bool` MemcachedTags :: addTagsToKeys ( `string|string[]` **$tags** , `string|string[]` **$keys** )
---
Adds each key specified tags. Returns `true` on success or `false` on failure.

##### Method Parameters

1. string|string[] **$tags** - Tag or list of tags that will be added to each key.
2. string|string[] **$keys** - Existing keys in Memcached for tags

##### Example

```php
$MemcachedTags->addTagsToKeys(['city:London', 'country:UK'], ['user:1', 'user:2', 'user:5']);
$MemcachedTags->addTagsToKeys(['city:Murmansk', 'country:Russia'], 'user:4');
$MemcachedTags->addTagsToKeys(['big', 'red'], 'apple');
$MemcachedTags->addTagsToKeys(['green', 'tasty'], 'orange');
```


#### `int` MemcachedTags :: deleteKey ( `string` **$key** )
---
Delete key and update dependent tags. Returns count of deleted keys (0 or 1).

##### Method Parameters
1. string **$key** - Name of key.

##### Example

```php
$MemcachedTags->deleteKey('user:1');
```


#### `int` MemcachedTags :: deleteKeys ( `string[]` **$keys** )
---
Delete keys and update dependent tags. Returns count of deleted keys.

##### Method Parameters
1. string[] **$keys** - List of keys.

##### Example

```php
$MemcachedTags->deleteKey(['user:1', 'user:2']);
```

#### `int` MemcachedTags :: deleteTag ( `string` **$tag** )
---
Delete tag. Keys will be not affected. Returns count of deleted tags. (0 or 1)

##### Method Parameters
1. string **$tag** - Name of tag.

##### Example

```php
$MemcachedTags->deleteTag('big');
```


#### `int` MemcachedTags :: deleteTags ( `string[]` **$tags** )
---
Delete several tags. Keys will be not affected. Returns count of deleted tags.

##### Method Parameters
1. string[] **$tags** - List of tags

##### Example

```php
$MemcachedTags->deleteTags(['big', 'tasty', 'old']);
```


#### `int` MemcachedTags :: deleteKeysByTag ( `string` **$tag** )
---
Delete keys by tag. Returns count of deleted keys.

##### Method Parameters
1. string **tag** - Name of tag.

##### Example

```php
$MemcachedTags->deleteKeysByTag('city:London');
// or
$MemcachedTags->deleteKeysByTag('sql');
```


#### `int` MemcachedTags :: deleteKeysByTags ( `string[]` **$tags** [, `int` **$compilation** = MemcachedTags::COMPILATION_ALL] )
---
Delete keys by several tags. Returns count of deleted keys.

##### Method Parameters
1. string[] **tags** - List of tags
2. int **$compilation**, default = MemcachedTags::COMPILATION_ALL - The method of combining tags.
    * `MemcachedTags::COMPILATION_ALL` - The same as MemcachedTags::COMPILATION_OR.
    * `MemcachedTags::COMPILATION_AND` - Delete keys that have every tags.
    * `MemcachedTags::COMPILATION_OR` - Delete keys that have any tags.
    * `MemcachedTags::COMPILATION_XOR` - Delete keys containing tag1 that are not have any of the other tags.

##### Example

```php
// Delete all apples and oranges
$MemcachedTags->deleteKeysByTags(['apple', 'oranges']);

// Delete only big oranges
$MemcachedTags->deleteKeysByTags(['big', 'oranges'], MemcachedTags::COMPILATION_AND);

// Delete all orange expect big oranges
$MemcachedTags->deleteKeysByTags(['oranges', 'big'], MemcachedTags::COMPILATION_XOR);

```


#### `string[]` MemcachedTags :: getKeysByTag ( `string` **$tag** )
---
Returns a list of keys with tag.

##### Method Parameters
1. string **tag** - Name of tag.

##### Example

```php
$MemcachedTags->getKeysByTag('big');
// or
$MemcachedTags->getKeysByTag('red');
```


#### `string[]|array` MemcachedTags :: getKeysByTags ( `string[]` **$tags** [, `int` **$compilation** = MemcachedTags::COMPILATION_ALL] )
---
Returns a list of keys by several tags.

##### Method Parameters
1. string[] **tags** - List of tags.
2. int **$compilation**, default = MemcachedTags::COMPILATION_ALL - The method of combining tags.
    * MemcachedTags::COMPILATION_ALL - Returns array with keys for every tag. `array(tag1 => [key1, key2], ...)`
    * MemcachedTags::COMPILATION_AND - Returns a list of keys that have every tags.
    * MemcachedTags::COMPILATION_OR - Returns a list of keys that have any tags.
    * MemcachedTags::COMPILATION_XOR - Returns a list of keys containing tag1 that are not have any of the other tags.

##### Example

```php
// Get all apples and oranges
$MemcachedTags->getKeysByTags(['apple', 'oranges']);

// Get all apples or oranges
$MemcachedTags->getKeysByTags(['apple', 'oranges'], MemcachedTags::COMPILATION_OR);

// Get only big oranges
$MemcachedTags->getKeysByTags(['big', 'oranges'], MemcachedTags::COMPILATION_AND);

// Get all orange expect big oranges
$MemcachedTags->getKeysByTags(['oranges', 'big'], MemcachedTags::COMPILATION_XOR);
```


#### `string[]` MemcachedTags :: getTagsByKey ( `string` **$key** )
---
Returns list of tags or empty list.

##### Method Parameters
1. string **$key** - Key in Memcached.

##### Example

```php
$MemcachedTags->getTagsByKey('user:1');
```


#### `bool` MemcachedTags :: setKeyWithTags ( `string` **$key** , `string` **$value** , `string|string[]` **$tags** )
---
Set value and tags to key. Returns result as `bool`.

##### Method Parameters
1. string **$key** - The key under which to store the value.
2. string **$value** - The value to store.
3. string|string[] **$tags** - Tag or list of tags for the key.

##### Example

```php
$MemcachedTags->setKeyWithTags('user:1', 'Alexander', ['sex:m', 'city:London']);
```


#### `bool` MemcachedTags :: setKeysWithTags ( `array` **$items** , `string|string[]` **$tags** )
---
Set values and tags to several keys. Returns result as `bool`.

##### Method Parameters
1. string **$items** - An array of key/value pairs to store on the server.
3. string|string[] **$tags** - Tag or list of tags for the items.

##### Example

```php
$MemcachedTags->setKeysWithTags(['user:1' => 'Alexander', 'user:2' => 'Irina'], 'city:London');
```


## Installation

### Composer

Download composer:

    wget -nc http://getcomposer.org/composer.phar

and add dependency to your project:

    php composer.phar require cheprasov/php-memcached-tags

## Running tests

To run tests type in console:

    ./vendor/bin/phpunit ./test/

## Something doesn't work

Feel free to fork project, fix bugs and finally request for pull
