<?php
/**
 * Class Events
 *
 * Author:  Kernel Huang
 * Mail:    kernelman79@gmail.com
 * Date:    1/12/19
 * Time:    7:17 PM
 */

namespace Client;

class Event
{

    public static function onStart() {
    }

    public static function onConnect($client) {
    }

    public static function onReceive($client, $data) {
    }

    public static function onError() {
    }

    public static function onMessage($serv, $frame) {
    }

    public static function onClose($service) {
    }
}
