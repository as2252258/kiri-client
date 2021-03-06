<?php


namespace Kiri;


use Closure;
use Exception;
use JetBrains\PhpStorm\Pure;
use Kiri\Core\Help;
use Kiri\Message\Stream;
use Psr\Http\Message\StreamInterface;
use Swoole\Coroutine\System;

defined('SPLIT_URL') or define('SPLIT_URL', '/(http[s]?:\/\/)?(([\w\-_]+\.)+\w+(:\d+)?)((\/[a-zA-Z0-9\-]+)+[\/]?(\?[a-zA-Z]+=.*)?)?/');


/**
 * Class ClientAbstracts
 * @package Http\Handler\Client
 */
abstract class ClientAbstracts implements IClient
{

	const POST = 'post';
	const UPLOAD = 'upload';
	const GET = 'get';
	const DELETE = 'delete';
	const OPTIONS = 'options';
	const HEAD = 'head';
	const PUT = 'put';

	private string $host = '';

	private array $header = [];

	private int $timeout = 0;

	private string $method = 'get';

	private bool $isSSL = FALSE;
	private string $agent = '';

	private string $ssl_cert_file = '';
	private string $ssl_key_file = '';
	private string $ca = '';
	private int $port = 80;


	private ?array $_responseHeader = [];


	private int $statusCode = 200;


	protected int $retryNum = 0;

	protected int $retryTimeout = 0;


	private bool $verifyPeer = TRUE;


	/**
	 * @var string|null
	 */
	protected ?string $body;


	private ?StreamInterface $_data = NULL;

	private int $connect_timeout = 1;


	/**
	 * @var resource|\Swoole\Coroutine\Http\Client|\Swoole\Client|\CurlHandle
	 */
	protected mixed $client;


	/**
	 * @param int $retryNum
	 * @return $this
	 */
	public function withRetryNum(int $retryNum): static
	{
		$this->retryNum = $retryNum;
		return $this;
	}


	/**
	 * @param int $retryTimeout
	 * @return $this
	 */
	public function withRetryTimeout(int $retryTimeout): static
	{
		$this->retryTimeout = $retryTimeout;
		return $this;
	}


	/**
	 * @return int
	 */
	public function getRetryNum(): int
	{
		return $this->retryNum;
	}


	/**
	 * @return int
	 */
	public function getRetryTimeout(): int
	{
		return $this->retryTimeout;
	}


	/**
	 * @param $bool
	 * @return $this
	 */
	public function withVerifyPeer($bool): static
	{
		$this->verifyPeer = $bool;
		return $this;
	}


	/**
	 * @return bool
	 */
	public function getVerifyPeer(): bool
	{
		return $this->verifyPeer;
	}


	/**
	 * @return int
	 */
	public function getStatusCode(): int
	{
		return $this->statusCode;
	}


	/**
	 * @return array
	 */
	public function getResponseHeaders(): array
	{
		return $this->_responseHeader;
	}


	/**
	 * @param string $key
	 * @return string|int|null
	 */
	public function getResponseHeader(string $key): null|string|int
	{
		return $this->_responseHeader[$key] ?? NULL;
	}


	/**
	 * @param null|array $responseHeader
	 */
	public function setResponseHeader(?array $responseHeader): void
	{
		$this->_responseHeader = $responseHeader;
	}


	/**
	 * @param int $statusCode
	 */
	public function setStatusCode(int $statusCode): void
	{
		$this->statusCode = $statusCode;
	}


	/**
	 * @return string|null
	 */
	public function getBody(): string|null
	{
		return $this->body;
	}


	/**
	 * @param ?string $body
	 */
	public function setBody(?string $body): void
	{
		$this->body = $body;
	}


	/**
	 * @param $host
	 * @param $port
	 * @param false $isSSL
	 */
	public function __construct($host, $port, bool $isSSL = FALSE)
	{
		$this->withHost($host)->withPort($port)->withIsSSL($isSSL);
	}


	/**
	 * @param string $path
	 * @param array $params
	 */
	public function post(string $path, array $params = []): void
	{
		$this->request(self::POST, $path, $params);
	}


	/**
	 * @param string $path
	 * @param array $params
	 */
	public function put(string $path, array $params = []): void
	{
		$this->request(self::PUT, $path, $params);
	}


	/**
	 * @param string $contentType
	 * @return ClientAbstracts
	 */
	public function withContentType(string $contentType): static
	{
		$this->header['Content-Type'] = $contentType;
		return $this;
	}


	/**
	 * @param string $path
	 * @param array $params
	 */
	public function head(string $path, array $params = []): void
	{
		$this->request(self::HEAD, $path, $params);
	}


	/**
	 * @param string $path
	 * @param array $params
	 */
	public function get(string $path, array $params = []): void
	{
		$this->request(self::GET, $path, $params);
	}

	/**
	 * @param string $path
	 * @param array $params
	 */
	public function option(string $path, array $params = []): void
	{
		$this->request(self::OPTIONS, $path, $params);
	}

	/**
	 * @param string $path
	 * @param array $params
	 */
	public function delete(string $path, array $params = []): void
	{
		$this->request(self::DELETE, $path, $params);
	}

	/**
	 * @param string $path
	 * @param array $params
	 */
	public function options(string $path, array $params = []): void
	{
		$this->request(self::OPTIONS, $path, $params);

	}

	/**
	 * @param string $path
	 * @param array $params
	 */
	public function upload(string $path, array $params = []): void
	{
		$this->request(self::UPLOAD, $path, $params);
	}


	/**
	 * @return string
	 */
	public function getHost(): string
	{
		return $this->host;
	}

	/**
	 * @return int
	 */
	#[Pure] protected function getHostPort(): int
	{
		if (!empty($this->getPort())) {
			return $this->getPort();
		}
		$port = 80;
		if ($this->isSSL()) $port = 443;
		return $port;
	}


	/**
	 * @param string $host
	 * @return ClientAbstracts
	 */
	private function withHost(string $host): static
	{
		$this->host = $host;
		if (!preg_match('/(\d{1,3}\.){3}\d{1,3}/', $host)) {
			if (Context::inCoroutine()) {
				$this->host = System::gethostbyname($host);
			}
			return $this->withAddedHeader('Host', $host);
		}
		return $this;
	}

	/**
	 * @return array
	 */
	public function getHeader(): array
	{
		return $this->header;
	}


	/**
	 * @return mixed|null
	 */
	public function getContentType(): ?string
	{
		return $this->header['Content-Type'] ?? $this->header['content-type'] ?? NULL;
	}


	/**
	 * @param array $header
	 * @return ClientAbstracts
	 */
	public function withHeader(array $header): static
	{
		$this->header = $header;
		return $this;
	}


	/**
	 * @param array $header
	 * @return ClientAbstracts
	 */
	public function withHeaders(array $header): static
	{
		if (empty($header)) {
			return $this;
		}
		foreach ($header as $key => $val) {
			$this->header[$key] = $val;
		}
		return $this;
	}

	/**
	 * @param $key
	 * @param $value
	 * @return ClientAbstracts
	 */
	public function withAddedHeader($key, $value): static
	{
		$this->header[$key] = $value;
		return $this;
	}

	/**
	 * @return int
	 */
	public function getTimeout(): int
	{
		return $this->timeout;
	}

	/**
	 * @param int $value
	 * @return ClientAbstracts
	 */
	public function withTimeout(int $value): static
	{
		$this->timeout = $value;
		return $this;
	}


	/**
	 * @param Closure|null $value
	 * @return ClientAbstracts
	 */
	public function withCallback(?Closure $value): static
	{
		return $this;
	}

	/**
	 * @return string
	 */
	public function getMethod(): string
	{
		return $this->method;
	}

	/**
	 * @param string $value
	 * @return static
	 */
	public function withMethod(string $value): static
	{
		$this->method = $value;
		return $this;
	}

	/**
	 * @return bool
	 */
	public function isSSL(): bool
	{
		return $this->isSSL;
	}

	/**
	 * @param bool $isSSL
	 * @return ClientAbstracts
	 */
	public function withIsSSL(bool $isSSL): static
	{
		$this->isSSL = $isSSL;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getAgent(): string
	{
		return $this->agent;
	}

	/**
	 * @param string $agent
	 * @return ClientAbstracts
	 */
	public function withAgent(string $agent): static
	{
		$this->agent = $agent;
		return $this;
	}


	/**
	 * @return string
	 */
	public function getSslCertFile(): string
	{
		return $this->ssl_cert_file;
	}

	/**
	 * @param string $ssl_cert_file
	 * @return ClientAbstracts
	 */
	public function withSslCertFile(string $ssl_cert_file): static
	{
		$this->ssl_cert_file = $ssl_cert_file;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getSslKeyFile(): string
	{
		return $this->ssl_key_file;
	}

	/**
	 * @param string $ssl_key_file
	 * @return ClientAbstracts
	 */
	public function withSslKeyFile(string $ssl_key_file): static
	{
		$this->ssl_key_file = $ssl_key_file;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getCa(): string
	{
		return $this->ca;
	}

	/**
	 * @param string $ssl_key_file
	 * @return static
	 */
	public function withCa(string $ssl_key_file): static
	{
		$this->ca = $ssl_key_file;
		return $this;
	}

	/**
	 * @return int
	 */
	#[Pure] public function getPort(): int
	{
		if ($this->isSSL()) {
			return 443;
		}
		if (empty($this->port)) {
			return 80;
		}
		return $this->port;
	}

	/**
	 * @param int $port
	 * @return ClientAbstracts
	 */
	private function withPort(int $port): static
	{
		$this->port = $port;
		return $this;
	}


	/**
	 * @return StreamInterface
	 */
	public function getData(): StreamInterface
	{
		if (!$this->_data) {
			$this->_data = new Stream();
		}
		return $this->_data;
	}

	/**
	 * @param string|StreamInterface $data
	 * @return ClientAbstracts
	 */
	public function withBody(string|StreamInterface $data): static
	{
		if (is_string($data)) {
			$data = new Stream($data);
		}
		$this->_data = $data;
		return $this;
	}

	/**
	 * @return int
	 */
	public function getConnectTimeout(): int
	{
		return $this->connect_timeout;
	}

	/**
	 * @param int $connect_timeout
	 * @return ClientAbstracts
	 */
	public function withConnectTimeout(int $connect_timeout): static
	{
		$this->connect_timeout = $connect_timeout;
		return $this;
	}


	/**
	 * @param $host
	 * @return string|string[]
	 */
	protected function replaceHost($host): array|string
	{
		if ($this->isHttp($host)) {
			return str_replace('http://', '', $host);
		}
		if ($this->isHttps($host)) {
			return str_replace('https://', '', $host);
		}
		return $host;
	}


	/**
	 * @param $url
	 * @return false|int
	 */
	protected function checkIsIp($url): bool|int
	{
		return preg_match('/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/', $url);
	}

	/**
	 * @param $url
	 * @return bool
	 */
	protected function isHttp($url): bool
	{
		return str_starts_with($url, 'http://');
	}

	/**
	 * @param $url
	 * @return bool
	 */
	protected function isHttps($url): bool
	{
		return str_starts_with($url, 'https://');
	}


	/**
	 * @param $newData
	 * @return string|null
	 */
	protected function mergeParams($newData): ?string
	{
		if (!is_string($newData)) {
			return $this->toRequest($newData);
		}
		return (string)$newData;
	}


	/**
	 * @param $data
	 * @return string
	 */
	protected function toRequest($data): string
	{
		if (is_string($data)) {
			return $data;
		}
		$contentType = 'application/x-www-form-urlencoded';
		if (isset($this->header['Content-Type'])) {
			$contentType = $this->header['Content-Type'];
		} else if (isset($this->header['content-type'])) {
			$contentType = $this->header['content-type'];
		}
		if (str_contains($contentType, 'json')) {
			return Help::toJson($data);
		} else if (str_contains($contentType, 'xml')) {
			return Help::toXml($data);
		} else {
			return http_build_query($data);
		}
	}


	/**
	 * @param $data
	 * @param $body
	 * @return array|string|null
	 * @throws Exception
	 */
	protected function resolve($data, $body): array|string|null
	{
		if (is_array($body)) {
			return $body;
		}
		$type = $data['content-type'] ?? $data['Content-Type'] ?? 'text/html';
		if (str_contains($type, 'text/html')) {
			return $body;
		} else if (str_contains($type, 'json')) {
			return json_decode($body, TRUE);
		} else if (str_contains($type, 'xml')) {
			return Help::xmlToArray($body);
		} else if (str_contains($type, 'plain')) {
			return Help::toArray($body);
		}
		return $body;
	}


	/**
	 * @return bool
	 * check isPost Request
	 */
	#[Pure] protected function isPost(): bool
	{
		return strtolower($this->method) === self::POST;
	}

	/**
	 * @return bool
	 * check isPost Request
	 */
	#[Pure] protected function isUpload(): bool
	{
		return strtolower($this->method) === self::UPLOAD;
	}


	/**
	 * @return bool
	 *
	 * check isGet Request
	 */
	#[Pure] protected function isGet(): bool
	{
		return strtolower($this->method) === self::GET;
	}

	/**
	 * @param        $arr
	 *
	 * @return array|string
	 * ???????????????????????????
	 */
	#[Pure] protected function paramEncode($arr): array|string
	{
		if (!is_array($arr)) {
			return $arr;
		}
		$_tmp = [];
		foreach ($arr as $Key => $val) {
			$_tmp[$Key] = $val;
		}
		if ($this->isGet()) {
			return http_build_query($_tmp);
		}
		return $_tmp;
	}


	/**
	 * @param string $string
	 * @return array
	 */
	#[Pure] protected function matchHost(string $string): array
	{
		return [$this->host, $this->isSSL(), $string];
	}


	/**
	 * @param $path
	 * @param $params
	 * @return string
	 */
	protected function joinGetParams($path, $params): string
	{
		if (empty($params)) {
			return $path;
		}
		if (!is_string($params)) {
			$params = http_build_query($params);
		}
		if (str_contains($path, '?')) {
			[$path, $getParams] = explode('?', $path);
		}
		if (empty($getParams)) {
			return $path . '?' . $params;
		}
		return $path . '?' . $params . '&' . $getParams;
	}

}
