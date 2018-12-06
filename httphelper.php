<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/12/5
 * Time: 14:41
 */

class httphelper
{
    private $appId;
    private $key;
    /**
     * @var swoole_http_request
     */
    private $request;
    /**
     * @var swoole_http_response
     */
    private $response;
    public $httpCode = 200;
    private $proxyVersion = 10;

    public function init($config, swoole_http_request $request, swoole_http_response $response)
    {
        $this->appId = $config['appId'];
        $this->key = $config['appKey'];
        $this->request = $request;
        $this->response = $response;
    }

    public function destory()
    {
        $this->request = $this->response = null;
        $this->httpCode = 200;
    }

    public function getHtml(string $url, $requestUri, array $param, string $method = "GET", $isAjax = null, $re_try = true)
    {

        $begin_time = @microtime(true);
        if (strpos($requestUri, 'auth') !== false) {
            $url .= '/auth';
        }
        $refer = isset($this->request->header['referer']) ? $this->request->header['referer'] : '';
        $ua = isset($this->request->header['user-agent']) ? $this->request->header['user-agent'] : '';
        $curl_time = 5;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, $curl_time);
        curl_setopt($ch, CURLOPT_USERAGENT, $ua);
        curl_setopt($ch, CURLOPT_REFERER, $refer);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $header = array(
            'APPID: ' . $this->appId,
            'APPKEY: ' . $this->key,
            'PROXY-VERSION: ' . $this->proxyVersion,
            'CMS-HOST: ' . $this->request->header['host'],
            'DOCUMENT-URL: ' . '/',
            'REQUEST-URL: ' . $requestUri,
        );
        $_isAjax = false;
        if ($isAjax) {
            $_isAjax = true;
        }
        if (!$_isAjax && $isAjax === null) {
            $_isAjax = $this->getIsAjaxRequest();
        }
        if ($_isAjax) {
            $header[] = 'X-Requested-With: XMLHttpRequest';
        }
        $clientIp = $this->get_real_ip();
        if (!empty($clientIp)) {
            $header[] = 'CLIENT-IP: ' . $clientIp;
            $header[] = 'X-FORWARDED-FOR: ' . $clientIp;
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);

        $cookie = $this->request->cookie ?: [];
        if (is_array($cookie)) {
            $str = '';
            foreach ($cookie as $k => $v) {
                $str .= $k . '=' . $v . '; ';
            }
            $cookie = $str;
        }

        if (!empty($cookie)) {
            curl_setopt($ch, CURLOPT_COOKIE, $cookie);
        }
        if (strtolower($method) == 'post') {
            curl_setopt($ch, CURLOPT_POST, TRUE);
            if ($param) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($param));
            }
            curl_setopt($ch, CURLOPT_URL, $url);
        } else {
            curl_setopt($ch, CURLOPT_HTTPGET, TRUE);
            if ($param) {
                $urlInfo = parse_url($url);
                $q = array();
                if (isset($urlInfo['query']) && !empty($urlInfo['query'])) {
                    parse_str($urlInfo['query'], $q);
                }
                $q = array_merge($q, $param);
                $cUrl = sprintf('%s://%s%s%s%s',
                    $urlInfo['scheme'],
                    $urlInfo['host'],
                    isset($urlInfo['port']) ? ':' . $urlInfo['port'] : '',
                    isset($urlInfo['path']) ? $urlInfo['path'] : '',
                    count($q) ? '?' . http_build_query($q) : '');
                curl_setopt($ch, CURLOPT_URL, $cUrl);
            } else {
                curl_setopt($ch, CURLOPT_URL, $url);
            }
        }
        try {
            $r = curl_exec($ch);
            $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $header = mb_substr($r, 0, $headerSize);
            $r = mb_substr($r, $headerSize);
            $this->httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
        } catch (Exception $e) {
            return $re_try == true ? $this->getHtml($url, $requestUri, $param, $method, $isAjax, false) : '';
        }

        unset($ch);
        $headers = explode("\r\n", $header);

        if ($this->httpCode != 200) {
            if ($this->httpCode !== 302) {
                $this->setHttpResponseCode($this->httpCode);
            }
        }
        $expires = time() + 300;
        foreach ($headers as $h) {
            $h = trim($h);
            if (empty($h) || preg_match('/^(HTTP|Connection|EagleId|Server|X\-Powered\-By|Date|Transfer\-Encoding|Content)/i', $h)) {
                continue;
            }
            if (strpos($h, 'expires:') !== false) {
                $temp_arr = explode(':', $h);
                if (!empty($temp_arr[1]) && is_numeric(trim($temp_arr[1]))) {
                    $expires = intval(trim($temp_arr[1]));
                }
            }
            if (strpos($h, 'Cookie') !== false) {
                $h = explode(':', $h);
                if (!empty($h[1])) {
                    $h = explode('=', $h[1]);
                    if (!empty($h[0]) && !empty($h[1])) {
                        $this->response->cookie(trim($h[0]), trim($h[1]), $expires);
                    }
                }
            } else {
                $h = explode(':', $h, 2);
                $this->response->header($h[0], $h[1]);
                if ($h[0] == 'Location') {
                    $this->response->redirect($h[1], $this->httpCode);
                    return false;
                }
            }
        }
        if ($re_try === false) {
            $Dtk_Cache_Check = 1;
        } else {
            $Dtk_Cache_Check = 0;
        }
        $end_time = @microtime(true);
        $this->response->header('Dtk-Cache-Check-' . $Dtk_Cache_Check, ($end_time - $begin_time));
        if ($this->httpCode != 0 && $this->httpCode != 500 && $this->httpCode != 200 && $this->httpCode != 302) {
            return false;
        }
        return ($re_try == true && empty($r)) ? $this->getHtml($url, $requestUri, $param, $method, $isAjax, false) : $r;
    }

    public function setHttpResponseCode($code)
    {
        switch ($code) {
            case 100:
                $text = 'Continue';
                break;
            case 101:
                $text = 'Switching Protocols';
                break;
            case 200:
                $text = 'OK';
                break;
            case 201:
                $text = 'Created';
                break;
            case 202:
                $text = 'Accepted';
                break;
            case 203:
                $text = 'Non-Authoritative Information';
                break;
            case 204:
                $text = 'No Content';
                break;
            case 205:
                $text = 'Reset Content';
                break;
            case 206:
                $text = 'Partial Content';
                break;
            case 300:
                $text = 'Multiple Choices';
                break;
            case 301:
                $text = 'Moved Permanently';
                break;
            case 302:
                $text = 'Moved Temporarily';
                break;
            case 303:
                $text = 'See Other';
                break;
            case 304:
                $text = 'Not Modified';
                break;
            case 305:
                $text = 'Use Proxy';
                break;
            case 400:
                $text = 'Bad Request';
                break;
            case 401:
                $text = 'Unauthorized';
                break;
            case 402:
                $text = 'Payment Required';
                break;
            case 403:
                $text = 'Forbidden';
                break;
            case 404:
                $text = 'Not Found';
                break;
            case 405:
                $text = 'Method Not Allowed';
                break;
            case 406:
                $text = 'Not Acceptable';
                break;
            case 407:
                $text = 'Proxy Authentication Required';
                break;
            case 408:
                $text = 'Request Time-out';
                break;
            case 409:
                $text = 'Conflict';
                break;
            case 410:
                $text = 'Gone';
                break;
            case 411:
                $text = 'Length Required';
                break;
            case 412:
                $text = 'Precondition Failed';
                break;
            case 413:
                $text = 'Request Entity Too Large';
                break;
            case 414:
                $text = 'Request-URI Too Large';
                break;
            case 415:
                $text = 'Unsupported Media Type';
                break;
            case 500:
                $text = 'Internal Server Error';
                break;
            case 501:
                $text = 'Not Implemented';
                break;
            case 502:
                $text = 'Bad Gateway';
                break;
            case 503:
                $text = 'Service Unavailable';
                break;
            case 504:
                $text = 'Gateway Time-out';
                break;
            case 505:
                $text = 'HTTP Version not supported';
                break;
            default:
                $text = '';
                break;
        }
        $protocol = (isset($this->request->server['server_protocol']) ? $this->request->server['server_protocol'] : 'HTTP/1.0');
        $this->response->header($protocol . ' ' . $code, $text);
    }

    public function getIsAjaxRequest()
    {
        $value = isset($this->request->header['x-requested-with']) ?: null;
        $result = 'xmlhttprequest' == strtolower($value) ? true : false;
        return $result;
    }

    public function get_real_ip($type = 0, $adv = true)
    {
        $type = $type ? 1 : 0;
        static $ip = null;

        if (null !== $ip) {
            return $ip[$type];
        }

        $httpAgentIp = 'x-real-ip';

        if ($httpAgentIp && isset($this->request->server[$httpAgentIp])) {
            $ip = $this->request->server[$httpAgentIp];
        } elseif ($adv) {
            $httpAgentIp = strtolower($httpAgentIp);
            if (isset($this->request->header[$httpAgentIp])) {
                $ip = $this->request->header[$httpAgentIp];
            } else if (isset($this->request->server['http_x_forwarded_for'])) {
                $arr = explode(',', $this->request->server['http_x_forwarded_for']);
                $pos = array_search('unknown', $arr);
                if (false !== $pos) {
                    unset($arr[$pos]);
                }
                $ip = trim(current($arr));
            } elseif (isset($this->request->server['http_client_ip'])) {
                $ip = $this->request->server['http_client_ip'];
            } elseif (isset($this->request->server['remote_addr'])) {
                $ip = $this->request->server['remote_addr'];
            }
        } elseif (isset($this->request->server['remote_addr'])) {
            $ip = $this->request->server['remote_addr'];
        }

        // IP地址类型
        $ip_mode = (strpos($ip, ':') === false) ? 'ipv4' : 'ipv6';

        // IP地址合法验证
        if (filter_var($ip, FILTER_VALIDATE_IP) !== $ip) {
            $ip = ('ipv4' === $ip_mode) ? '0.0.0.0' : '::';
        }

        // 如果是ipv4地址，则直接使用ip2long返回int类型ip；如果是ipv6地址，暂时不支持，直接返回0
        $long_ip = ('ipv4' === $ip_mode) ? sprintf("%u", ip2long($ip)) : 0;

        $ip = [$ip, $long_ip];

        return $ip[$type];
    }
}