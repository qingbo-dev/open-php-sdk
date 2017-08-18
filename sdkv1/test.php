<?php
include __DIR__ . '/signature.php';
$appID = 'xx';
$appKey = 'xxxx';
$sign = new Lib_ee_signature($appID, $appKey);
$data=['wx_name'=>'sdgongshangju','sortby'=>'posttime','order'=>'desc'];
$start =  microtime(true);
echo 'start_time:'. $start.PHP_EOL;
$ret = $sign->get_send('/weixin/v1/articles', $data);
$data=curlQbPost($ret['uri'], $ret['header'],$ret['params']);
var_export($data);
echo PHP_EOL;
echo 'runtime:'.(microtime(true)-$start).PHP_EOL;
function curlQbPost($url, $header, $data)
{
    $new_header= array();
    foreach ($header as $k=>$v){
        $new_header[]=$k . ':' . preg_replace('/\s+/', ' ', is_array($v)?implode(',', $v):$v);
    }
    if(!empty($data['query'])){
    	$url=$url.'?'.http_build_query($data['query']);
    }
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
    curl_setopt($curl, CURLOPT_USERAGENT, $header['user-agent']);
    if(!empty($data['body'])){
    	curl_setopt($curl, CURLOPT_POST, 1);
    	curl_setopt($curl, CURLOPT_POSTFIELDS, $data['body']);
    }
    curl_setopt($curl, CURLOPT_TIMEOUT, 30);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $new_header);
    curl_setopt($curl, CURLOPT_HEADER, 0);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);


    $tmpInfo = curl_exec($curl);

    /*if (curl_errno($curl)) {
        echo 'Errno' . curl_error($curl);
    }*/
    curl_close($curl);
    return $tmpInfo;
}
