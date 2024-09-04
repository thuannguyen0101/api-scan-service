<?php

namespace App\Services\System;

interface SystemServiceInfo
{
    public function getInfo($isCheckDocker = false): array;
    public function getSysService(): array;
    public function getDockerContainer(): array;
}
