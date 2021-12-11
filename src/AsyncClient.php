<?php
/**
 * Created by PhpStorm.
 * User: whwyy
 * Date: 2018/5/24 0024
 * Time: 11:34
 */
declare(strict_types=1);

namespace Http\Client;

use Exception;
use Http\Message\Stream;
use JetBrains\PhpStorm\Pure;
use Kiri\Abstracts\Logger;
use Kiri\Kiri;
use Swoole\Client as SwowClient;

/**
 * Class Client
 * @package Kiri\Kiri\Http
 */
class AsyncClient extends ClientAbstracts
{

    /**
     * @param string $method
     * @param $path
     * @param array $params
     * @return void
     * @throws Exception
     */
    public function request(string $method, $path, array $params = []): void
    {
        $this->withMethod($method)
            ->coroutine(
                $this->matchHost($path),
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
            $this->generate_client($data, ...$url);
        } catch (\Throwable $exception) {
            Kiri::getDi()->get(Logger::class)->error('rpc', [$exception]);
            $this->setStatusCode(-1);
            $this->setBody(jTraceEx($exception));
        }
    }


    /**
     * @param $data
     * @param $host
     * @param $isHttps
     * @param $path
     * @throws Exception
     */
    private function generate_client($data, $host, $isHttps, $path): void
    {
        $this->client = new SwowClient(SWOOLE_TCP, FALSE);
        if (!$this->client->connect($host, $this->getPort())) {
            throw new Exception('链接失败');
        }
        if ($isHttps || $this->isSSL()) {
            $this->client->enableSSL();
        }
        $this->client->set($this->settings());
        if (!empty($this->getAgent())) {
            $this->withAddedHeader('User-Agent', $this->getAgent());
        }

        $path = $this->setParams($path, $data);

        $array = [];
        $array[] = strtoupper($this->getMethod()) . ' ' . $path . ' HTTP/1.1';
        if (!empty($this->getHeader())) {
            foreach ($this->getHeader() as $key => $value) {
                $array[] = sprintf('%s: %s', $key, $value);
            }
        }
        $array = implode("\r\n", $array) . "
        
        ";
        $this->client->send($array . $this->getData()->getContents());

        var_dump($array . $this->getData()->getContents());

        $revice = $this->client->recv();

        [$header, $body] = explode("\r\n\r\n", $revice);

        $header = explode("\r\n", $header);

        $status = array_shift($header);

        $this->setBody($body);
        $this->setStatusCode(intval(explode(' ', $status)[1]));
        $this->setResponseHeader($header);
    }


    /**
     * @param $path
     * @param $data
     * @return string
     */
    private function setParams($path, $data): string
    {
        if ($this->isGet()) {
            if (!empty($data)) $path .= '?' . $data;
        } else {
            $data = $this->mergeParams($data);
            if (!empty($data)) {
                $this->withBody(new Stream($data));
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

    /**
     * @return array
     */
    #[Pure] private function settings(): array
    {
        $sslCert = $this->getSslCertFile();
        $sslKey = $this->getSslKeyFile();
        $sslCa = $this->getCa();

        $params = [];
        if ($this->getConnectTimeout() > 0) {
            $params['timeout'] = $this->getConnectTimeout();
        }
        if (empty($sslCert) || empty($sslKey) || empty($sslCa)) {
            return $params;
        }

        $params['ssl_host_name'] = $this->getHost();
        $params['ssl_cert_file'] = $this->getSslCertFile();
        $params['ssl_key_file'] = $this->getSslKeyFile();
        $params['ssl_verify_peer'] = TRUE;
        $params['ssl_cafile'] = $sslCa;

        return $params;
    }
}
