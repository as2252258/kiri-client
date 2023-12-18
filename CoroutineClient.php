<?php
/**
 * Created by PhpStorm.
 * User: whwyy
 * Date: 2018/5/24 0024
 * Time: 11:34
 */
declare(strict_types=1);

namespace Kiri;

use Exception;
use Swoole\Coroutine\Http\Client as SwowClient;

/**
 * Class Client
 * @package Kiri\Http
 */
class CoroutineClient extends ClientAbstracts
{

    use TSwooleClient;

    /**
     * @param string $method
     * @param $path
     * @param array|string $params
     * @return void
     * @throws
     */
    public function request(string $method, $path, array|string $params = []): void
    {
        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }

        $host = $this->getHost();
        if (!preg_match('/(\d{1,3}\.){3}\d{1,3}/', $host)) {
            $this->withAddedHeader('Host', $host);
        }
        $this->withMethod($method)
             ->coroutine(
                 $path,
                 $this->paramEncode($params)
             );
    }


    /**
     * @param string $path
     * @return $this
     */
    public function withCAInfo(string $path): static
    {
        return $this;
    }

    /**
     * @param string $url
     * @param array|string $data
     */
    private function coroutine(string $url, array|string $data = []): void
    {
        try {
            $this->generate_client($this->getHost(), $this->isSSL());
            if ($this->client->statusCode < 0) {
                throw new Exception($this->client->errMsg);
            }

            $this->execute($url, $data);

        } catch (\Throwable $exception) {
            $this->setStatusCode(-1);
            $this->setBody(jTraceEx($exception));
        }
    }


    /**
     * @param string $path
     * @param array|string $data
     * @return void
     */
    private function execute(string $path, array|string $data): void
    {
        $this->client->execute($this->setParams($path, $data));
        if (in_array($this->client->getStatusCode(), [502, 404])) {
            $this->retry($path, $data);
        } else {
            $this->setStatusCode($this->client->getStatusCode());
            $this->setBody($this->client->getBody());
            $this->setResponseHeader($this->client->headers);
        }
    }


    /**
     * @param string $path
     * @param array|string $data
     * @return void
     */
    private function retry(string $path, array|string $data): void
    {
        if (($this->num += 1) <= $this->retryNum) {
            sleep($this->retryTimeout);

            $this->execute($path, $data);
        } else {
            $this->setStatusCode($this->client->statusCode);
            $this->setBody($this->client->errMsg);
        }
    }

    /**
     * @param string $host
     * @param bool $isHttps
     */
    private function generate_client(string $host, bool $isHttps): void
    {
        if ($isHttps || $this->isSSL()) {
            $this->client = new SwowClient($host, 443, true);
        } else {
            $this->client = new SwowClient($host, $this->getPort(), false);
        }
        $this->client->set($this->settings());
        if (!empty($this->getAgent())) {
            $this->withAddedHeader('User-Agent', $this->getAgent());
        }
        $this->client->setHeaders($this->getHeader());
        $this->client->setMethod(strtoupper($this->getMethod()));
    }


    /**
     * @param string $path
     * @param mixed $data
     * @return string
     */
    private function setParams(string $path, mixed $data): string
    {
        $content = $this->getData();
        if (!empty($content)) {
            $this->client->setData($content);
        }
        if ($this->isGet()) {
            if (!empty($data)) $path .= '?' . $data;
        } else if (!empty($data)) {
            $this->client->setData($data);
        }
        return $path;
    }

    /**
     *
     */
    public function close(): void
    {
        $this->client->close();
    }
}
