<?php
/**
 * Created by IntelliJ IDEA.
 * User: kernel
 * Date: 2018/11/15
 * Time: 11:35 AM
 */

namespace Tests;

use Services\Config;
use Client\RedisSync;

class RedisSyncTest extends TestCase {

    /**
     * 测试get方法
     *
     * @throws \Exceptions\AuthorizationException
     * @throws \Exceptions\InvalidArgumentException
     * @throws \Exceptions\UnSelectedException
     * @throws \Exceptions\UnconnectedException
     * @throws \Exceptions\UnexpectedException
     */
    public function testGet() {
        $key    = 'cache_get';
        RedisSync::$config = Config::redis()::get('sync');

        $cache  = RedisSync::get($key);
        $this->assertFalse($cache, 'The key not found: ' . $key);
    }

    /**
     * 测试set方法
     *
     * @throws \Exceptions\AuthorizationException
     * @throws \Exceptions\InvalidArgumentException
     * @throws \Exceptions\UnSelectedException
     * @throws \Exceptions\UnconnectedException
     * @throws \Exceptions\UnexpectedException
     */
    public function testSet() {
        $key        = 'cache_set';
        $content    = 'cache';
        $cache      = RedisSync::set($key, $content);
        $this->assertTrue($cache, 'The key set failed: ' . $key);
    }

    /**
     * 测试scan方法
     *
     * @throws \Exceptions\AuthorizationException
     * @throws \Exceptions\InvalidArgumentException
     * @throws \Exceptions\UnSelectedException
     * @throws \Exceptions\UnconnectedException
     * @throws \Exceptions\UnexpectedException
     */
    public function testScan() {
        $prefix = 'cache_';
        $total  = RedisSync::scan($prefix, 50);
        $this->assertEquals(1, count($total));
    }

    /**
     * 测试删除指定KEY前缀的所有键值
     *
     * @throws \Exceptions\AuthorizationException
     * @throws \Exceptions\InvalidArgumentException
     * @throws \Exceptions\UnSelectedException
     * @throws \Exceptions\UnconnectedException
     * @throws \Exceptions\UnexpectedException
     */
    public function testDeleteAll() {
        $prefix = 'cache_';
        $total  = RedisSync::scan($prefix, 50);
        $keys   = [];

        foreach ($total as $value) {
            $keys[] = substr($value, strlen(RedisSync::$prefix)); // 去除env文件定义的前缀字符再删除
        }

        $delete = RedisSync::deleteAll($keys);
        $this->assertTrue($delete);
    }
}
