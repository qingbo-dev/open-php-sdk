<?php
/**
 * Created by PhpStorm.
 * User: phpboy
 * Date: 2017/6/1
 * Time: 14:21
 */

namespace GSDATA;


use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use function GuzzleHttp\Psr7\stream_for;

final class SDK
{
    const VERSION = '1.0.2';

    private $host = 'api.gsdata.cn';

    private $old_host = 'open.gsdata.cn/api';

    private $signature;

    private $service;

    private $ishttps = false;



    public function __construct($app_key,$app_secret,$version='2',$ishttps=false)
    {
        $this->signature = new Signature($app_key,$app_secret);
        $this->ishttps = $ishttps;
    }

    public function setService($service){
        $this->service=$service;
    }

    private function setQuery(array $params){
        $this->query=['query'=>$params];
    }

    private function setBody($params){
        $this->body=['body'=>$params];
    }

    /**
     *
     * @param string $body
     * @param bool $query
     *
     * @return string
     */
    public function post_send($body,$json=false,$query=false){
        if($this->ishttps){
            $url = 'https://'.$this->host.'/'.$this->service;
        }else{
            $url = 'http://'.$this->host.'/'.$this->service;
        }
        $body = is_string($body)?$body:($json?json_encode($body):http_build_query($body));
        $params=['body'=>$body];
        if($query){
            $params['query']=$query;
        }
        return $this->send('POST',$url,$params);
    }

    public function get_send($query=false){
        if($this->ishttps){
            $url = 'https://'.$this->host.'/'.$this->service;
        }else{
            $url = 'http://'.$this->host.'/'.$this->service;
        }
        if($query){
            $params=['query'=>$query];
        }else{
            $params=[];
        }
        return $this->send('GET',$url,$params);
    }

    public function delete_send($query=false){
        if($this->ishttps){
            $url = 'https://'.$this->host.'/'.$this->service;
        }else{
            $url = 'http://'.$this->host.'/'.$this->service;
        }
        if($query){
            $params=['query'=>$query];
        }else{
            $params=[];
        }
        return $this->send('DELETE',$url,$params);
    }

    public function put_send(array $body,$query=false){
        if($this->ishttps){
            $url = 'https://'.$this->host.'/'.$this->service;
        }else{
            $url = 'http://'.$this->host.'/'.$this->service;
        }
        $params=['body'=>$body];
        if($query){
            $params['query']=$query;
        }
        return $this->send('PUT',$url,$params);
    }

    private function send($mothd,$url,array $params){
        $signature=$this->signature;
        $http = new Request($mothd,$url,[],'');
        if(!empty($params['body'])){

            $http=$http->withBody( stream_for($params['body']));
        }
        if(!empty($params['query'])) {
            $http = $http->withUri($http->getUri()->withQuery(http_build_query($params['query'])));
        }
        $http=$http->withHeader('User-Agent','GSDATA-v'.self::VERSION.'-SDK');
        $requset=$signature->signRequest($http);
        try {
            $client = new Client();
            $response = $client->send($requset);
            return $response->getBody()->getContents();
        }catch (RequestException $exception){
            return $exception->getResponse()->getBody()->getContents();
        }
    }
}
