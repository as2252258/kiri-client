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
use Kiri\Abstracts\Logger;
use Kiri\Kiri;
use Swoole\Client as SwowClient;

/**
 * Class Client
 * @package Kiri\Kiri\Http
 */
class AsyncClient extends ClientAbstracts
{


	use TSwooleClient;

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
		$this->client->set(array_merge($this->settings(), ['package_eof' => "\r\n\r\n", 'open_eof_check' => true]));
		if (!empty($this->getAgent())) {
			$this->withAddedHeader('User-Agent', $this->getAgent());
		}

		$path = $this->setParams($path, $data);

		$this->withAddedHeader('Accept', ' text/html,application/xhtml+xml,application/json,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9');
//        $this->withAddedHeader('Accept-Encoding', 'gzip');
		$this->withAddedHeader('Content-Length', $this->getData()->getSize());

		$this->execute($path, $this->getData()->getContents());
	}


	/**
	 * @param string $path
	 * @param string $content
	 * @return void
	 */
	private function execute(string $path, string $content)
	{
		$array = [];
		$array[] = strtoupper($this->getMethod()) . ' ' . $path . ' HTTP/1.0';
		if (!empty($this->getHeader())) {
			foreach ($this->getHeader() as $key => $value) {
				$array[] = sprintf('%s: %s', $key, $value);
			}
		}
		$this->client->send(implode("\r\n", $array) . "\r\n\r\n" . $content);
		$revice = $this->client->recv();

		[$header, $body] = explode("\r\n\r\n", $revice);

		$header = explode("\r\n", $header);
		$status = array_shift($header);

		$this->setStatusCode(intval(explode(' ', $status)[1]));
		$this->parseResponseHeaders($header);
		$this->setBody($body);

		var_dump($body);
	}


	private function chunked()
	{

	}


	/**
	 * @param array $headers
	 * @return void
	 */
	private function parseResponseHeaders(array $headers)
	{
		$array = [];
		foreach ($headers as $header) {
			[$key, $value] = explode(': ', $header);

			$array[$key] = trim($value);
		}
		$this->setResponseHeader($array);
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
}
