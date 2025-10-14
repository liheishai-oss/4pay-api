<?php
return [
    '' => [
//        app\middleware\ServerStatusMiddleware::class, // 服务器状态检查（优先级最高）
//        app\middleware\MaintenanceCheckMiddleware::class, // 维护状态检查 - 临时禁用
//        app\middleware\Debug::class,
//        app\middleware\AutoAddCurrentIpMiddleware::class, // 自动添加当前外网IP - 临时禁用
        app\middleware\CrossDomain::class,//跨域请求
        app\middleware\TraceMiddleware::class,
//        app\middleware\Auth::class
    ],


];