<?php

namespace App\Services\System;

use Carbon\Carbon;

abstract class BaseSystemServiceInfo
{
    protected function convertTime(string $startTime, $endTime = null, $isContainerDocker = false): string
    {
        $endTime = $endTime ?: Carbon::now();

        if ($isContainerDocker) {
            $startTime = str_replace('Z', '+00:00', $startTime);
            $startTime = preg_replace('/(\.\d{6})\d+/', '$1', $startTime);
        }


        $serviceStartTime = strtotime($startTime);
        $startTime        = Carbon::parse($serviceStartTime);
        $diff             = $startTime->diff($endTime);

        if ($diff->y > 0) {
            return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
        } elseif ($diff->m > 0) {
            return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
        } elseif ($diff->d > 0) {
            return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
        } elseif ($diff->h > 0) {
            return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
        } elseif ($diff->i > 0) {
            return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
        } else {
            return 'just now';
        }
    }

    protected function getServiceSystem(string $serviceName, array $serviceNameNotIn = []): array
    {

        $output       = shell_exec("systemctl list-units --type=service --all | grep $serviceName");
//        if ($serviceName == 'mysql'){
//            dd($output);
//        }
        if ($output === null){
            return [];
        }
        $listServices = explode(PHP_EOL, trim($output));
        $endTime      = Carbon::now();
        $serviceMesh  = [];

        foreach ($listServices as $service) {
            $service            = preg_split('/\s+/', ltrim($service), 5);
            $service_name       = $service[0];
            $service_status     = $service[2];
            $service_sub_status = $service[3];
            $uptime             = "0";
            $downtime           = "0";
            $log_info           = null;
            $is_reloaded        = false;

            if (in_array($service_name, $serviceNameNotIn)) {
                continue;
            }

            $log = shell_exec("journalctl -u $service_name --since '5 minutes ago'");

            switch ($service_status) {
                case $service_status == 'active':
                {
                    $execMainStartTimestamp = shell_exec("systemctl show $service_name --property=ActiveEnterTimestamp --value");
                    $uptime                 = $this->convertTime($execMainStartTimestamp, $endTime);

                    $checkReloaded = shell_exec("journalctl -u $service_name --since '5 minutes ago' | grep 'Reload'");
                    $listReloaded  = $checkReloaded ? explode(PHP_EOL, trim($checkReloaded)) : [];

                    if (count($listReloaded) > 0) {
                        $is_reloaded = true;
                        $log_info    = $checkReloaded;
                    }
                    break;
                }

                case $service_status == 'inactive':
                {
                    $execMainExitTimestamp = shell_exec("systemctl show $service_name --property=InactiveEnterTimestamp --value");
                    $downtime              = $this->convertTime($execMainExitTimestamp, $endTime);
                    $log_info              = $log;
                    break;
                }

                case $service_status == 'failed':
                {
                    $execMainExitTimestamp = shell_exec("systemctl show $service_name --property=ExecMainExitTimestamp --value");
                    $downtime              = $this->convertTime($execMainExitTimestamp, $endTime);
                    $log_info              = $log;
                    break;
                }

                case $service_status == 'activating':
                {
                    $stateChangeTimestamp = shell_exec("systemctl show $service_name --property=StateChangeTimestamp --value");
                    $uptime               = $this->convertTime($stateChangeTimestamp, $endTime);
                    $log_info             = $log;
                    break;
                }

                case $service_status == 'deactivating':
                {
                    $stateChangeTimestamp = shell_exec("systemctl show $service_name --property=StateChangeTimestamp --value");
                    $downtime             = $this->convertTime($stateChangeTimestamp, $endTime);
                    $log_info             = $log;
                    break;
                }
            }

            $serviceMesh[] = [
                'name'        => $service_name,
                'status'      => "$service_status ($service_sub_status)",
                'sub_status'  => $service_sub_status,
                'uptime'      => $uptime,
                'downtime'    => $downtime,
                'log_info'    => $log_info,
                'type'        => 'system',
                'is_reloaded' => $is_reloaded
            ];
        }

        return $serviceMesh;
    }

    public function getServiceInDockerContainer(string $serviceName, array $serviceNameNotIn = []): array
    {
        $container          = shell_exec("docker ps -a --filter 'name=$serviceName' 2>&1 --format '{{.ID}} {{.Image}} {{.Status}}'  | grep -v -E 'exporter'" );
        if ($container === null){
            return [];
        }
        $listContainer      = explode(PHP_EOL, trim($container));
        $serviceInContainer = [];
        $endTime            = Carbon::now();

        foreach ($listContainer as $container) {
            $container            = preg_split('/\s+/', ltrim($container), 3);
            $container_image_name = $container[1];
            $container_id         = $container[0];
            $container_info       = json_decode(shell_exec("docker inspect $container_id"), true);

            if (empty($container_info)) {
                continue;
            }

            if (isset($container_info[0]['State'])) {
                $state           = $container_info[0]['State'];
                $uptime          = $downtime = "0h0";
                $status          = $state['Status'] ?? 'unknown';
                $statusSystem    = $status;
                $subStatusSystem = null;
                $log_info        = null;

                switch ($status) {
                    case $status === 'created':
                    {
                        $uptime          = $this->convertTime($container_info[0]['Created'] ?? '', $endTime, true);
                        $statusSystem    = 'inactive';
                        $subStatusSystem = 'loaded';

                        break;
                    }
                    case $status === 'running':
                    {
                        $uptime          = $this->convertTime($state['StartedAt'] ?? '', $endTime, true);
                        $statusSystem    = 'active';
                        $subStatusSystem = 'running';

                        break;
                    }
                    case $status === 'paused':
                    {
                        $uptime          = $this->convertTime($state['StartedAt'] ?? '', $endTime, true);
                        $log_info        = shell_exec("docker logs --tail 20 2>&1 $container_id");
                        $statusSystem    = 'active';
                        $subStatusSystem = 'exited';

                        break;
                    }
                    case $status === 'stopped':
                    {
                        $downtime        = $this->convertTime($state['FinishedAt'] ?? '', $endTime, true);
                        $log_info        = shell_exec("docker logs --tail 20 $container_id  2>&1  | grep -iE 'error|crit|debug|emerg|alert'");
                        $statusSystem    = 'inactive';
                        $subStatusSystem = 'dead';

                        break;
                    }
                    case $status === 'exited':
                    {
                        $downtime        = $this->convertTime($state['FinishedAt'] ?? '', $endTime, true);
                        $statusSystem    = 'inactive';
                        $subStatusSystem = 'dead';

                        if ($state['ExitCode'] != 0) {
                            $log_info = "ExitCode: " . $state['ExitCode'] . "\n";
                            $log_info .= shell_exec("docker logs --tail 20 $container_id 2>&1 | grep -iE 'error|crit|debug|emerg|alert'");
                        }

                        break;
                    }
                    case $status === 'removing':
                    {
                        $downtime     = $this->convertTime($state['FinishedAt'] ?? '', $endTime, true);
                        $log_info     = shell_exec("docker logs --tail 20 $container_id");
                        $log_info     .= shell_exec("journalctl -u docker.service --since '5 minutes ago'");
                        $statusSystem = 'deactivating';

                        break;
                    }
                    case $status === 'dead':
                    {
                        $downtime     = $this->convertTime($state['FinishedAt'] ?? '', $endTime, true);
                        $statusSystem = 'failed';
                        if ($state['ExitCode'] != 0) {
                            $log_info = shell_exec("docker logs $container_id 2>&1 | grep -iE 'error|crit|debug|emerg|alert'");
                        }

                        break;
                    }
                }

                $serviceInContainer[] = [
                    'name'        => $container_image_name,
                    'status'      => $statusSystem . " ($subStatusSystem)",
                    'sub_status'  => $subStatusSystem,
                    'uptime'      => $uptime,
                    'downtime'    => $downtime,
                    'log_info'    => $log_info,
                    'type'        => 'docker',
                    'is_reloaded' => false
                ];
            }

        }
        return $serviceInContainer;
    }
}
