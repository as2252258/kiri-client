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
        $params['ssl_host_name'] = $this->getHost();
        if (!empty($sslCert)) {
            $params['ssl_cert_file'] = $this->getSslCertFile();
        }
        if (!empty($sslKey)) {
            $params['ssl_key_file'] = $this->getSslKeyFile();
        }
        if (!empty($sslCa)) {
            $params['ssl_cafile'] = $sslCa;
        }
		return $params;
	}

}
