<?php
/**
 * Created by PhpStorm.
 * User: phpboy
 * Date: 2017/6/1
 * Time: 14:21
 */

namespace GSDATA;


use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;

final class SDK
{
    const VERSION = '1.0.0';

    private $host = 'api.qb.cn';

    private $signature;

    private $service;

    private $ishttps = false;



    public function __construct($app_key,$app_secret,$ishttps=false)
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

    public function post_send($body,$query=false){
        if($this->ishttps){
            $url = 'https://'.$this->host.'/'.$this->service;
        }else{
            $url = 'http://'.$this->host.'/'.$this->service;
        }
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
        $http = new Request($mothd,$url,$params);
        $http=$http->withHeader('User-Agent','GSDATA-v'.self::VERSION.'-SDK');
        $requset=$signature->signRequest($http);
        $client = new Client();
        $response=$client->send($requset);
        return $response->getBody()->getContents();
    }
}