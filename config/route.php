<?php

use app\admin\controller\v1\system\LoginController;
use Webman\Route;

// 定义后台管理路由
Route::group('/api/v1/admin', function() {
    // 生成谷歌验证码二维码
    Route::add(['GET', 'OPTIONS'], '/google-auth/qrcode/{secret}', [app\admin\controller\v1\GoogleAuthController::class, 'generateQrCode']);
    Route::add(['GET', 'OPTIONS'], '/system/rule', [app\admin\controller\v1\MenuRuleController::class, 'rule']);

    Route::add(['GET', 'OPTIONS'], '/dashboard/ranking', [app\admin\controller\v1\DashboardController::class, 'getChannelRanking']);
    // 测试路由
    Route::add(['GET', 'OPTIONS'], '/test/server-list', [app\admin\controller\v1\TestController::class, 'serverList']);
    Route::add(['POST', 'OPTIONS'],'/upload', [app\admin\controller\v1\UploadController::class, 'upload']);
    Route::add(['POST', 'OPTIONS'],'/upload/images', [app\admin\controller\v1\UploadController::class, 'images']);
    
    // 回调监控路由
    Route::group('/callback-monitor', function () {
        // 获取未通知订单列表
        Route::add(['GET', 'OPTIONS'], '/unnotified-orders', [app\admin\controller\v1\CallbackMonitorController::class, 'getUnnotifiedOrders']);
        // 手动触发订单通知
        Route::add(['POST', 'OPTIONS'], '/trigger-notification', [app\admin\controller\v1\CallbackMonitorController::class, 'triggerNotification']);
        // 批量触发通知
        Route::add(['POST', 'OPTIONS'], '/batch-trigger-notification', [app\admin\controller\v1\CallbackMonitorController::class, 'batchTriggerNotification']);
        // 获取通知统计信息
        Route::add(['GET', 'OPTIONS'], '/notification-stats', [app\admin\controller\v1\CallbackMonitorController::class, 'getNotificationStats']);
        // 修复未通知订单
        Route::add(['POST', 'OPTIONS'], '/fix-unnotified-orders', [app\admin\controller\v1\CallbackMonitorController::class, 'fixUnnotifiedOrders']);
    });

    
    // 财务管理路由
    Route::group('/finance', function () {
        // 供应商余额变动记录
        Route::add(['GET', 'OPTIONS'], '/supplier-balance-log', [app\admin\controller\v1\finance\SupplierBalanceLogController::class, 'index']);
        Route::add(['GET', 'OPTIONS'], '/supplier-balance-log/statistics', [app\admin\controller\v1\finance\SupplierBalanceLogController::class, 'statistics']);
        Route::add(['GET', 'OPTIONS'], '/supplier-balance-log/{id}', [app\admin\controller\v1\finance\SupplierBalanceLogController::class, 'show']);
    });
    // 菜单规则管理
    Route::group('/menu-rule', function () {
        // 获取菜单规则列表
        Route::add(['GET', 'OPTIONS'], '', [app\admin\controller\v1\MenuRuleController::class, 'index']);
        // 获取子节点
        Route::add(['GET', 'OPTIONS'], '/children/{rule_id}', [app\admin\controller\v1\MenuRuleController::class, 'children']);

//        // 获取单个菜单规则详情
//
//
//        // 创建菜单规则
        Route::add(['POST', 'OPTIONS'], '/add', [app\admin\controller\v1\MenuRuleController::class, 'store']);
//
//        // 更新菜单规则
        Route::add(['POST', 'OPTIONS'], '/edit', [app\admin\controller\v1\MenuRuleController::class, 'edit']);
//
//        // 删除菜单规则
        Route::add(['POST', 'OPTIONS'], '/delete', [app\admin\controller\v1\MenuRuleController::class, 'destroy']);
//
        // 获取下拉菜单数据
        Route::add(['GET', 'OPTIONS'], '/dropdown/{group_id}', [app\admin\controller\v1\MenuRuleController::class, 'dropdown']);
    });

    // 系统日志
    Route::group('/system/log', function () {
        // 获取日志表
        Route::add(['GET', 'OPTIONS'], '', [app\admin\controller\v1\AdminLogController::class, 'index']);
        // 删除日志
        Route::add(['POST', 'OPTIONS'], '/delete', [app\admin\controller\v1\AdminLogController::class, 'destroy']);
    });

    // 系统配置
    Route::group('/system/config', function () {
        // 获取配置列表
        Route::add(['GET', 'OPTIONS'], '/list', [app\admin\controller\v1\SystemConfigController::class, 'index']);
        // 保存配置
        Route::add(['POST', 'OPTIONS'], '/save', [app\admin\controller\v1\SystemConfigController::class, 'save']);
        // 获取配置分组
        Route::add(['GET', 'OPTIONS'], '/groups', [app\admin\controller\v1\SystemConfigController::class, 'getGroups']);
        // 重置配置
        Route::add(['POST', 'OPTIONS'], '/reset', [app\admin\controller\v1\SystemConfigController::class, 'reset']);
    });




    // 系统菜单
    Route::add(['GET', 'OPTIONS'],'/menu', [app\admin\controller\v1\AdminController::class, 'menu']);
    Route::group('/user', function () {
        Route::add(['GET', 'OPTIONS'], '/info', [app\admin\controller\v1\AdminController::class, 'info']);
        Route::add(['GET', 'OPTIONS'],'/index', [app\admin\controller\v1\AdminController::class, 'index']);
        Route::add(['POST', 'OPTIONS'], '/add', [app\admin\controller\v1\AdminController::class, 'store']);
        Route::add(['POST', 'OPTIONS'], '/edit', [app\admin\controller\v1\AdminController::class, 'store']);
        Route::add(['GET', 'OPTIONS'], '/detail/{id}', [app\admin\controller\v1\AdminController::class, 'detail']);
        Route::add(['POST', 'OPTIONS'], '/delete', [app\admin\controller\v1\AdminController::class, 'destroy']);
        Route::add(['POST', 'OPTIONS'], '/switch', [app\admin\controller\v1\AdminController::class, 'switch']);
        // 系统登录
//        Route::add(['POST', 'OPTIONS'],'/login', [app\admin\controller\v1\AdminController::class, 'login']);
        Route::add(['POST', 'OPTIONS'],'/login', [LoginController::class, 'login']);
        Route::group('/group', function () {
            Route::add(['GET', 'OPTIONS'],'', [app\admin\controller\v1\AdminGroupController::class, 'index']);
            Route::add(['GET', 'OPTIONS'],'/dropdown/{group_id}', [app\admin\controller\v1\AdminGroupController::class, 'dropdown']);
            Route::add(['POST', 'OPTIONS'], '/add', [app\admin\controller\v1\AdminGroupController::class, 'store']);
            Route::add(['POST', 'OPTIONS'], '/edit', [app\admin\controller\v1\AdminGroupController::class, 'store']);
            Route::add(['GET', 'OPTIONS'], '/detail/{id}', [app\admin\controller\v1\AdminGroupController::class, 'detail']);
            Route::add(['POST', 'OPTIONS'], '/delete', [app\admin\controller\v1\AdminGroupController::class, 'destroy']);
        });
    });

    Route::group('/telegram-admin', function () {
        // 获取列表
        Route::add(['GET', 'OPTIONS'], '', [app\admin\controller\v1\telegram\admin\IndexController::class, 'index']);
        // 获取详情
        Route::add(['GET', 'OPTIONS'], '/detail/{id}', [app\admin\controller\v1\telegram\admin\DetailController::class, 'show']);
        // 新增
        Route::add(['POST', 'OPTIONS'], '/add', [app\admin\controller\v1\telegram\admin\StoreController::class, 'store']);
        // 编辑
        Route::add(['POST', 'OPTIONS'], '/edit', [app\admin\controller\v1\telegram\admin\EditAdminController::class, 'update']);
        // 删除
        Route::add(['POST', 'OPTIONS'], '/delete', [app\admin\controller\v1\telegram\admin\DestroyController::class, 'destroy']);
        // 状态开关
        Route::add(['POST', 'OPTIONS'], '/status-switch', [app\admin\controller\v1\telegram\admin\StatusSwitchController::class, 'toggle']);
    });

    Route::group('/supplier', function () {
        // 获取供应商列表
        Route::add(['GET', 'OPTIONS'], '', [app\admin\controller\v1\supplier\IndexController::class, 'index']);

        // 获取供应商详情
        Route::add(['GET', 'OPTIONS'], '/detail/{id}', [app\admin\controller\v1\supplier\DetailController::class, 'show']);

        // 新增供应商
        Route::add(['POST', 'OPTIONS'], '/add', [app\admin\controller\v1\supplier\StoreController::class, 'store']);

        // 编辑供应商
        Route::add(['POST', 'OPTIONS'], '/edit', [app\admin\controller\v1\supplier\EditController::class, 'update']);

        // 删除供应商
        Route::add(['POST', 'OPTIONS'], '/delete', [app\admin\controller\v1\supplier\DestroyController::class, 'destroy']);

        // 批量删除供应商
        Route::add(['POST', 'OPTIONS'], '/batch-delete', [app\admin\controller\v1\supplier\DestroyController::class, 'batchDestroy']);

        // 状态开关
        Route::add(['POST', 'OPTIONS'], '/status-switch', [app\admin\controller\v1\supplier\StatusSwitchController::class, 'toggle']);

        // 预付检验路由（新增）
        Route::add(['POST', 'OPTIONS'], '/prepay-check', [app\admin\controller\v1\supplier\PrepayCheckController::class, 'check']);

        // 获取启用的Telegram管理员列表
        Route::add(['GET', 'OPTIONS'], '/telegram-admins', [app\admin\controller\v1\supplier\TelegramAdminListController::class, 'index']);
        
        // 供应商选择相关路由
        Route::add(['GET', 'OPTIONS'], '/select/all', [app\admin\controller\v1\supplier\SelectController::class, 'getAllSuppliers']);
    });
    Route::group('/payment-channel', function () {
        // 获取收款通道列表
        Route::add(['GET', 'OPTIONS'], '', [app\admin\controller\v1\payment\channel\IndexController::class, 'index']);

        // 获取收款通道详情
        Route::add(['GET', 'OPTIONS'], '/detail/{id}', [app\admin\controller\v1\payment\channel\DetailController::class, 'show']);

        // 新增收款通道
        Route::add(['POST', 'OPTIONS'], '/add', [app\admin\controller\v1\payment\channel\StoreController::class, 'store']);

        // 编辑收款通道
        Route::add(['POST', 'OPTIONS'], '/edit', [app\admin\controller\v1\payment\channel\EditController::class, 'update']);

        // 删除收款通道
        Route::add(['POST', 'OPTIONS'], '/delete', [app\admin\controller\v1\payment\channel\DestroyController::class, 'destroy']);

        // 批量删除收款通道
        Route::add(['POST', 'OPTIONS'], '/batch-delete', [app\admin\controller\v1\payment\channel\DestroyController::class, 'batchDestroy']);

        // 状态开关
        Route::add(['POST', 'OPTIONS'], '/status-switch', [app\admin\controller\v1\payment\channel\StatusSwitchController::class, 'toggle']);
        
        // 通道测试功能
        Route::add(['POST', 'OPTIONS'], '/test', [app\admin\controller\v1\payment\channel\TestController::class, 'testChannel']);


    });

    // 商户管理路由
    Route::group('/merchant', function () {
        // 获取商户列表
        Route::add(['GET', 'OPTIONS'], '', [app\admin\controller\v1\merchant\IndexController::class, 'index']);

        // 获取商户详情
        Route::add(['GET', 'OPTIONS'], '/detail/{id}', [app\admin\controller\v1\merchant\DetailController::class, 'show']);

        // 新增商户
        Route::add(['POST', 'OPTIONS'], '/add', [app\admin\controller\v1\merchant\StoreController::class, 'store']);

        // 编辑商户
        Route::add(['POST', 'OPTIONS'], '/edit', [app\admin\controller\v1\merchant\EditController::class, 'update']);

        // 删除商户
        Route::add(['POST', 'OPTIONS'], '/delete', [app\admin\controller\v1\merchant\DestroyController::class, 'destroy']);

        // 状态开关
        Route::add(['POST', 'OPTIONS'], '/status-switch', [app\admin\controller\v1\merchant\StatusSwitchController::class, 'switch']);
        // 商户产品管理路由
        Route::add(['GET', 'OPTIONS'], '/{merchant_id}/products', [app\admin\controller\v1\product\MerchantAssignmentController::class, 'getMerchantProducts']);
    });

    // 产品管理路由
    Route::group('/product', function () {
        // 获取产品列表
        Route::add(['GET', 'OPTIONS'], '', [app\admin\controller\v1\product\IndexController::class, 'index']);

        // 获取产品详情
        Route::add(['GET', 'OPTIONS'], '/detail/{id}', [app\admin\controller\v1\product\DetailController::class, 'show']);

        // 新增产品
        Route::add(['POST', 'OPTIONS'], '/add', [app\admin\controller\v1\product\StoreController::class, 'store']);

        // 编辑产品
        Route::add(['POST', 'OPTIONS'], '/edit', [app\admin\controller\v1\product\EditController::class, 'update']);

        // 删除产品
        Route::add(['POST', 'OPTIONS'], '/delete', [app\admin\controller\v1\product\DestroyController::class, 'destroy']);

        // 批量删除产品
        Route::add(['POST', 'OPTIONS'], '/batch-delete', [app\admin\controller\v1\product\DestroyController::class, 'batchDestroy']);

        // 状态开关
        Route::add(['POST', 'OPTIONS'], '/status-switch', [app\admin\controller\v1\product\StatusSwitchController::class, 'toggle']);

        // 轮询池管理
        Route::add(['GET', 'OPTIONS'], '/pool/list', [app\admin\controller\v1\product\PoolController::class, 'getPoolList']);
        Route::add(['POST', 'OPTIONS'], '/pool/assign', [app\admin\controller\v1\product\PoolController::class, 'assignToPool']);
        Route::add(['POST', 'OPTIONS'], '/pool/remove', [app\admin\controller\v1\product\PoolController::class, 'removeFromPool']);
        Route::add(['POST', 'OPTIONS'], '/pool/update', [app\admin\controller\v1\product\PoolController::class, 'updatePool']);

        // 商户分配管理
        Route::add(['GET', 'OPTIONS'], '/{product_id}/merchants', [app\admin\controller\v1\product\MerchantAssignmentController::class, 'getMerchantList']);
        Route::add(['POST', 'OPTIONS'], '/assign-merchant', [app\admin\controller\v1\product\MerchantAssignmentController::class, 'assignProductToMerchant']);
        Route::add(['POST', 'OPTIONS'], '/update-merchant-rate', [app\admin\controller\v1\product\MerchantAssignmentController::class, 'updateMerchantRate']);
        Route::add(['POST', 'OPTIONS'], '/remove-assignment', [app\admin\controller\v1\product\MerchantAssignmentController::class, 'removeAssignment']);
        

    });

    // 订单管理路由
    Route::group('/order', function () {
        // 获取订单列表
        Route::add(['GET', 'OPTIONS'], '', [app\admin\controller\v1\OrderManagementController::class, 'index']);

        // 获取订单详情
        Route::add(['GET', 'OPTIONS'], '/{id:\d+}', [app\admin\controller\v1\OrderManagementController::class, 'show']);

        // 获取订单统计
        Route::add(['GET', 'OPTIONS'], '/statistics', [app\admin\controller\v1\OrderManagementController::class, 'statistics']);

        // 补单（支持单个和批量）
        Route::add(['POST', 'OPTIONS'], '/reissue', [app\admin\controller\v1\OrderManagementController::class, 'reissue']);

        // 回调（支持单个和批量）
        Route::add(['POST', 'OPTIONS'], '/callback', [app\admin\controller\v1\OrderManagementController::class, 'callback']);

        // 查单
        Route::add(['POST', 'OPTIONS'], '/query', [app\admin\controller\v1\OrderManagementController::class, 'query']);

        // 获取订单流转日志
        Route::add(['GET', 'OPTIONS'], '/logs', [app\admin\controller\v1\OrderManagementController::class, 'logs']);

    });


    // 服务器管理路由
    Route::group('/server', function () {
        // 服务器CRUD操作
        Route::add(['GET', 'OPTIONS'], '/list', [app\admin\controller\v1\ServerController::class, 'index']);
        Route::add(['GET', 'OPTIONS'], '/detail/{id}', [app\admin\controller\v1\ServerController::class, 'show']);
        Route::add(['POST', 'OPTIONS'], '/add', [app\admin\controller\v1\ServerController::class, 'store']);
        Route::add(['POST', 'OPTIONS'], '/update/{id}', [app\admin\controller\v1\ServerController::class, 'update']);
        Route::add(['POST', 'OPTIONS'], '/toggle-maintenance/{id}', [app\admin\controller\v1\ServerController::class, 'toggleMaintenance']);
        Route::add(['POST', 'OPTIONS'], '/update-status/{id}', [app\admin\controller\v1\ServerController::class, 'updateStatus']);
        Route::add(['DELETE', 'OPTIONS'], '/delete/{id}', [app\admin\controller\v1\ServerController::class, 'destroy']);
        Route::add(['GET', 'OPTIONS'], '/check-maintenance', [app\admin\controller\v1\ServerController::class, 'checkMaintenanceStatus']);
        Route::add(['GET', 'OPTIONS'], '/nginx-config', [app\admin\controller\v1\ServerController::class, 'getNginxConfig']);
        Route::add(['POST', 'OPTIONS'], '/deploy', [app\admin\controller\v1\ServerController::class, 'deploy']);
        
        // 原有服务器状态管理路由
        Route::add(['GET', 'OPTIONS'], '/status', [app\admin\controller\v1\ServerManagementController::class, 'getCurrentServerStatus']);
        Route::add(['GET', 'OPTIONS'], '/all', [app\admin\controller\v1\ServerManagementController::class, 'getAllServersStatus']);
        Route::add(['POST', 'OPTIONS'], '/set-status', [app\admin\controller\v1\ServerManagementController::class, 'setServerStatus']);
        Route::add(['POST', 'OPTIONS'], '/batch-set-status', [app\admin\controller\v1\ServerManagementController::class, 'batchSetServerStatus']);
        Route::add(['GET', 'OPTIONS'], '/health', [app\admin\controller\v1\ServerManagementController::class, 'getServerHealth']);
        Route::add(['POST', 'OPTIONS'], '/remove', [app\admin\controller\v1\ServerManagementController::class, 'removeServer']);
        Route::add(['GET', 'OPTIONS'], '/stats', [app\admin\controller\v1\ServerManagementController::class, 'getServerStats']);
    });

    // 商户回调监控路由
    Route::group('/merchant-callback-monitor', function () {
        Route::add(['GET', 'OPTIONS'], '/real-time-data', [app\admin\controller\v1\MerchantCallbackMonitorController::class, 'getRealTimeData']);
        Route::add(['GET', 'OPTIONS'], '/merchant-stats', [app\admin\controller\v1\MerchantCallbackMonitorController::class, 'getMerchantStats']);
        Route::add(['GET', 'OPTIONS'], '/notify-logs', [app\admin\controller\v1\MerchantCallbackMonitorController::class, 'getNotifyLogs']);
        Route::add(['GET', 'OPTIONS'], '/merchant-detail', [app\admin\controller\v1\MerchantCallbackMonitorController::class, 'getMerchantDetail']);
        Route::add(['POST', 'OPTIONS'], '/reset-circuit-breaker', [app\admin\controller\v1\MerchantCallbackMonitorController::class, 'resetCircuitBreaker']);
        Route::add(['GET', 'OPTIONS'], '/paid-but-callback-failed', [app\admin\controller\v1\MerchantCallbackMonitorController::class, 'getPaidButCallbackFailedOrders']);
        Route::add(['GET', 'OPTIONS'], '/health-status', [app\admin\controller\v1\MerchantCallbackMonitorController::class, 'getHealthStatus']);
        // 超时监控相关路由
        Route::add(['GET', 'OPTIONS'], '/timeout-monitor-stats', [app\admin\controller\v1\MerchantCallbackMonitorController::class, 'getTimeoutMonitorStats']);
        Route::add(['GET', 'OPTIONS'], '/timeout-monitor-status', [app\admin\controller\v1\MerchantCallbackMonitorController::class, 'getTimeoutMonitorStatus']);
        Route::add(['POST', 'OPTIONS'], '/restart-timeout-monitor', [app\admin\controller\v1\MerchantCallbackMonitorController::class, 'restartTimeoutMonitor']);
        // 队列状态路由
        Route::add(['GET', 'OPTIONS'], '/queue-status', [app\admin\controller\v1\MerchantCallbackMonitorController::class, 'getQueueStatus']);
    });

    
    // Dashboard 路由
    Route::add(['GET', 'OPTIONS'], '/dashboard/stats', [app\admin\controller\v1\DashboardController::class, 'getTodayStats']);
    Route::add(['GET', 'OPTIONS'], '/dashboard/trend', [app\admin\controller\v1\DashboardController::class, 'getOrderTrend']);
    Route::add(['GET', 'OPTIONS'], '/dashboard/data', [app\admin\controller\v1\DashboardController::class, 'getDashboardData']);
    
    // 权限测试路由
    Route::add(['GET', 'OPTIONS'], '/test/permissions', [app\admin\controller\v1\TestController::class, 'getPermissions']);
    Route::add(['GET', 'OPTIONS'], '/test/check-permissions', [app\admin\controller\v1\TestController::class, 'checkPermissions']);
    
    // 谷歌验证码路由
    Route::add(['GET', 'OPTIONS'], '/google-auth/qr-code', [app\admin\controller\v1\GoogleAuthController::class, 'generateQrCode']);
    Route::add(['POST', 'OPTIONS'], '/google-auth/bind', [app\admin\controller\v1\GoogleAuthController::class, 'bindGoogleAuth']);
    Route::add(['GET', 'OPTIONS'], '/google-auth/check', [app\admin\controller\v1\GoogleAuthController::class, 'checkBinding']);

    // 密码修改路由
    Route::add(['POST', 'OPTIONS'], '/change-password', [app\admin\controller\v1\ChangePasswordController::class, 'changePassword']);
    Route::add(['POST', 'OPTIONS'], '/update-password', [app\admin\controller\v1\ChangePasswordController::class, 'updatePassword']);




})->middleware([app\middleware\Auth::class]);


// Telegram Webhook路由
Route::group('/telegram', function () {
    // 处理Telegram Webhook
    Route::add(['POST', 'OPTIONS'], '/webhook', [app\admin\controller\v1\robot\TelegramWebhookController::class, 'handleWebhook']);
});
Route::group('/notify', function () {
    // 处理Telegram Webhook
    Route::add(['POST', 'OPTIONS'], '', [app\admin\controller\v1\robot\TestNotifyController::class, 'index']);
});
// 供货商回调路由
Route::group('/callback', function () {
    // 测试路由
    Route::add(['POST', 'OPTIONS'], '/test/{payment_name}', [app\api\controller\v1\callback\TestController::class, 'test']);
    // 统一支付服务商回调路由
    Route::add(['GET', 'POST', 'OPTIONS'], '/{payment_name}', [app\api\controller\v1\callback\SupplierCallbackController::class, 'handleCallback']);
})->middleware([app\middleware\CallbackWhitelistMiddleware::class]);
Route::group('/api/v1', function () {
    // 订单管理路由
    Route::group('/order', function () {
        // 创建订单
        Route::add(['POST', 'OPTIONS'], '/create', [app\api\controller\v1\order\CreateController::class, 'create']);
        // 查询订单
        Route::add(['POST', 'OPTIONS'], '/query', [app\api\controller\v1\order\QueryController::class, 'query']);
    });

    // 商户管理路由
    Route::group('/merchant', function () {
        // 查询余额
        Route::add(['POST', 'OPTIONS'], '/balance', [app\api\controller\v1\merchant\BalanceController::class, 'query']);
    });

    // 简单测试路由（无中间件）
    Route::add(['POST', 'OPTIONS'], '/callback/simple-test', [app\api\controller\v1\callback\TestController::class, 'test']);
    



})->middleware([
    app\middleware\IpWhitelistMiddleware::class, app\middleware\AntiReplayMiddleware::class
]);