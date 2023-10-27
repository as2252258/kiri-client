<?php
declare(strict_types=1);

namespace Kiri;


use Exception;


/**
 * Class CurlClient
 * @package Http\Handler\Client
 */
class CurlClient extends ClientAbstracts
{

    /**
     * @param $method
     * @param $path
     * @param array|string $params
     * @throws Exception
     */
    public function request($method, $path, array|string $params = []): void
    {
        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }
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
        $host = $this->isSSL() ? 'https://' . $this->getHost() : 'http://' . $this->getHost();
        if ($this->getPort() != 443 && $this->getPort() != 80) {
            $host .= ':' . $this->getPort();
        }
        $this->do(curl_init($host . $path), $host . $path, $method);
        if ($this->isSSL()) {
            $this->curlHandlerSslSet();
        }
        $contents = $this->getData();
        if (empty($params) && empty($contents)) {
            return;
        }
        if (!empty($contents)) {
            curl_setopt($this->client, CURLOPT_POSTFIELDS, $contents);
        } else if ($method === self::UPLOAD) {
            curl_setopt($this->client, CURLOPT_POSTFIELDS, $params);
        } else if ($method === self::POST) {
            if (is_array($params)) {
                $params = http_build_query($params);
            }
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
        if (!empty($this->getCa()) && file_exists($this->getCa())) {
            curl_setopt($this->client, CURLOPT_CAINFO, $this->getCa());
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
        [$proxy, $port] = [$this->getProxyHost(), $this->getProxyPort()];
        if (!empty($proxy) && $port > 0) {
            curl_setopt($resource, CURLOPT_PROXYPORT, $port);
            curl_setopt($resource, CURLOPT_PROXY, $proxy);
        }
        curl_setopt($resource, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        $this->client = $resource;
        if (!empty($this->caPath)) {
            curl_setopt($this->client, CURLOPT_CAINFO, $this->caPath);
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
        if ($output !== FALSE) {
            $this->explode($output);
        } else {
            $this->setStatusCode(curl_errno($this->client));
            $this->setBody(curl_error($this->client));
        }
    }


    /**
     * @return void
     * @throws Exception
     */
    private function retry(): void
    {
        if (($this->num += 1) <= $this->retryNum) {
            sleep($this->retryTimeout);

            $this->execute();
        } else {
            $this->setStatusCode(curl_errno($this->client));
            $this->setBody(curl_error($this->client));
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

        $statusCode = intval($status[1]);
        if (in_array($statusCode, [502, 404])) {
            $this->retry();
        } else {
            $this->setStatusCode($statusCode);
            $this->setBody($body);
            $this->setResponseHeader($header);
        }
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
    private function parseHeaderMat(): array
    {
        $headers = [];
        foreach ($this->getHeader() as $key => $val) {
            $headers[$key] = $key . ': ' . $val;
        }
        return array_values($headers);
    }
}
