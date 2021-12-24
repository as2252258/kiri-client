<?php
declare(strict_types=1);

namespace Http\Client;


use Exception;
use Http\Message\Response;
use Http\Message\Stream;
use JetBrains\PhpStorm\Pure;
use Psr\Http\Message\ResponseInterface;


/**
 * Class Curl
 * @package Http\Handler\Client
 */
class Curl extends ClientAbstracts
{

    /**
     * @param $method
     * @param $path
     * @param array $params
     * @throws Exception
     */
    public function request($method, $path, array $params = []): void
    {
        if ($method == self::GET) {
            $path = $this->joinGetParams($path, $params);
        }

        $this->getCurlHandler($path, $method, $params);

        $this->execute();
    }


    /**
     * @param $path
     * @param $method
     * @param $params
     * @throws Exception
     */
    private function getCurlHandler($path, $method, $params): void
    {
        [$host, $isHttps, $path] = $this->matchHost($path);

        $host = $isHttps ? 'https://' . $host : 'http://' . $host;
        if ($this->getPort() != 443 && $this->getPort() != 80) {
            $host .= ':' . $this->getPort();
        }
        $this->do(curl_init($host . $path), $host . $path, $method);
        if ($isHttps !== FALSE) {
            $this->curlHandlerSslSet();
        }
        $contents = $this->getData()->getContents();
        if (empty($params) && empty($contents)) {
            return;
        }
        if (!empty($contents)) {
            curl_setopt($this->client, CURLOPT_POSTFIELDS, $contents);
        } else if ($method === self::POST) {
            curl_setopt($this->client, CURLOPT_POSTFIELDS, $this->mergeParams($params));
        } else if ($method === self::UPLOAD) {
            curl_setopt($this->client, CURLOPT_POSTFIELDS, $params);
        }
    }


    /**
     * @return void
     * @throws Exception
     */
    private function curlHandlerSslSet(): void
    {
        if (!empty($this->getSslKeyFile()) && file_exists($this->getSslKeyFile())) {
            curl_setopt($this->client, CURLOPT_SSLKEY, $this->getSslKeyFile());
        }
        if (!empty($this->getSslCertFile()) && file_exists($this->getSslCertFile())) {
            curl_setopt($this->client, CURLOPT_SSLCERT, $this->getSslCertFile());
        }
    }


    /**
     * @param $resource
     * @param $path
     * @param $method
     * @throws Exception
     */
    private function do($resource, $path, $method): void
    {
        curl_setopt($resource, CURLOPT_URL, $path);
        curl_setopt($resource, CURLOPT_TIMEOUT, $this->getTimeout());                     // 超时设置
        curl_setopt($resource, CURLOPT_CONNECTTIMEOUT, $this->getConnectTimeout());       // 超时设置
        curl_setopt($resource, CURLOPT_HEADER, TRUE);
        curl_setopt($resource, CURLOPT_FAILONERROR, TRUE);
        curl_setopt($resource, CURLOPT_HTTPHEADER, $this->parseHeaderMat());
        if (defined('CURLOPT_SSL_FALSESTART')) {
            curl_setopt($resource, CURLOPT_SSL_FALSESTART, TRUE);
        }
        curl_setopt($resource, CURLOPT_FORBID_REUSE, FALSE);
        curl_setopt($resource, CURLOPT_FRESH_CONNECT, FALSE);
        if (!empty($this->getAgent())) {
            curl_setopt($resource, CURLOPT_USERAGENT, $this->getAgent());
        }
        curl_setopt($resource, CURLOPT_NOBODY, FALSE);
        curl_setopt($resource, CURLOPT_RETURNTRANSFER, TRUE);//返回内容
        curl_setopt($resource, CURLOPT_FOLLOWLOCATION, TRUE);// 跟踪重定向
        curl_setopt($resource, CURLOPT_ENCODING, 'gzip,deflate');
        if ($method === self::POST || $method == self::UPLOAD) {
            curl_setopt($resource, CURLOPT_POST, 1);
        }
        curl_setopt($resource, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        $this->client = $resource;
        if (!empty($this->caPath)) {
            curl_setopt($this->client,CURLOPT_CAINFO, $this->caPath);
        }
    }


    private string $caPath = '';


    /**
     * @param $path
     * @return $this
     */
    public function withCAInfo($path): static
    {
        $this->caPath = $path;
        return $this;
    }


    /**
     * @throws Exception
     */
    private function execute(): void
    {
        $output = curl_exec($this->client);
        if ($output === FALSE) {
            $this->setStatusCode(curl_errno($this->client));
            $this->setBody(curl_error($this->client));
        } else {
            $this->explode($output);
        }
    }


    /**
     *
     */
    public function close(): void
    {
        curl_close($this->client);
    }


    /**
     * @param $output
     * @return void
     * @throws Exception
     */
    private function explode($output): void
    {
        [$header, $body] = explode("\r\n\r\n", $output, 2);
        if ($header == 'HTTP/1.1 100 Continue') {
            [$header, $body] = explode("\r\n\r\n", $body, 2);
        }

        $header = explode("\r\n", $header);
        $status = explode(' ', array_shift($header));

        $this->setStatusCode(intval($status[1]));
        $this->setBody($body);
        $this->setResponseHeader($header);
    }

    /**
     * @param $headers
     * @return array
     */
    private function headerFormat($headers): array
    {
        $_tmp = [];
        foreach ($headers as $val) {
            $trim = explode(': ', trim($val));

            $_tmp[strtolower($trim[0])] = [$trim[1] ?? ''];
        }
        return $_tmp;
    }


    /**
     * @return array
     */
    #[Pure] private function parseHeaderMat(): array
    {
        $headers = [];
        foreach ($this->getHeader() as $key => $val) {
            $headers[$key] = $key . ': ' . $val;
        }
        return array_values($headers);
    }
}
