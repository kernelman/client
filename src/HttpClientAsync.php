<?php
/**
 * Class WebSocket
 *
 * Author:  Kernel Huang
 * Mail:    kernelman79@gmail.com
 * Date:    1/11/19
 * Time:    5:47 PM
 */

namespace Client;

use Common\JsonFormat;
use Common\Property;
use Exceptions\InvalidArgumentException;
use Exceptions\NotFoundException;
use Exceptions\UnconnectedException;

/**
 * Http/WebSocket async client.
 * The async client dependent extension for Swoole.
 *
 * Class WebSocket
 * @package Services
 */
class HttpClientAsync
{
    public static $event    = null;
    public static $client   = null;
    public static $memory   = null;

    private static $host    = null;
    private static $port    = null;

    /**
     * Initialize
     *
     * @param $option
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    private static function initialize($option) {

        // Check swoole extension
        if (!extension_loaded('swoole')) {
            throw new NotFoundException('Swoole extension');
        }

        if (Property::nonExistsReturnNull($option, 'set') == null) {
            throw new InvalidArgumentException("The set property not found");
        }

        self::$host     = Property::nonExistsReturnNull($option->set, 'host');
        if (self::$host == null) {
            throw new InvalidArgumentException("The WebSocket client host settings not found");
        }

        self::$port     = Property::nonExistsReturnZero($option->set, 'port');
        if (self::$port == 0) {
            throw new InvalidArgumentException("The WebSocket client host settings not found");
        }
    }

    /**
     * Start WebSocket client
     *
     * @param $option
     * @param Event $event
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws UnconnectedException
     */
    public static function start($option, Event $event) {

        self::initialize($option);
        self::$client = self::client($option);
        self::set($option);
        self::listen($option, $event);
    }

    /**
     * Open WebSocket client
     *
     * @param $option
     * @return \swoole\http\client
     * @throws UnconnectedException
     */
    private static function client($option) {
        $client = new \swoole\http\client(
            $option->set->host,
            $option->set->port,
            null
        );

        if ($client) {
            return $client;
        }

        throw new UnconnectedException('WebSocket server: ' . $option->host . ':'. $option->port);
    }

    /**
     * Set the WebSocket Server behavior.
     *
     * @param $option
     */
    private static function set($option) {
        $set = $option->set;

        self::$client->setHeaders(['Trace-Id' => md5(time())]);
        self::$client->set(array(
            'open_eof_split'            => $set->eofSplit,
            'package_eof'               => $set->packageEof,
            'open_length_check'         => $set->lengthCheck,
            'package_max_length'        => $set->packageMaxLength,
            'package_length_type'       => $set->packageLengthType,
            'package_length_offset'     => $set->packageLengthOffset,
            'package_body_offset'       => $set->packageBodyOffset,
            'socket_buffer_size'        => $set->socketBufferSize,
        ));
    }

    /**
     * Listen method for event object
     *
     * @param $option
     * @param $event
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    private static function listen($option, $event) {
        self::$event = $event;

        if (!is_object(self::$event)) {
            throw new InvalidArgumentException('Event object not found.');
        }

        if (Property::nonExistsReturnNull($option, 'listen') == null) {
            throw new InvalidArgumentException("The listen property not found");
        }

        // Client listen method lists
        foreach ($option->listen as $key => $value) {
            $methodList = [ self::$event, $value ];

            if (is_callable($methodList)) {
                self::$client->on($key, $methodList);

            } else {
                throw new NotFoundException('Cannot call method: ' . $value . ' on class.');
            }
        }
    }

    /**
     * Push data to server
     *
     * @param string $path
     * @param $data
     * @param bool $format
     * @return mixed
     * @throws \Exceptions\UnFormattedException
     */
    public static function push(string $path, $data, $format = true) {
        if ($format) {
            self::$client->data = JsonFormat::boot($data);

        } else {

            self::$client->data = $data;
        }

        return self::$client->upgrade($path, function ($cli) {

            return $cli->push($cli->data);
        });
    }
}
