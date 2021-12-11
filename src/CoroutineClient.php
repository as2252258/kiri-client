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
use JetBrains\PhpStorm\Pure;
use Kiri\Abstracts\Logger;
use Kiri\Kiri;
use Swoole\Coroutine\Http\Client as SwowClient;

/**
 * Class Client
 * @package Kiri\Kiri\Http
 */
class CoroutineClient extends ClientAbstracts
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
			if ($this->client->statusCode < 0) {
				throw new Exception($this->client->errMsg);
			}
			$this->setStatusCode($this->client->getStatusCode());
			$this->setBody($this->client->getBody());
			$this->setResponseHeader($this->client->headers);
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
	 */
	private function generate_client($data, $host, $isHttps, $path): void
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
		$this->client->execute($this->setParams($path, $data));
	}


	/**
	 * @param $path
	 * @param $data
	 * @return string
	 */
	private function setParams($path, $data): string
	{
		$content = $this->getData()->getContents();
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
		$params['ssl_verify_peer'] = true;
		$params['ssl_cafile'] = $sslCa;

		return $params;
	}
}
