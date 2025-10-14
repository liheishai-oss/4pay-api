<?php

namespace app\api\controller\v1\callback;

use support\Request;
use support\Response;

class TestController
{
    public function test(Request $request): Response
    {
        \support\Log::info('TestController 被调用', [
            'request_method' => $request->method(),
            'request_uri' => $request->uri(),
            'route_params' => $request->route ?? []
        ]);
        
        return json(['code' => 200, 'message' => 'Test success', 'data' => ['route_params' => $request->route ?? []]]);
    }
}
