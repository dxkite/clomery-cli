<?php
namespace clomery\remote;

use CURLFile;
use Exception;
use clomery\remote\RemoteException;

/**
 * 远程服务器接口类
 */
class RemoteClass
{
    protected $id = 0;
    protected $url;
    protected $headers;
    protected $cookieFileSavePath;

    /**
     * 创建远程服务对象接口
     *
     * @param string $url
     * @param string $cookiePath
     * @param array $headers
     */
    public function __construct(string $url, string $cookiePath, array $headers=[])
    {
        $this->url=$url;
        $this->cookieFileSavePath = $cookiePath;
        $this->headers = $headers;
    }
    
    /**
     * 通常调用 方式
     *
     * @param string $method
     * @param array $params
     * @return void
     */
    public function __call(string $method, array $params)
    {
        return $this->exec($this->url, $method, $params, $this->headers);
    }

    /**
     * 含有文件调用方式
     *
     * @param string $method
     * @param array $params
     * @return void
     */
    public function _call(string $method, array $params)
    {
        return $this->exec($this->url, $method, $params, $this->headers);
    }

    /**
     * 调用远程接口
     *
     * @param string $url
     * @param string $method
     * @param array $params
     * @return void
     */
    public function exec(string $url, string $method, array $params, array $headerArray)
    {
        $this->id++;
        $cookieFile = $this->cookieFileSavePath;
        $headers =[
            'XRPC-Id:'. $this->id,
            'XRPC-Method:'.$method,
            'User-Agent: XRPC-Client',
            'Accept: application/json , image/*'
        ];
        foreach ($headerArray as $name=>$value) {
            $headers[]=$name.':'.$value;
        }
        $postFile = false;
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLINFO_HEADER_OUT, 1);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($curl, CURLOPT_AUTOREFERER, 1);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_SAFE_UPLOAD, 1);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        curl_setopt($curl, CURLOPT_COOKIEFILE, $cookieFile);
        curl_setopt($curl, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 30);

        // curl_setopt($curl, CURLOPT_PROXY, '127.0.0.1'); //代理服务器地址   
        // curl_setopt($curl, CURLOPT_PROXYPORT, 8888 ); //代理服务器端口

        // curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $verifyHost);
        // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verifyPeer);
        foreach ($params as $name => $param) {
            if ($param instanceof \CURLFile) {
                $postFile =true;
            }
        }
        if ($postFile) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
        } else {
            $json=json_encode($params);
            $length=strlen($json);
            $headers[]= 'Content-Type: application/json';
            $headers[]=  'Content-Length: '.  $length;
            curl_setopt($curl, CURLOPT_POSTFIELDS, $json);
        }
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        $data = curl_exec($curl);
        $contentType=curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
        $code =curl_getinfo($curl, CURLINFO_HTTP_CODE);
        // \var_dump(curl_getinfo($curl));
        // $headerSend =curl_getinfo($curl, CURLINFO_HEADER_OUT);
        // print $headerSend; 
        if ($data) {
            curl_close($curl);
            if ($code  == 200) {
                if (preg_match('/json/i', $contentType)) {
                    $ret= json_decode($data, true);
                    if (is_array($ret) && array_key_exists('error', $ret)) {
                        $error=$ret['error'];
                        if ($error['name'] === 'PermissionDeny') {
                            throw (new RemoteException($error['message'], $error['code']))->setName($error['name']);
                        }
                        throw (new RemoteException($error['message'], $error['code']))->setName($error['name']);
                    } 
                    return $ret;
                } else {
                    return $data;
                }
            } elseif ($code == 500) {
                if (preg_match('/json/i', $contentType)) {
                    $error=json_decode($data, true);
                    throw (new RemoteException($error['error']['message'], $error['error']['code']))->setName($error['error']['name']);
                }
                throw new RemoteException('Server 500 Error');
            }
        } else {
            if ($errno = curl_errno($curl)) {
                $error_message = curl_strerror($errno);
                curl_close($curl);
                throw (new RemoteException("cURL error ({$errno}):\n {$error_message}", $errno))->setName('CURLError');
            }
        }
        return null;
    }
}
