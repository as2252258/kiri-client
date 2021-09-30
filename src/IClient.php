<?php


namespace Http\Handler\Client;


use Closure;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

interface IClient
{


	/**
	 * @param string $path
	 * @param array $params
	 * @return ResponseInterface
	 */
	public function get(string $path, array $params = []): ResponseInterface;


	/**
	 * @param string $path
	 * @param array $params
	 * @return ResponseInterface
	 */
	public function post(string $path, array $params = []): ResponseInterface;


	/**
	 * @param string $path
	 * @param array $params
	 * @return ResponseInterface
	 */
	public function delete(string $path, array $params = []): ResponseInterface;


	/**
	 * @param string $path
	 * @param array $params
	 * @return ResponseInterface
	 */
	public function options(string $path, array $params = []): ResponseInterface;


	/**
	 * @param string $path
	 * @param array $params
	 * @return ResponseInterface
	 */
	public function upload(string $path, array $params = []): ResponseInterface;


	/**
	 * @param string $path
	 * @param array $params
	 * @return ResponseInterface
	 */
	public function put(string $path, array $params = []): ResponseInterface;


	/**
	 * @param string $path
	 * @param array $params
	 * @return ResponseInterface
	 */
	public function head(string $path, array $params = []): ResponseInterface;


	/**
	 * @param string $method
	 * @param string $path
	 * @param array $params
	 * @return ResponseInterface
	 */
	public function request(string $method, string $path, array $params = []): ResponseInterface;


	/**
	 * @param array $header
	 * @return static
	 */
	public function withHeader(array $header): static;


	/**
	 * @param array $header
	 * @return static
	 */
	public function withHeaders(array $header): static;


	/**
	 * @param string $key
	 * @param string $value
	 * @return static
	 */
	public function withAddedHeader(string $key, string $value): static;


	/**
	 * @param int $value
	 * @return static
	 */
	public function withTimeout(int $value): static;


	/**
	 * @param Closure|null $value
	 * @return static
	 */
	public function withCallback(?Closure $value): static;


	/**
	 * @param string $value
	 * @return static
	 */
	public function withMethod(string $value): static;


	/**
	 * @param bool $isSSL
	 * @return static
	 */
	public function withIsSSL(bool $isSSL): static;


	/**
	 * @param string $agent
	 * @return static
	 */
	public function withAgent(string $agent): static;


	/**
	 * @param string $ssl_cert_file
	 * @return static
	 */
	public function withSslCertFile(string $ssl_cert_file): static;


	/**
	 * @param string $ssl_key_file
	 * @return static
	 */
	public function withSslKeyFile(string $ssl_key_file): static;


	/**
	 * @param string $ssl_key_file
	 * @return static
	 */
	public function withCa(string $ssl_key_file): static;


	/**
	 * @param string|StreamInterface $data
	 * @return static
	 */
	public function withBody(string|StreamInterface $data): static;


	/**
	 * @param int $connect_timeout
	 * @return static
	 */
	public function withConnectTimeout(int $connect_timeout): static;


	/**
	 * @param string $contentType
	 * @return static
	 */
	public function withContentType(string $contentType): static;
}
