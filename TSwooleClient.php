<?php

namespace Kiri;

trait TSwooleClient
{


	/**
	 * @return array
	 */
	private function settings(): array
	{
		$sslCert = $this->getSslCertFile();
		$sslKey = $this->getSslKeyFile();
		$sslCa = $this->getCa();

		$params = [];
		if ($this->getConnectTimeout() > 0) {
			$params['timeout'] = $this->getConnectTimeout();
		}

        [$proxy, $port] = [$this->getProxyHost(), $this->getProxyPort()];
        if (!empty($proxy) && $port > 0) {
            $params['http_proxy_host'] = $proxy;
            $params['http_proxy_port'] = $port;
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
