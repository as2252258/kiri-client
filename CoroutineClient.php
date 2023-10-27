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
use Swoole\Coroutine\System;

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
     * @throws Exception
     */
    public function request(string $method, $path, array|string $params = []): void
    {
        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }
        $this->withMethod($method)
            ->coroutine(
                $path,
                $this->paramEncode($params)
            );
    }


    /**
     * @param $path
     * @return $this
     */
    public function withCAInfo($path): static
    {
        return $this;
    }

    /**
     * @param $url
     * @param array|string $data
     * @throws Exception 使用swoole协程方式请求
     */
    private function coroutine($url, array|string $data = []): void
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
     * @param $path
     * @param $data
     * @return void
     * @throws Exception
     */
    private function execute($path, $data): void
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
     * @param $path
     * @param $data
     * @return void
     * @throws Exception
     */
    private function retry($path, $data): void
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
     * @param $host
     * @param $isHttps
     */
    private function generate_client($host, $isHttps): void
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
     * @param $path
     * @param $data
     * @return string
     */
    private function setParams($path, $data): string
    {
        $content = $this->getData();
        if (!empty($content)) {
            $this->client->setData($content);
        }
        if ($this->isGet()) {
            if (!empty($data)) $path .= '?' . $data;
        } else {
            $data = $this->mergeParams($data);
            if (!empty($data)) {
                $this->client->setData($data);
            }
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
