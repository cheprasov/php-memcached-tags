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

use InvalidArgumentException;
use Memcached;
use MemcachedLock\MemcachedLock;

class MemcachedTags implements TagsInterface {

    const VERSION = '1.0.5';

    const COMPILATION_ALL = 0;
    const COMPILATION_AND = 1;
    const COMPILATION_OR  = 2;
    const COMPILATION_XOR = 3;

    const PREFIX_LOCK = '_MemcachedLock';
    const PREFIX_TAG  = '_t_';
    const PREFIX_KEY  = '_k_';
    const TAGS_KEY    = '_tags_';

    /**
     * @var Memcached
     */
    protected $Memcached;

    /**
     * @var string
     */
    protected $prefix = 'tag';

    /**
     * @var string
     */
    protected $separator = '||';

    /**
     * @param Memcached $Memcached
     * @param array|null $config
     */
    public function __construct(Memcached $Memcached, array $config = null) {
        $this->Memcached = $Memcached;

        if (isset($config['prefix'])) {
            $this->prefix = $config['prefix'];
        }
        if (!empty($config['separator'])) {
            $this->separator = $config['separator'];
        }
    }

    /**
     * @param string|string[] $tag
     * @return string
     */
    protected function getKeyNameForTag($tag) {
        return $this->prefix . self::PREFIX_TAG . $tag;
    }

    /**
     * @param string[] $tags
     * @param bool $flip
     * @return array
     */
    protected function getKeyHashesForTags($tags, $flip = false) {
        $result = [];
        foreach ($tags as $tag) {
            $result[$tag] = $this->getKeyNameForTag($tag);
        }
        if ($flip) {
            return array_flip($result);
        }
        return $result;
    }

    /**
     * @param string $ktag
     * @return string
     */
    protected function getKeyNameForKey($ktag) {
        return $this->prefix . self::PREFIX_KEY . $ktag;
    }

    /**
     * @param string[] $keys
     * @param bool $flip
     * @return array
     */
    protected function getKeyHashesForKeys($keys, $flip = false) {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->getKeyNameForKey($key);
        }
        if ($flip) {
            return array_flip($result);
        }
        return $result;
    }

    /**
     * @param string $data
     * @return mixed
     */
    protected function decode($data) {
        return explode($this->separator, $data);
    }

    /**
     * @param mixed $data
     * @return string
     */
    protected function encode($data) {
        return implode($this->separator, $data);
    }

    /**
     * @param string $serialized
     * @param array $data
     * @return string
     */
    protected function addData($serialized, array $data) {
        if (!$serialized) {
            return $this->encode($data);
        } else {
            $str = $this->separator . $serialized . $this->separator;
            $add = '';
            foreach ($data as $datum) {
                $pos = strpos($str, $this->separator . $datum . $this->separator);
                if ($pos !== false) {
                    continue;
                }
                $add .= $this->separator . $datum;
            }
            return $serialized . $add;
        }
    }

    /**
     * @param string $serialized
     * @param array $data
     * @return string
     */
    protected function removeData($serialized, array $data) {
        if (!$serialized) {
            return null;
        } else {
            $data = array_map(function($d){
                return $this->separator . $d . $this->separator;
            }, $data);
            $result = str_replace($data, $this->separator, $this->separator . $serialized . $this->separator);
            if (!$result || $result === $this->separator) {
                return null;
            }
            $len = strlen($this->separator);
            return substr($result, $len, -$len);
        }
    }

    /**
     * @param string[] $tags
     * @param string[] $keys
     * @return bool
     */
    protected function _addTagsToKeys(array $tags, array $keys) {
        $count = 0;
        foreach ($tags as $tag) {
            $keyName = $this->getKeyNameForTag($tag);
            $serialized = $this->addData($this->Memcached->get($keyName), $keys);
            if ($this->Memcached->set($keyName, $serialized)) {
                ++$count;
            }
        }
        foreach ($keys as $key) {
            $keyName = $this->getKeyNameForKey($key);
            $serialized = $this->addData($this->Memcached->get($keyName), $tags);
            $this->Memcached->set($keyName, $serialized);
        }
        $this->_registerTags($tags);
        return $count === count($tags);
    }

    /**
     * @param string[] $keys
     * @return int
     */
    protected function _removeKeys($keys) {
        if ($tags = $this->getTagsByKeys($keys)) {
            $tags = array_values(call_user_func_array('array_merge', $tags));
        }
        $result = 0;
        foreach ($keys as $key) {
            if ($this->Memcached->delete($key)) {
                ++$result;
            }
            $this->Memcached->delete($this->getKeyNameForKey($key));
        }
        if (!$tags) {
            return $result;
        }
        foreach ($tags as $tag) {
            $keyName = $this->getKeyNameForTag($tag);
            $value = $this->removeData($this->Memcached->get($keyName), $keys);
            if (!$value) {
                $this->Memcached->delete($keyName);
            } else {
                $this->Memcached->set($keyName, $value);
            }
        }
        return $result;
    }

    /**
     * @param int $locktime
     * @param int $waittime
     * @return MemcachedLock
     * @throws \MemcachedLock\Exception\LockHasAcquiredAlreadyException
     */
    protected function createLock($locktime = 2, $waittime = 3) {
        $Lock = new MemcachedLock($this->Memcached, $this->prefix. self::PREFIX_LOCK);
        $Lock->acquire($locktime, $waittime);
        return $Lock;
    }

    /**
     * @param array $hash
     * @return array
     */
    protected function getDecodedMulti(array $hash) {
        $res = $this->Memcached->getMulti($hash);
        $data = [];
        foreach ($hash as $key => $val) {
            if (isset($res[$val]) && ($value = $this->decode($res[$val]))) {
                $data[$key] = $value;
            } else {
                $data[$key] = [];
            }
        }
        return $data;
    }

    /**
     * @inheritdoc
     */
    public function addTagsToKeys($tags, $keys) {
        $tags = is_array($tags) ? array_unique($tags) : [$tags];
        $keys = is_array($keys) ? array_unique($keys) : [$keys];
        $Lock = $this->createLock();
        return $this->_addTagsToKeys($tags, $keys);
    }

    /**
     * @inheritdoc
     */
    public function deleteKey($key) {
        return $this->deleteKeys((array) $key);
    }

    /**
     * @inheritdoc
     */
    public function deleteKeys(array $keys) {
        $Lock = $this->createLock();
        return $this->_removeKeys($keys);
    }

    /**
     * @inheritdoc
     */
    public function deleteTag($tag) {
        return $this->deleteTags((array) $tag);
    }

    /**
     * @inheritdoc
     */
    public function deleteTags(array $tags) {
        $Lock = $this->createLock();
        $keys = $this->getKeysByTags($tags, self::COMPILATION_OR);
        foreach ($keys as $key) {
            $keyName = $this->getKeyNameForKey($key);
            $value = $this->removeData($this->Memcached->get($keyName), $tags);
            if (!$value) {
                $this->Memcached->delete($keyName);
            } else {
                $this->Memcached->set($keyName, $value);
            }
        }
        $result = 0;
        // Because <deleteMulti> has strange behavior
        foreach ($tags as $tag) {
            if ($this->Memcached->delete($this->getKeyNameForTag($tag))) {
                ++$result;
            }
        }
        $this->_updateRegisty($tags);
        return $result;
    }

    /**
     * @inheritdoc
     */
    public function deleteKeysByTag($tag) {
        return $this->deleteKeysByTags((array) $tag);
    }

    /**
     * @inheritdoc
     */
    public function deleteKeysByTags(array $tags, $compilation = MemcachedTags::COMPILATION_ALL) {
        $Lock = $this->createLock();
        if ($compilation === self::COMPILATION_ALL) {
            $compilation = self::COMPILATION_OR;
        }
        $keys = $this->getKeysByTags($tags, $compilation);
        if (is_null($keys)){
            return false;
        }
        return $this->_removeKeys($keys);
    }

    /**
     * @inheritdoc
     */
    public function getKeysByTag($tag) {
        if ($value = $this->Memcached->get($this->getKeyNameForTag($tag))) {
            if ($keys = $this->decode($value)) {
                return $keys;
            }
        }
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getKeysByTags(array $tags, $compilation = MemcachedTags::COMPILATION_ALL) {
        $data = $this->getDecodedMulti($this->getKeyHashesForTags($tags));
        if (count($data) < 2) {
            if (!$data) {
                return [];
            }
            return $compilation == MemcachedTags::COMPILATION_ALL ? $data : reset($data);
        }
        switch ($compilation) {
            case self::COMPILATION_ALL:
                return $data;
            case self::COMPILATION_AND:
                return array_values(call_user_func_array('array_intersect', $data));
            case self::COMPILATION_OR:
                return array_values(array_unique(call_user_func_array('array_merge', $data)));
            case self::COMPILATION_XOR:
                return array_values(call_user_func_array('array_diff', $data));
            default:
                throw new InvalidArgumentException('Unknown compilation type '. $compilation);
        }
    }

    /**
     * @inheritdoc
     */
    public function getTagsByKey($key) {
        if ($value = $this->Memcached->get($this->getKeyNameForKey($key))) {
            if ($tags = $this->decode($value)) {
                return $tags;
            }
        }
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getTagsByKeys(array $keys) {
        return $this->getDecodedMulti($this->getKeyHashesForKeys($keys));
    }

    /**
     * @inheritdoc
     */
    public function setKeyWithTags($key, $value, $tags) {
        $Lock = $this->createLock();
        if ($this->Memcached->set($key, $value)) {
            return $this->_addTagsToKeys((array) $tags, (array) $key);
        }
        return false;
    }

    /**
     * @inheritdoc
     */
    public function setKeysWithTags(array $items, $tags) {
        $Lock = $this->createLock();
        if ($this->Memcached->setMulti($items)) {
            return $this->_addTagsToKeys((array) $tags, array_keys($items));
        }
        return false;
    }
    
    /**
     * @param array $tags
     * @return bool
     */
    protected function _registerTags(array $tags){
        $tagsKey = $this->getKeyNameForKey(self::TAGS_KEY);
        $storedTags = $this->Memcached->get($tagsKey);
        if ($storedTags){
            $storedTags = array_unique(array_merge($storedTags,$tags));
        } else {
            $storedTags = $tags;
        }
        if ($this->Memcached->set($tagsKey, $storedTags)){
            return true;
        }
        return false;
    }
    
    /**
     * 
     * @param array $tags
     * @return bool
     */
    protected function _updateRegisty(array $tags){
        $tagsKey = $this->getKeyNameForKey(self::TAGS_KEY);
        $storedTags = $this->Memcached->get($tagsKey);
        if ($storedTags){
            $storedTags = array_diff($storedTags,$tags);
            if ($this->Memcached->set($tagsKey, $storedTags)){
                return true;
            }
        } 
        return false;
    }
    
    /**
     * 
     * @return array $tags
     */
    public function getTags(){
        $tagsKey = $this->getKeyNameForKey(self::TAGS_KEY);
        $tags    = $this->Memcached->get($tagsKey);
        return $tags;
    }

} 