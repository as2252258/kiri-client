<?php

namespace Http\Client;

use Kiri\Context;


/**
 * @mixin CoroutineClient|Curl
 */
class Client
{


	private CoroutineClient|Curl|AsyncClient $abstracts;


	/**
	 * @param string $host
	 * @param int $port
	 * @param bool $isSsl
	 */
	public function __construct(string $host, int $port, bool $isSsl = false)
	{
		if (Context::inCoroutine()) {
			$this->abstracts = new CoroutineClient($host, $port, $isSsl);
		} else {
			$this->abstracts = new AsyncClient($host, $port, $isSsl);
		}
	}


	/**
	 * @param string $name
	 * @param array $arguments
	 * @return mixed
	 */
	public function __call(string $name, array $arguments)
	{
		return $this->abstracts->{$name}(...$arguments);
	}

}
