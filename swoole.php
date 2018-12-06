<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/12/5
 * Time: 14:41
 */

date_default_timezone_set('Asia/Shanghai');

require 'httphelper.php';
require 'requestHelper.php';
require 'cache.php';


$http = new swoole_http_server("0.0.0.0", 9501);
$http->set(array(
    'worker_num' => 2,
    'daemonize' => 1,
    'document_root' => getcwd().'/public',
    'enable_static_handler' => true,
    'max_request' => 10000,
));
$http->on('request', function (swoole_http_request $request, swoole_http_response $response) {

    $cookie = $request->cookie ?: [];
    $post = $request->post ?: [];
    $server = $request->server;
    $requestUri = $server['request_uri'];
    $requestMethod = $server['request_method'];
    if ($requestUri == '/favicon.ico') {
        return $response->end('');
    }
    $requestHelper = new requestHelper();
    $requestHelper->init($request);

    $cachehelper = cache::getInstance();
    $query_string = isset($request->server['query_string']) ? $request->server['query_string'] : '';
    if (!empty($query_string)) {
        $requestUri .= '?' . $query_string;
    }
    $key = md5($requestUri . $requestHelper->isMobile() . $requestHelper->isIPad() . $requestHelper->isIPhone() . $requestHelper->isMicroMessenger() . $requestHelper->isWeibo());
    if ($requestMethod == 'GET') {
        $cacheData = $cachehelper->get($key);
        if ($cacheData !== false && !empty($cacheData)) {
            return $response->end($cacheData);
        }
    }
    $config = require 'config.php';
    $httpHelper = new httpHelper();
    $httpHelper->init($config, $request, $response);
    $html = $httpHelper->getHtml($config['host'], $requestUri, $requestMethod == 'POST' ? $post : array(), $requestMethod);
    if (($html === false)) {
        return;
    }
    if (($requestMethod == 'GET') && $httpHelper->httpCode == 200 && !empty($html)) {
        $cache_check = isset($cookie['cache_check']) ? $cookie['cache_check'] : null;
        $expire = empty($cache_check) ? 360 : 600;
        $response->header('Dtk-Cache-Check-time', $expire);
        $cachehelper->set($key, $html, $expire);
    }
    return $response->end($html);
});

$http->start();
