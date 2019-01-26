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

use Common\Property;
use Exceptions\InvalidArgumentException;
use Exceptions\NotFoundException;
use Exceptions\UnconnectedException;
use Services\Config;
use Services\MemoryTable;

/**
 * WebSocket client
 *
 * Class WebSocket
 * @package Services
 */
class WebSocket
{
    public static $event        = null;
    public static $memory       = null;

    private static $host        = null;
    private static $port        = null;
    private static $client      = null;
    private static $handshake   = null;

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
            throw new NotFoundException('The swoole extension can not loaded.');
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
     * @throws \Exceptions\RequiredException
     */
    public static function start($option, Event $event) {

        self::initialize($option);
        self::$client = self::client($option);
        self::listen($option, $event);
        self::set($option);
        self::beforeStart();
        self::_connect($option);
    }

    /**
     * Open WebSocket client
     *
     * @param $option
     * @return \Swoole\Client
     * @throws UnconnectedException
     */
    private static function client($option) {
        $client = new \Swoole\Client(
            $option->set->socketType,
            $option->set->syncType
        );

        if ($client) {
            return $client;
        }

        throw new UnconnectedException('Open WebSocket Client: ' . $option->socketType . '|'. $option->syncType);
    }

    /**
     * Set the WebSocket Server behavior.
     *
     * @param $option
     */
    private static function set($option) {
        $set = $option->set;

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
     * Connect WebSocket server.
     * @param $option
     * @throws UnconnectedException
     */
    private static function _connect($option) {
        if (!self::$client->connect($option->set->host, $option->set->port, $option->set->timeout)) {
            throw new UnconnectedException('WebSocket Server: ' . self::$host . ':'. self::$port );
        }
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

        foreach ($option->listen as $key => $value) {
            $methodList = [ WebSocket::$event, $value ];

            if (is_callable($methodList)) {
                self::$client->on($key, $methodList);

            } else {
                throw new NotFoundException('Cannot call method: ' . $value . ' on class.');
            }
        }
    }

    /**
     * Before starting the WebSocket client to create memory table.
     *
     * @throws NotFoundException
     * @throws \Exceptions\RequiredException
     */
    public static function beforeStart() {

        $option = Config::memory();
        $table  = $option::get('table');
        $schema = $option::get('schema');

        self::$memory = new MemoryTable($table);

        foreach ($schema as $value) {
            self::$memory->column($value->name)->type($value->type)->size($value->size);
        }

        self::$memory->add();
    }

    public static function onPush($cli, $data) {
        $cli->push($data);
    }
}
