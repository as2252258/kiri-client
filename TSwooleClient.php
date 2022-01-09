<?php

namespace Http\Client;

use JetBrains\PhpStorm\Pure;

trait TSwooleClient
{



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
        $params['ssl_verify_peer'] = TRUE;
        $params['ssl_cafile'] = $sslCa;

        return $params;
    }

}