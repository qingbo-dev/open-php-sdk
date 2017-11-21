<?php
namespace GSDATA;
use function GuzzleHttp\Psr7\build_query;
use function GuzzleHttp\Psr7\parse_query;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\RequestInterface;

class Signature{

    const ISO8601_BASIC = 'Ymd\THis\Z';
    const UNSIGNED_PAYLOAD = 'UNSIGNED-PAYLOAD';

    private $cache = [];

    private $unsigned=false;

    private $cacheSize = 0;

    /** @var string */
    private $secret;

    private $appkey;

    public function __construct($appkey,$secret){
        $this->secret=$secret;
        $this->appkey=$appkey;
    }

    protected function createCanonicalizedPath($path)
    {
        $doubleEncoded = rawurlencode(ltrim($path, '/'));
        return '/' . str_replace('%2F', '/', $doubleEncoded);
    }

    public function signRequest(RequestInterface $request){
        $ldt = gmdate(self::ISO8601_BASIC);
        $sdt = substr($ldt, 0, 8);
        $parsed = $this->parseRequest($request);
        $parsed['headers']['x-gsdata-date'] = [$ldt];
        $payload = $this->getPayload($request);
        $context = $this->createContext($parsed, $payload);
        $toSign = $this->createStringToSign($ldt, $context['creq']);
        $signingKey = $this->getSigningKey(
            $sdt,
            $parsed['path'],
            $this->secret
        );
        $signature = hash_hmac('sha256', $toSign, $signingKey);
        $parsed['headers']['Authorization'] = [
            "GSDATA-HMAC-SHA256 "
            . "AppKey={$this->appkey}, "
            . "SignedHeaders={$context['headers']}, Signature={$signature}"
        ];
        return $this->buildRequest($parsed);
    }

    private function createContext(array $parsedRequest, $payload)
    {
        static $blacklist = [
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
        ];
        $canon = $parsedRequest['method'] . "\n"
            . $this->createCanonicalizedPath($parsedRequest['path']) . "\n"
            . $this->getCanonicalizedQuery($parsedRequest['query']) . "\n";
        $aggregate = [];
        foreach ($parsedRequest['headers'] as $key => $values) {
            $key = strtolower($key);
            if (!isset($blacklist[$key])) {
                foreach ($values as $v) {
                    $aggregate[$key][] = $v;
                }
            }
        }
        ksort($aggregate);
        $canonHeaders = [];
        foreach ($aggregate as $k => $v) {
            if (count($v) > 0) {
                sort($v);
            }
            $canonHeaders[] = $k . ':' . preg_replace('/\s+/', ' ', implode(',', $v));
        }
        $signedHeadersString = implode(';', array_keys($aggregate));
        $canon .= implode("\n", $canonHeaders) . "\n"
            . $signedHeadersString . "\n"
            . $payload;
        return ['creq' => $canon, 'headers' => $signedHeadersString];
    }


    private function getSigningKey($shortDate, $service, $secretKey)
    {
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



    private function parseRequest(RequestInterface $request)
    {
        $request = $request
            ->withoutHeader('x-gsdata-date')
            ->withoutHeader('Date')
            ->withoutHeader('Authorization');

        $uri = $request->getUri();

        return [
            'method'  => $request->getMethod(),
            'path'    => $uri->getPath(),
            'query'   => parse_query($uri->getQuery()),
            'uri'     => $uri,
            'headers' => $request->getHeaders(),
            'body'    => $request->getBody(),
            'version' => $request->getProtocolVersion()
        ];
    }

    private function moveHeadersToQuery(array $parsedRequest)
    {
        foreach ($parsedRequest['headers'] as $name => $header) {
            $lname = strtolower($name);
            if (substr($lname, 0, 7) == 'x-gsdata') {
                $parsedRequest['query'][$name] = $header;
            }
            if ($lname !== 'host') {
                unset($parsedRequest['headers'][$name]);
            }
        }
        return $parsedRequest;
    }

    private function buildRequest(array $req)
    {
        if ($req['query']) {
            $req['uri'] = $req['uri']->withQuery(build_query($req['query']));
        }
        return new Request(
            $req['method'],
            $req['uri'],
            $req['headers'],
            $req['body'],
            $req['version']
        );
    }

    private function createStringToSign($longDate, $creq)
    {
        $hash = hash('sha256', $creq);
        return "GSDATA-HMAC-SHA256\n{$longDate}\n{$hash}";
    }

    protected function getPayload(RequestInterface $request)
    {
        if ($this->unsigned && $request->getUri()->getScheme() == 'https') {
            return self::UNSIGNED_PAYLOAD;
        }

        if (!$request->getBody()->isSeekable()) {
            //throw new CouldNotCreateChecksumException('sha256');
        }
        try {
            return  \GuzzleHttp\Psr7\hash($request->getBody(), 'sha256');
        } catch (\Exception $e) {
            //throw new CouldNotCreateChecksumException('sha256', $e);
        }
    }
    protected function getPresignedPayload(RequestInterface $request)
    {
        return $this->getPayload($request);
    }

    private function getCanonicalizedQuery(array $query)
    {
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

}
