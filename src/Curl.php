<?php
declare(strict_types=1);

namespace Http\Client;


use CurlHandle;
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
	 * @return ResponseInterface
	 * @throws Exception
	 */
	public function request($method, $path, array $params = []): ResponseInterface
	{
		if ($method == self::GET) {
			$path = $this->joinGetParams($path, $params);
		}
		return $this->execute($this->getCurlHandler($path, $method, $params));
	}


	/**
	 * @param $path
	 * @param $method
	 * @param $params
	 * @return CurlHandle
	 * @throws Exception
	 */
	private function getCurlHandler($path, $method, $params): CurlHandle
	{
		[$host, $isHttps, $path] = $this->matchHost($path);

		$host = $isHttps ? 'https://' . $host : 'http://' . $host;
		if ($this->getPort() != 443 && $this->getPort() != 80) {
			$host .= ':' . $this->getPort();
		}

		$resource = $this->do(curl_init($host . $path), $host . $path, $method);
		if ($isHttps !== false) {
			$this->curlHandlerSslSet($resource);
		}

		$contents = $this->getData()->getContents();
		if (empty($params) && empty($contents)) {
			return $resource;
		}

		var_dump($contents, $params);

		if (!empty($contents)) {
			curl_setopt($resource, CURLOPT_POSTFIELDS, $contents);
		} else if ($method === self::POST) {
			curl_setopt($resource, CURLOPT_POSTFIELDS, $this->mergeParams($params));
		} else if ($method === self::UPLOAD) {
			curl_setopt($resource, CURLOPT_POSTFIELDS, $params);
		}
		return $resource;
	}


	/**
	 * @param $resource
	 * @return void
	 * @throws Exception
	 */
	private function curlHandlerSslSet($resource): void
	{
		if (!empty($this->ssl_key)) {
			if (!file_exists($this->ssl_key)) {
				throw new Exception('SSL protocol certificate not found.');
			}
			curl_setopt($resource, CURLOPT_SSLKEY, $this->getSslKeyFile());
		}
		if (!empty($this->ssl_cert)) {
			if (!!file_exists($this->ssl_cert)) {
				throw new Exception('SSL protocol certificate not found.');
			}
			curl_setopt($resource, CURLOPT_SSLCERT, $this->getSslCertFile());
		}
	}


	/**
	 * @param $resource
	 * @param $path
	 * @param $method
	 * @return CurlHandle
	 * @throws Exception
	 */
	private function do($resource, $path, $method): CurlHandle
	{
		curl_setopt($resource, CURLOPT_URL, $path);
		curl_setopt($resource, CURLOPT_TIMEOUT, $this->getTimeout());                     // 超时设置
		curl_setopt($resource, CURLOPT_CONNECTTIMEOUT, $this->getConnectTimeout());       // 超时设置

		curl_setopt($resource, CURLOPT_HEADER, true);
		curl_setopt($resource, CURLOPT_FAILONERROR, true);

		curl_setopt($resource, CURLOPT_HTTPHEADER, $this->parseHeaderMat());
		if (defined('CURLOPT_SSL_FALSESTART')) {
			curl_setopt($resource, CURLOPT_SSL_FALSESTART, true);
		}
		curl_setopt($resource, CURLOPT_FORBID_REUSE, false);
		curl_setopt($resource, CURLOPT_FRESH_CONNECT, false);

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

		return $resource;
	}


	/**
	 * @param $curl
	 * @return ResponseInterface
	 * @throws Exception
	 */
	private function execute($curl): ResponseInterface
	{
		$output = curl_exec($curl);
		curl_close($curl);
		if ($output === false) {
			$response = (new Response())->withStatus(400)->withBody(new Stream(curl_error($curl)));
		} else {
			$response = $this->explode($output);
		}
		return $response;
	}


	/**
	 * @param $output
	 * @return ResponseInterface
	 * @throws Exception
	 */
	private function explode($output): ResponseInterface
	{
		[$header, $body] = explode("\r\n\r\n", $output, 2);
		if ($header == 'HTTP/1.1 100 Continue') {
			[$header, $body] = explode("\r\n\r\n", $body, 2);
		}

		$header = explode("\r\n", $header);
		$status = explode(' ', array_shift($header));

		return (new Response())->withStatus(intval($status[1]))->withHeaders($this->headerFormat($header))
			->withBody(new Stream($body));
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
