<?php
namespace Test\Integration;

use MemcachedTags\MemcachedTags;
use Parallel\Parallel;
use Parallel\Storage\MemcachedStorage;

class MemcachedTagsParallelTest extends \PHPUnit_Framework_TestCase {

    /**
     * @var \Memcached
     */
    protected static $Memcached;

    protected function getMemcached() {
        $MC = new \Memcached();
        // MEMCACHED_TEST_SERVER defined in phpunit.xml
        $server = explode(':', MEMCACHED_TEST_SERVER);
        $MC->addServer($server[0], $server[1]);
        return $MC;
    }

    public function test_parallel() {
        $MC = $this->getMemcached();
        $MC->flush();
        unset($MC);

        $Storage = new MemcachedStorage(
            ['servers'=>[explode(':', MEMCACHED_TEST_SERVER)]]
        );
        $Parallel = new Parallel($Storage);

        $start = microtime(true) + 2;

        // 1st operation
        $Parallel->run('foo', function() use ($start) {
            $MemcachedTags = new MemcachedTags($MC = $this->getMemcached(), 'tag_');
            while (microtime(true) < $start) {
                // wait for start
            }
            for ($i = 1; $i <= 300; ++$i) {
                echo '+';
                ob_flush();
                $MC->set('key_1_'. $i, $i);
                $MC->set('key_2_'. $i, $i);
                $MemcachedTags->addTags('tag1', 'key_1_'. $i);
                $MemcachedTags->addTags(['tag2', 'tag3'], 'key_2_'. $i);
            }
            return 1;
        });

        $MemcachedTags = new MemcachedTags($MC = $this->getMemcached(), 'tag_');

        while (microtime(true) < $start) {
            // wait for start
        }
        $count1 = 0;
        for ($i = 1; $i <= 300; ++$i) {
            echo '-';
            ob_flush();
            $count1 += $MemcachedTags->deleteKeysByTags('tag1');
            $MemcachedTags->deleteKeysByTags(['tag2', 'tag3']);
            usleep(100);
        }

        $Parallel->wait('foo');

        $keys1 = $MemcachedTags->getKeysByTags('tag1');
        $keys2 = $MemcachedTags->getKeysByTags('tag2');
        $keys3 = $MemcachedTags->getKeysByTags('tag3');

        var_dump($keys1);
        var_dump(count($keys1));
        var_dump($count1);

        for ($i = 1; $i <= 300; ++$i) {
            $value = $MC->get('key_1_'. $i);
            if ($value) {
                if (!in_array('key_1_' . $i, $keys1)) {
                    var_dump('LOST');
                    var_dump($value);
                }
            } else {
                if (in_array('key_1_' . $i, $keys1)) {
                    var_dump('EST');
                    var_dump($value);
                }
            }
            //$this->assertSame($value ? true : false, in_array('key_1_' . $i, $keys1));

            //$value = $MC->get('key_2_'. $i);
            //$this->assertSame($value ? true : false, in_array('key_2_' . $i, $keys2));

            //$value = $MC->get('key_3_'. $i);
            //$this->assertSame($value ? true : false, in_array('key_3_' . $i, $keys3));
        }

        $MC->flush();
    }

}
