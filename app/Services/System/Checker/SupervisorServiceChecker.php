<?php

namespace App\Services\System\Checker;

use App\Services\System\BaseSystemServiceInfo;
use App\Services\System\SystemServiceInfo;

class SupervisorServiceChecker extends BaseSystemServiceInfo implements SystemServiceInfo
{
    public function getInfo($isCheckDocker = false): array
    {
        $service = $this->getSysService();
        if ($isCheckDocker) {
            $service = array_merge($service, $this->getDockerContainer());
        }
        return $service;
    }

    public function getSysService(): array
    {
        return $this->getServiceSystem('supervisor');
    }

    public function getDockerContainer(): array
    {
        return $this->getServiceInDockerContainer('supervisor');
    }
}
