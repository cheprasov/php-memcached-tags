<?php
/**
 * This file is part of MemcachedTags.
 * git: https://github.com/cheprasov/php-memcached-tags
 *
 * (C) Alexander Cheprasov <cheprasov.84@ya.ru>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace MemcachedTags;

interface TagsInterface {

    /**
     * Add tag(s) to key(s)
     * @param string|string[] $tags
     * @param string|string[] $keys
     * @return int Returns count of created/updated tags.
     */
    public function addTagsToKeys($tags, $keys);

    /**
     * Delete key and update dependent tags
     * @param string $key
     * @return int Returns count of deleted key
     */
    public function deleteKey($key);

    /**
     * Delete keys and update dependent tags
     * @param string[] $keys
     * @return int Returns count of deleted key
     */
    public function deleteKeys(array $keys);

    /**
     * Delete tag. Keys will be not affected.
     * @param string $tag
     * @return int Returns count of deleted tags
     */
    public function deleteTag($tag);

    /**
     * Delete tags. Keys will be not affected.
     * @param string[] $tags
     * @return int Returns count of deleted tags
     */
    public function deleteTags(array $tags);

    /**
     * Delete all keys by tag
     * @param string|string[] $tag
     * @return int Returns count of deleted keys.
     */
    public function deleteKeysByTag($tag);

    /**
     * Delete all keys by tags
     * @param string[] $tags
     * @param int $compilation
     * @return int Returns count of deleted keys.
     */
    public function deleteKeysByTags(array $tags, $compilation);

    /**
     * Get keys by tag.
     * @param string $tag
     * @return string[] Returns list of keys.
     */
    public function getKeysByTag($tag);

    /**
     * Get keys by tags.
     * @param string[] $tags
     * @param int $compilation
     * @return string[] Returns list of keys.
     */
    public function getKeysByTags(array $tags, $compilation);

    /**
     * Get tags by key.
     * @param string $key
     * @return string[] Returns list of tags.
     */
    public function getTagsByKey($key);

    /**
     * Set value and tags to key.
     * @param string $key
     * @param string $value
     * @param string|string[] $tags
     * @param int $timeout
     * @return bool
     */
    public function setKeyWithTags($key, $value, $tags, int $timeout);

    /**
     * Set values and tags to several keys.
     * @param array $items
     * @param string|string[] $tags
     * @param int $timeout
     * @return bool
     */
    public function setKeysWithTags(array $items, $tags, int $timeout);

}
