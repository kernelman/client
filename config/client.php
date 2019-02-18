<?php
/**
 * Created by IntelliJ IDEA.
 * User: kernel Huang
 * Email: kernelman79@gmail.com
 * Date: 2018/11/21
 * Time: 1:57 PM
 */

$service    = 'Logs\\Services\\';
$model      = 'Logs\\Models\\';

return (object)array(

    // Linux/Mac操作系统的日志文件绝对路径, 可根据服务器系统配置环境自定义
    'root'      => '/tmp/logs/',        // 日志根目录
    'name'      => 'Client',            // 业务系统名/日志目录名/日志DB名
    'work'      => 'sync',              // sync or async, 日志写入方式(同步/异步)
    'store'     => $service . 'File',   // file or elk or mongodb, 日志存储类型(文件/ELK/Mongodb)
    'models'    => $model,              // Models类命名空间
    'size'      => 2000,                // 设置日志文件大小阈值, 单位为MB, 日志文件大小超过这个设置将重新创建
    'env'       => 'dev',               // 默认为pro，pro是生产环境，dev是开发环境

    'module'    => (object)[            // 业务模块名/二级目录名/日志表名/
        'http'    =>  'http',
    ]
);
