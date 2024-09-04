<?php

namespace App\Http\Controllers;

use App\Services\ServiceSystemInfo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceInfoController extends Controller
{
    protected $serviceSystemInfo;

    public function __construct(ServiceSystemInfo $serviceSystemInfo)
    {
        $this->serviceSystemInfo = $serviceSystemInfo;
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $classList = $request->get('class_list', []);

        if (empty($classList)) {
            return response()->json(
                [
                    "error"   => "Bad Request",
                    'data'    => null,
                    'message' => 'Invalid input provided'
                ], 400
            );
        }
        $dataRes = $this->serviceSystemInfo->getInfoService($classList);
        return response()->json(
            [
                'data'    => $dataRes,
                'message' => 'Success'
            ], 200
        );
    }
}
