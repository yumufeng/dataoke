<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/12/5
 * Time: 15:32
 */

class cache
{
    private static $instance;

    private $prefix = 'tbk_tdk_';

    public function __construct()
    {
        $this->redis = new \Redis();
        $this->redis->connect("127.0.0.1", 6379);
    }

    public function set($key, $value, $expire = 360)
    {
        $innerdata = serialize($value);
        $this->redis->setex($this->getCacheKey($key), $expire, $innerdata);
    }

    public function get($key)
    {
        $outData = $this->redis->get($this->getCacheKey($key));
        return unserialize($outData);
    }

    /**
     * 获取实际的缓存标识
     * @access protected
     * @param  string $name 缓存名
     * @return string
     */
    protected function getCacheKey($name)
    {
        return $this->prefix . $name;
    }

    public static function getInstance()
    {
        if (empty(self::$instance)) {
            self::$instance = new static();
        }
        return self::$instance;
    }

}