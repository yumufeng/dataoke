<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/12/5
 * Time: 14:41
 */

class requestHelper
{
    private $request;

    private $ua;

    public function init($request)
    {
        $this->request = $request;
        $this->ua = $request->header['user-agent'];
    }

    public function isMobile()
    {
        return preg_match('/(iphone|android|Windows\sPhone)/i', $this->ua);
    }

    public function isWeibo()
    {
        return preg_match('/Weibo/i', $this->ua);
    }

    public function isMicroMessenger()
    {
        return preg_match('/MicroMessenger/i', $this->ua);
    }

    public function isIPhone()
    {
        return preg_match('/iPhone/i', $this->ua);
    }

    public function isIPad()
    {
        return preg_match('/(iPad|)/i', $this->ua);
    }

}