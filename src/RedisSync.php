<?php
/**
 * Created by IntelliJ IDEA.
 * User: kernel
 * Date: 2018/11/13
 * Time: 1:20 PM
 */

/**
 * Redis sync client.
 *
 * Class RedisCache
 * @package Modules\Db\RedisCache
 */
namespace Client;

use Common\Property;
use Exceptions\AuthorizationException;
use Exceptions\UnconnectedException;
use Exceptions\UnexpectedException;
use Exceptions\InvalidArgumentException;
use Exceptions\UnSelectedException;

class RedisSync {

    private static $_db         = null;
    private static $_redis      = null;
    private static $host        = null;
    private static $port        = null;
    private static $password    = null;
    private static $lifetime    = 0;    // 缓存Key过期时间: 默认0为不设置Key过期时间, 单位为秒
    private static $persistent  = true; // 默认开启长链接
    private static $serializer  = null; // 使用php的序列化和反序列化进行缓存数据处理

    public  static $prefix      = null; // Key默认前缀名
    public  static $config      = null; // 不为空使用自定义配置文件对象, 为空则使用第三方框架配置: Laravel

    /**
     * RedisCache constructor.
     *
     * @throws InvalidArgumentException
     * @throws UnexpectedException
     */
    public static function initialize() {

        if (!extension_loaded('redis')) {
            throw new UnexpectedException('The Redis extension can not loaded.');
        }

        if (self::$config != null) {
            self::useConfig();

        } else {
            self::useApiConfig();
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    private static function useApiConfig() {

        // Default the DB host.
        self::$host     = env('REDIS_HOST', null);
        if (self::$host == null) {
            throw new InvalidArgumentException("The Redis host settings not found");
        }

        // Default the DB port.
        self::$port     = (int)env('REDIS_PORT', null);
        if (self::$port == 0) {
            throw new InvalidArgumentException("The Redis port settings not found");
        }

        self::$password     = env('REDIS_PASSWORD', null);
        if (self::$password == null) {
            throw new InvalidArgumentException("The Redis password settings not found");
        }

        // Default the key lifetime for DB.
        self::$lifetime     = (int)env('REDIS_LIFETIME', self::$lifetime);
        if (self::$lifetime == 0) {
            throw new InvalidArgumentException("The Redis lifetime settings must and must not be 0");
        }

        // 使用php的序列化和反序列化进行缓存数据处理
        self::$serializer  = \Redis::SERIALIZER_PHP;

        // Use default DB.
        self::$_db          = (int)env('REDIS_DB', 0);

        // False is disable pConnect, true is enable pConnect, default enable pConnect.
        self::$persistent   = env('REDIS_PERSISTENT', self::$persistent);

        self::$prefix       = env('REDIS_PREFIX', self::$prefix);
    }

    /**
     * @throws InvalidArgumentException
     */
    private static function useConfig() {

        // Default the DB host.
        self::$host     = Property::nonExistsReturnNull(self::$config, 'REDIS_HOST');
        if (self::$host == null) {
            throw new InvalidArgumentException("The Redis host settings not found");
        }

        // Default the DB port.
        self::$port     = (int)Property::nonExistsReturnZero(self::$config, 'REDIS_PORT');
        if (self::$port == 0) {
            throw new InvalidArgumentException("The Redis port settings not found");
        }

        // Access DB password.
        self::$password     = Property::nonExistsReturnNull(self::$config, 'REDIS_PASSWORD');
        if (self::$password == null) {
            throw new InvalidArgumentException("The Redis password settings not found");
        }

        // Default the key lifetime for DB.
        self::$lifetime     = (int)Property::nonExistsReturnZero(self::$config, 'REDIS_LIFETIME');
        if (self::$lifetime == 0) {
            throw new InvalidArgumentException("The Redis lifetime settings must and must not be 0");
        }

        // 使用php的序列化和反序列化进行缓存数据处理
        self::$serializer  = \Redis::SERIALIZER_PHP;

        // Use default DB.
        self::$_db          = (int)Property::nonExistsReturnZero(self::$config, 'REDIS_DB');

        // False is disable pConnect, true is enable pConnect, default enable pConnect.
        self::$persistent   = Property::isExists(self::$config, 'REDIS_PERSISTENT', self::$persistent);

        self::$prefix       = Property::nonExistsReturnNull(self::$config, 'REDIS_PREFIX');
    }

    /**
     * Create connection to redis
     *
     * @throws AuthorizationException
     * @throws InvalidArgumentException
     * @throws UnSelectedException
     * @throws UnconnectedException
     * @throws UnexpectedException
     */
    private static function _connect() {
        $redis = null;

        self::initialize();
        $redis = new \Redis();

        // 开启长连接
        if (self::$persistent) {
            $success = $redis->pconnect(self::$host, self::$port);
            if (!$success) {
                throw new UnconnectedException('Could not pconnect to the Redis server ' . self::$host . ':' . self::$port);
            }

        // 开启短连接
        } else {
            $success = $redis->connect(self::$host, self::$port);
            if (!$success) {
                throw new UnconnectedException('Could not connect to the Redis server ' . self::$host . ':' . self::$port);
            }
        }

        // 设置Key前缀
        $success = $redis->setOption(\Redis::OPT_PREFIX, self::$prefix);
        if (!$success) {
            throw new InvalidArgumentException('Name: ' . 'Redis::OPT_PREFIX' . 'Value: ' . self::$prefix);
        }

        // 开启缓存数据的序列化及反序列化
        $success = $redis->setOption(\Redis::OPT_SERIALIZER, self::$serializer);
        if (!$success) {
            throw new InvalidArgumentException('Name: ' . 'Redis::OPT_SERIALIZER' . 'Value: ' . self::$serializer);
        }

        // 开启Scan多次扫描
        $success = $redis->setOption(\Redis::OPT_SCAN, 'Redis::SCAN_RETRY');
        if (!$success) {
            throw new InvalidArgumentException('Name: ' . 'Redis::SCAN_RETRY' . 'Value: ' . self::$serializer);
        }

        // 验证密码
        $success = $redis->auth(self::$password);
        if (!$success) {
            throw new AuthorizationException('With the Redis server');
        }

        // 选择Redis数据库(0 - 16)
        $success = $redis->select(self::$_db);
        if (!$success) {
            throw new UnSelectedException('Redis server selected database failed, the db name: ' . self::$_db);
        }

        self::$_redis = $redis;
    }

    /**
     * Returns a cached content
     *
     * @param string $key
     * @param int $db
     * @return mixed
     * @throws AuthorizationException
     * @throws InvalidArgumentException
     * @throws UnSelectedException
     * @throws UnconnectedException
     * @throws UnexpectedException
     */
    public static function get(string $key, int $db = null) {
        $redis  = null;
        $redis  = self::$_redis;

        if ($db != null) {
            self::$_db = $db;
            self::_connect();

            $redis = self::$_redis;
        }

        if (!is_object($redis)) {
            self::_connect();
            $redis = self::$_redis;
        }

        return $redis->get($key);
    }

    /**
     * Call redis set method
     *
     * @param string $key
     * @param $content
     * @param int $lifetime
     * @param null $db
     * @param bool $neverExpire
     * @return mixed
     * @throws AuthorizationException
     * @throws InvalidArgumentException
     * @throws UnSelectedException
     * @throws UnconnectedException
     * @throws UnexpectedException
     */
    public static function set(string $key, $content, int $lifetime = 0, $db = null, $neverExpire = false) {
        $redis = null;
        $redis = self::$_redis;

        if ($db != null) {
            self::$_db = $db;
            self::_connect();

            $redis = self::$_redis;
        }

        if (!is_object($redis)) {
            self::_connect();
            $redis = self::$_redis;
        }

        if ($lifetime == 0) {
            $lifetime = self::$lifetime;
        }

        if (!$neverExpire) {
            return $redis->set($key, $content, $lifetime);

        } else {
            return $redis->set($key, $content);
        }
    }

    /**
     * Get the key lifetime
     *
     * @param string $key
     * @param int $db
     * @return mixed
     * @throws AuthorizationException
     * @throws InvalidArgumentException
     * @throws UnSelectedException
     * @throws UnconnectedException
     * @throws UnexpectedException
     */
    public static function ttl(string $key, int $db = 0) {
        $redis      = null;
        $redis      = self::$_redis;
        self::$_db  = $db;

        if (!is_object($redis)) {
            self::_connect();
            $redis = self::$_redis;
        }

        return $redis->ttl($key);
    }

    /**
     * Stores cached content to collection
     *
     * @param string $key
     * @param $content
     * @param int $db
     * @return mixed
     * @throws AuthorizationException
     * @throws InvalidArgumentException
     * @throws UnSelectedException
     * @throws UnconnectedException
     * @throws UnexpectedException
     */
    public static function save(string $key, $content, $db = 0) {
        $redis      = null;
        $redis      = self::$_redis;
        self::$_db  = $db;

        if (!is_object($redis)) {
            self::_connect();
            $redis = self::$_redis;
        }

        if (is_array($content) || is_object($content)) {
            return $redis->sAddArray($key, $content);
        }

        return $redis->sAdd($key, $content);
    }

    /**
     * Get collection members
     *
     * @param string $key
     * @param int $db
     * @return array
     * @throws AuthorizationException
     * @throws InvalidArgumentException
     * @throws UnSelectedException
     * @throws UnconnectedException
     * @throws UnexpectedException
     */
    public static function getMembers(string $key, int $db = 0) : array {
        $redis      = null;
        $redis      = self::$_redis;
        self::$_db  = $db;

        if (!is_object($redis)) {
            self::_connect();
            $redis = self::$_redis;
        }

        return $redis->sMembers($key);
    }

    /**
     * Set an expiration time
     *
     * @param string $key
     * @param int $lifetime
     * @param int $db
     * @return mixed
     * @throws AuthorizationException
     * @throws InvalidArgumentException
     * @throws UnSelectedException
     * @throws UnconnectedException
     * @throws UnexpectedException
     */
    public static function expire(string $key, int $lifetime = 0, int $db = 0) {
        $redis      = null;
        $redis      = self::$_redis;
        self::$_db  = $db;

        if (!is_object($redis)) {
            self::_connect();
            $redis = self::$_redis;
        }

        return $redis->expire($key, $lifetime);
    }

    /**
     * Set if not exists
     *
     * @param string $key
     * @param null $content
     * @param int|null $lifetime
     * @param int $db
     * @return mixed
     * @throws AuthorizationException
     * @throws InvalidArgumentException
     * @throws UnSelectedException
     * @throws UnconnectedException
     * @throws UnexpectedException
     */
    public static function setNx(string $key, $content = null, int $lifetime = 0, int $db = 0) {

        $redis     = null;
        $redis     = self::$_redis;
        self::$_db = $db;

        if (!is_object($redis)) {
            self::_connect();
            $redis = self::$_redis;
        }

        if ($lifetime == 0) {
            $lifetime = self::$lifetime;
        }

        return $redis->set($key, $content, ['NX', 'EX' => $lifetime]);
    }

    /**
     * Call redis getSet method
     *
     * @param string $key
     * @param $content
     * @param int $db
     * @return mixed
     * @throws AuthorizationException
     * @throws InvalidArgumentException
     * @throws UnSelectedException
     * @throws UnconnectedException
     * @throws UnexpectedException
     */
    public static function getSet(string $key, $content , int $db = 0) {

        $redis      = null;
        $redis      = self::$_redis;
        self::$_db  = $db;

        if (!is_object($redis)) {
            self::_connect();
            $redis = self::$_redis;
        }

        return $redis->getSet($key, $content);
    }

    /**
     * Delete a value from the cache by its key
     *
     * @param $key
     * @param int $db
     * @return mixed
     * @throws AuthorizationException
     * @throws InvalidArgumentException
     * @throws UnSelectedException
     * @throws UnconnectedException
     * @throws UnexpectedException
     */
    public static function delete(string $key, int $db = 0) {

        $redis      = null;
        $redis      = self::$_redis;
        self::$_db  = $db;

        if (!is_object($redis)) {
            self::_connect();
            $redis = self::$_redis;
        }

        return $redis->del($key);
    }

    /**
     * Query the existing cached keys
     *
     */
    /**
     * @param null $prefix
     */
    public static function queryKeys($prefix = null) {}

    /**
     * Checks if cache exists and it isn't expired
     *
     * @param string $key
     * @param int $db
     * @return mixed
     * @throws AuthorizationException
     * @throws InvalidArgumentException
     * @throws UnSelectedException
     * @throws UnconnectedException
     * @throws UnexpectedException
     */
    public static function exists(string $key, $db = 0) {
        $redis      = null;
        $redis      = self::$_redis;
        self::$_db  = $db;

        if (!is_object($redis)) {
            self::_connect();
            $redis = self::$_redis;
        }

        return $redis->exists($key);
    }

    /**
     * Call redis publish method
     *
     * @param string $channel
     * @param string $content
     * @param int $db
     * @return mixed
     * @throws AuthorizationException
     * @throws InvalidArgumentException
     * @throws UnSelectedException
     * @throws UnconnectedException
     * @throws UnexpectedException
     */
    public static function publish(string $channel, string $content, int $db = 0) {
        $redis      = null;
        $redis      = self::$_redis;
        self::$_db  = $db;

        if (!is_object($redis)) {
            self::_connect();
            $redis = self::$_redis;
        }

        return $redis->publish($channel, $content);
    }

    /**
     * Incremental scanning all key
     *
     * @param string $prefix
     * @param int $count
     * @param int $db
     * @return array
     * @throws AuthorizationException
     * @throws InvalidArgumentException
     * @throws UnSelectedException
     * @throws UnconnectedException
     * @throws UnexpectedException
     */
    public static function scan(string $prefix, int $count, int $db = 0) : array {
        $redis  = null;
        $redis  = self::$_redis;

        if ($db != null) {
            self::$_db = $db;
            self::_connect();

            $redis = self::$_redis;
        }

        if (!is_object($redis)) {
            self::_connect();
            $redis = self::$_redis;
        }

        $it     = NULL;
        $total  = [];

        while ($keys = $redis->scan($it, self::$prefix . $prefix . '*', $count)) {
            foreach ($keys as $value) {
                $total[] = $value;
            }
        }

        return $total;
    }

    /**
     * Delete all elements of the keys array for redis
     *
     * @param array $keys
     * @param int $db
     * @return bool
     * @throws AuthorizationException
     * @throws InvalidArgumentException
     * @throws UnSelectedException
     * @throws UnconnectedException
     * @throws UnexpectedException
     */
    public static function deleteAll(array $keys, int $db = 0) {
        $redis  = null;
        $redis  = self::$_redis;

        if ($db != null) {
            self::$_db = $db;
            self::_connect();

            $redis = self::$_redis;
        }

        if (!is_object($redis)) {
            self::_connect();
            $redis = self::$_redis;
        }

        $counts = count($keys);
        $remove = $redis->delete($keys);

        return $remove === $counts;
    }
}
