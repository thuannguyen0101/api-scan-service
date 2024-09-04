<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class ServiceSystemInfo
{
    public function getInfoService($classList = []): array
    {
        $isCheckDocker = false;
        $listService   = [];

        if (in_array('Docker', $classList)) {
            $isCheckDocker = true;
        }

        foreach ($classList as $class) {
            $classPath = 'App\\Services\\System\\Checker\\' . $class . 'ServiceChecker';
            if (class_exists($classPath)) {
                $instance = app($classPath);
                if ($class == 'Docker') {
                    $listService[$class] = $instance->getInfo();
                } else {
                    $listService[$class] = $instance->getInfo($isCheckDocker);
                }

            } else {
                Log::error("Class $classPath does not exist.\n");
                $listService[$class] = [];
            }
        }

        return $listService;
    }
}
