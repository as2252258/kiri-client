<?php
/**
 * Created by PhpStorm.
 * User: whwyy
 * Date: 2018/5/24 0024
 * Time: 11:34
 */
declare(strict_types=1);

namespace Http\Handler\Client;

use Exception;
use Http\Message\Response;
use Http\Message\Stream;
use JetBrains\PhpStorm\Pure;
use Psr\Http\Message\ResponseInterface;
use Swoole\Coroutine\Http\Client as SwowClient;

/**
 * Class Client
 * @package Kiri\Kiri\Http
 */
class Client extends ClientAbstracts
{

	/**
	 * @param string $method
	 * @param $path
	 * @param array $params
	 * @return ResponseInterface
	 * @throws Exception
	 */
	public function request(string $method, $path, array $params = []): ResponseInterface
	{
		return $this->withMethod($method)
			->coroutine(
				$this->matchHost($path),
				$this->paramEncode($params)
			);
	}


	/**
	 * @param $url
	 * @param array|string $data
	 * @return ResponseInterface
	 * @throws Exception 使用swoole协程方式请求
	 */
	private function coroutine($url, array|string $data = []): ResponseInterface
	{
		try {
			$client = $this->generate_client($data, ...$url);
			if ($client->statusCode < 0) {
				throw new Exception($client->errMsg);
			}
			return (new Response())->withStatus($client->getStatusCode())
				->withHeaders($client->getHeaders())
				->withBody(new Stream($client->getBody()));
		} catch (\Throwable $exception) {
			$this->addError($exception, 'rpc');
			return (new Response())->withStatus(-1)->withHeaders([])
				->withBody(new Stream(jTraceEx($exception)));
		}
	}


	/**
	 * @param $data
	 * @param $host
	 * @param $isHttps
	 * @param $path
	 * @return SwowClient
	 */
	private function generate_client($data, $host, $isHttps, $path): SwowClient
	{
		if ($isHttps || $this->isSSL()) {
			$client = new SwowClient($host, 443, true);
		} else {
			$client = new SwowClient($host, $this->getPort(), false);
		}
		$client->set($this->settings());
		if (!empty($this->getAgent())) {
			$this->withAddedHeader('User-Agent', $this->getAgent());
		}
		$client->setHeaders($this->getHeader());
		$client->setMethod(strtoupper($this->getMethod()));
		$client->execute($this->setParams($client, $path, $data));
		$client->close();
		return $client;
	}


	/**
	 * @param SwowClient $client
	 * @param $path
	 * @param $data
	 * @return string
	 */
	private function setParams(SwowClient $client, $path, $data): string
	{
		$content = $this->getData()->getContents();
		if (!empty($content)) {
			$client->setData($content);
		}
		if ($this->isGet()) {
			if (!empty($data)) $path .= '?' . $data;
		} else {
			$data = $this->mergeParams($data);
			if (!empty($data)) {
				$client->setData($data);
			}
		}
		return $path;
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
