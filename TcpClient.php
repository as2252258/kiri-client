<?php

namespace Kiri;

use Exception;
use Kiri\Di\Context;
use Swoole\Coroutine\Client as CoroutineClient;
use Swoole\Client as AsyncClient;


class TcpClient
{


    /**
     * @var AsyncClient|CoroutineClient
     */
    protected AsyncClient|CoroutineClient $client;


    /**
     * @param string $host
     * @param int $port
     * @param int $socket
     * @throws
     */
    public function __construct(readonly public string $host, readonly public int $port, readonly public int $socket = SWOOLE_SOCK_TCP)
    {
        $this->reconnect();
    }


    /**
     * @return void
     * @throws
     */
    public function reconnect(): void
    {
        $this->client?->close();
        if (Context::inCoroutine()) {
            $this->client = new CoroutineClient($this->socket);
        } else {
            $this->client = new AsyncClient($this->socket);
        }
        if (!$this->client->connect($this->host, $this->port, 1)) {
            throw new Exception('Connect ' . $this->host . '::' . $this->port . ' fail');
        }
    }


    /**
     * @param string $data
     * @return int|bool
     */
    public function send(string $data): int|bool
    {
        return $this->client->send($data);
    }


    /**
     * @return bool|string
     */
    public function read(): bool|string
    {
        return $this->client->recv();
    }


    /**
     * @return bool
     */
    public function isConnected(): bool
    {
        return $this->client->isConnected();
    }


    /**
     * @return bool
     */
    public function close(): bool
    {
        return $this->client->close();
    }

}