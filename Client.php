<?php

namespace Kiri;

use Swoole\Coroutine;


/**
 * @mixin CoroutineClient|CurlClient
 */
class Client
{


	private CoroutineClient|CurlClient $abstracts;


    /**
     * @param string $host
     * @param int $port
     * @param bool $isSsl
     * @param bool $useCurl
     */
	public function __construct(string $host, int $port, bool $isSsl = false, bool $useCurl = false)
	{
        if ($useCurl) {
            $this->abstracts = new CurlClient($host, $port, $isSsl);
            return;
        }
		if (class_exists(Coroutine::class) && Coroutine::getCid() > -1) {
			$this->abstracts = new CoroutineClient($host, $port, $isSsl);
		} else {
			$this->abstracts = new CurlClient($host, $port, $isSsl);
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
