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

    private $headers;

    private $is_json = false;


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
     * post 请求
     *
     * @param string $body
     * @param bool $query
     *
     * @return string
     */
    public function post_send($body,$json=false,$query=false){
        $this->is_json = $json;
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

    /**
     * get 请求
     *
     * @param bool $query
     *
     * @return string
     */
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

    /**
     * delete 请求
     *
     * @param array $body
     * @param bool $query
     * @return string
     */
    public function delete_send(array $body,$query=false,$json=false){
        if($this->ishttps){
            $url = 'https://'.$this->host.'/'.$this->service;
        }else{
            $url = 'http://'.$this->host.'/'.$this->service;
        }
        $params=['body'=>$body];
        if($query){
            $params['query']=$query;
        }
        return $this->send('DELETE',$url,$params);
    }

    /**
     * update 请求
     *
     * @param array $body
     * @param bool $query
     * @return string
     */
    public function put_send(array $body,$query=false,$json=false){
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

    /**
     * 获取列表的总页数
     *
     * @return int
     */
    public function get_page_total(){
        return isset($this->headers['X-Pagination-Page-Count'])?$this->headers['X-Pagination-Page-Count'][0]:0;
    }

    /**
     * 获取列表的总条数
     *
     * @return int
     */
    public function get_total(){
        return isset($this->headers['X-Pagination-Total-Count'])?$this->headers['X-Pagination-Total-Count'][0]:0;
    }

    /**
     * 当前页
     *
     * @return int
     */
    public function get_now_page(){
        return isset($this->headers['X-Pagination-Current-Page'])?$this->headers['X-Pagination-Current-Page'][0]:0;
    }

    /**
     * 每页的条数
     *
     * @return int
     */
    public function get_page_limit(){
        return isset($this->headers['X-Pagination-Per-Page'])?$this->headers['X-Pagination-Per-Page'][0]:0;
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
        $http=$http->withAddedHeader('Accept','application/json');
        if(!empty($params['body'])) {
            if($this->is_json){
                $http = $http->withAddedHeader('content-Type', 'application/json');
            }else {
                $http = $http->withAddedHeader('content-Type', 'application/x-www-form-urlencoded');
            }
        }
        $requset=$signature->signRequest($http);
        try {
            $client = new Client();
            $response = $client->send($requset);
            $this->headers=$response->getHeaders();
            return $response->getBody()->getContents();
        }catch (RequestException $exception){
            return $exception->getResponse()->getBody()->getContents();
        }
    }
}
