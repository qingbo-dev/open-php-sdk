<?php
/**
 * 清博数据对接签名计算类
 * 
 * @author zsg
 * @since 2017-06-29
 * @version 5.0
 */

class Lib_ee_signature{
	
	const VERSION = '1.0.0';
	
	const ISO8601_BASIC = 'Ymd\THis\Z';
	
	private $host = 'api.gsdata.cn';
	
	private $ishttps = false;
	
	private $service;
	
	private $appkey;
	
	private $secret;
	
	private $cache = [];
	
	private $unsigned=false;
	
	private $cacheSize = 0;
	
	public function __construct($app_key,$app_secret,$ishttps=false){
		$this->secret=$app_secret;
        $this->appkey=$app_key;
		$this->ishttps = $ishttps;
	}
	
	public function post_send($service, $body, $json=false, $query=false){
		$this->service = $service;
		if($this->ishttps){
			$url = 'https://'.$this->host.$service;
		}else{
			$url = 'http://'.$this->host.$service;
		}
		$body = is_string($body)?$body:($json?json_encode($body):http_build_query($body));
		
		$params = array('body' => $body);
		
		if($query){
			$params['query'] = $query;
		}
		return $this->send('POST',$url,$params);
    }

    public function get_send($service, array $query){
        $this->service = $service;
        if($this->ishttps){
            $url = 'https://'.$this->host.$service;
        }else{
            $url = 'http://'.$this->host.$service;
        }
        $params = array('body' => '');
        $params['query'] = $query;
        return $this->send('GET',$url,$params);
    }
	
	private function send($mothd,$url,array $params){
		$ldt = gmdate(self::ISO8601_BASIC);
		$sdt = substr($ldt, 0, 8);
		$header = array();
		$header['host'] = $this->host;
		$header['user-agent'] = 'GSDATA-v'.self::VERSION.'-SDK';
		$header['x-gsdata-date'] = $ldt;
		$payload = hash('sha256',$params['body']);
		$parsedRequest = array();
		$parsedRequest['method'] = $mothd;
		$parsedRequest['path'] = $this->service;
		$parsedRequest['query'] = isset($params['query'])?$params['query']:array();
		$parsedRequest['headers'] = $header;
                $context = $this->createContext($parsedRequest, $payload);
		$toSign = $this->createStringToSign($ldt, $context['creq']);
		$signingKey = $this->getSigningKey(
				$sdt,
				$this->service,
				$this->secret
		);
		$signature = hash_hmac('sha256', $toSign, $signingKey);
		$header['Authorization'] =
		"GSDATA-HMAC-SHA256 "
				. "AppKey={$this->appkey}, "
				. "SignedHeaders={$context['headers']}, Signature={$signature}"
				;
		return array('header'=>$header, 'uri'=>$url,'params'=>$params);
	}
	
	private function getSigningKey($shortDate, $service, $secretKey){
		$k = $shortDate . '_' . $service . '_' . $secretKey;
		if (!isset($this->cache[$k])) {
			if (++$this->cacheSize > 50) {
				$this->cache = [];
				$this->cacheSize = 0;
			}
			$dateKey = hash_hmac(
					'sha256',
					$shortDate,
					"GSDATA{$secretKey}",
					true
			);
			$serviceKey = hash_hmac('sha256', $service, $dateKey, true);
			$this->cache[$k] = hash_hmac(
					'sha256',
					'gsdata_request',
					$serviceKey,
					true
			);
		}
		return $this->cache[$k];
	}
	
	private function createContext(array $parsedRequest, $payload){
		static $blacklist = array(
			'cache-control'       => true,
			'content-type'        => true,
			'content-length'      => true,
			'expect'              => true,
			'max-forwards'        => true,
			'pragma'              => true,
			'range'               => true,
			'te'                  => true,
			'if-match'            => true,
			'if-none-match'       => true,
			'if-modified-since'   => true,
			'if-unmodified-since' => true,
			'if-range'            => true,
			'accept'              => true,
			'authorization'       => true,
			'proxy-authorization' => true,
			'from'                => true,
			'referer'             => true,
			'x-gsdagta-trace-id'     => true
		);
		$canon = $parsedRequest['method'] . "\n"
				. $this->createCanonicalizedPath($parsedRequest['path']) . "\n"
						. $this->getCanonicalizedQuery($parsedRequest['query']) . "\n";
		$aggregate = array();
		foreach ($parsedRequest['headers'] as $key => $values) {
			$key = strtolower($key);
			if (!isset($blacklist[$key])) {
			    if(is_array($values)) {
                    foreach ($values as $v) {
                        $aggregate[$key][] = $v;
                    }
                }else{
                    $aggregate[$key]=$values;
                }
			}
		}
		ksort($aggregate);
		$canonHeaders = array();
		foreach ($aggregate as $k => $v) {
			if (is_array($v) && count($v) > 0) {
				sort($v);
			}
			$canonHeaders[] = $k . ':' . preg_replace('/\s+/', ' ', is_array($v)?implode(',', $v):$v);
		}
		$signedHeadersString = implode(';', array_keys($aggregate));
		$canon .= implode("\n", $canonHeaders) . "\n"
				. $signedHeadersString . "\n"
						. $payload;
		return array('creq' => $canon, 'headers' => $signedHeadersString,'query'=>$this->getCanonicalizedQuery($parsedRequest['query']));
	}

	private function getCanonicalizedQuery(array $query){
		if (!$query) {
			return '';
		}
		$qs = '';
		ksort($query);
		foreach ($query as $k => $v) {
			if (!is_array($v)) {
				$qs .= rawurlencode($k) . '=' . rawurlencode($v) . '&';
			} else {
				sort($v);
				foreach ($v as $value) {
					$qs .= rawurlencode($k) . '=' . rawurlencode($value) . '&';
				}
			}
		}
		return substr($qs, 0, -1);
	}
	private function createCanonicalizedPath($path){
		$doubleEncoded = rawurlencode(ltrim($path, '/'));
		return '/' . str_replace('%2F', '/', $doubleEncoded);
	}
	
	private function createStringToSign($longDate, $creq){
		$hash = hash('sha256', $creq);
		return "GSDATA-HMAC-SHA256\n{$longDate}\n{$hash}";
	}
}
