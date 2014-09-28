<?php

namespace Common\Api;

class Curl {
    private $_ch;
    public $_cookies = array();
    private $_url;
    public function __construct() {
        $this->_ch = curl_init();
        curl_setopt($this->_ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($this->_ch, CURLOPT_HEADER, 1);
    }
    public function post($url, $postData) {
        if (!$this->_parse_url($url)) {
            return false;
        }
        curl_setopt($this->_ch, CURLOPT_URL, $url);
        curl_setopt($this->_ch, CURLOPT_POST, true);
        curl_setopt($this->_ch, CURLOPT_POSTFIELDS, $postData);
        $this->_attach_cookie($url);
        $response = curl_exec($this->_ch);
        if ($response === false) {
            return false;
        }
        $ret = $this->_parse_http_response($response);
        return $ret['body'];
    }
    public function get($url) {
        if (!$this->_parse_url($url)) {
            return false;
        }
        curl_setopt($this->_ch, CURLOPT_URL, $url);
        curl_setopt($this->_ch, CURLOPT_POST, false);
        // curl_setopt($this->_ch, CURLOPT_HTTPHEADER, array('Expect:'));
        $this->_attach_cookie($url);
        $response = curl_exec($this->_ch);
        if ($response === false) {
            return false;
        }
        $ret = $this->_parse_http_response($response);
        return $ret['body'];
    }

    private function _parse_url($url) {
        $_url = parse_url($url);
        if (!isset($_url['host']) || !isset($_url['scheme']) || (strtolower($_url['scheme']) != 'http' && strtolower($_url['scheme']) != 'https')) {
            return false;
        }
        $_url['path'] = isset($_url['path']) ? dirname($_url['path']) : '/';
        $this->_url = $_url;
        return true;
    }

    private function _attach_cookie($url) {
        $sub_domain = $this->_url['host'];
        $cookie_str = '';
        if (isset($this->_cookies[$sub_domain])) {
            foreach($this->_cookies[$sub_domain] as $cookie) {
                if ($cookie['path']  == dirname($this->_url['path'])) {
                    $cookie_str .= $cookie['name'] . '=' . $cookie['value'] . ';';
                }
            }
        }
        $arrs = explode('.', $sub_domain);
        for ($i = 1; $i < count($arrs) - 1; $i++) {
            $domain = '';
            for ($j = $i; $j < count($arrs); $j++) { 
                $domain .= '.' . $arrs[$j] ;
            }
            if (isset($this->_cookies[$domain])) {
                foreach($this->_cookies[$domain] as $cookie) {
                    if ($cookie['path']  == dirname($this->_url['path'])) {
                        $cookie_str .= $cookie['name'] . '=' . $cookie['value'] . ';';
                    }
                }
            }
        }

        if ($cookie_str) {
            $cookie_str = substr($cookie_str, 0, -1);
            curl_setopt($this->_ch , CURLOPT_COOKIE, $cookie_str);
        }
    }

    private function _parse_cookie_str($cookie) {
        $ret = array();
        $arrs = explode(';', $cookie);
        foreach ($arrs as $k => $v) {
            $v = trim($v);
            $p = explode('=', $v);
            if (count($p) == 1) {
                continue;
            }
            if ($k == 0) {
                $ret['name'] = $p[0];
                $ret['value'] = $p[1];
                continue;
            }
            $ret[$p[0]] = $p[1];
        }
        if (!isset($ret['domain'])) {
            $ret['domain'] = $this->_url['host'];
        }
        return $ret;
    }

    private function _parse_http_response($response_str) {
        $pos = strpos($response_str, "\r\n\r\n");
        $head = substr($response_str, 0, $pos);
        if (strpos($head, 'Connection established') || strpos($head, 'HTTP/1.1 100 Continue') === 0) {
            $response_str = substr($response_str, $pos + 4);
            $pos = strpos($response_str, "\r\n\r\n");
            $head = substr($response_str, 0, $pos);
        }
        $body = substr($response_str, $pos + 4);
        $headers = http_parse_headers($head);
        if (isset($headers['Set-Cookie'])) {
            $cookies = array();
            if (is_array($headers['Set-Cookie'])) {
                foreach($headers['Set-Cookie'] as $item) {
                    $ret = $this->_parse_cookie_str($item);
                    $cookies[$ret['name']] = $ret;
                }               
            }
            else {
                $ret = $this->_parse_cookie_str($headers['Set-Cookie']);
                $cookies[$ret['name']] = $ret;          
            }
            $headers['Set-Cookie'] = $cookies;
            foreach ($cookies as $cookie) { 
                if (!isset($this->_cookies[$cookie['domain']])) {
                    $this->_cookies[$cookie['domain']] = array($cookie['name'] => $cookie);
                }
                else {
                    $this->_cookies[$cookie['domain']][$cookie['name']] = $cookie;
                }
            }
        }
        return array('head' => $headers, 'body' => $body);            
    }

}