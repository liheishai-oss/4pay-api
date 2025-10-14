<?php
require_once __DIR__ . '/vendor/autoload.php';

use app\model\AdminRule;
use support\Db;

try {
    echo "开始查找菜单项...\n";
    
    // 查找包含"监控"的菜单项
    $monitorMenus = AdminRule::where('title', 'like', '%监控%')->get();
    
    if ($monitorMenus->count() > 0) {
        echo "找到以下包含'监控'的菜单项：\n";
        foreach ($monitorMenus as $menu) {
            echo "ID: {$menu->id}, 标题: {$menu->title}, 规则: {$menu->rule}, 父级ID: {$menu->parent_id}\n";
        }
        
        // 更新菜单标题
        $updated = AdminRule::where('title', 'like', '%监控%')
            ->update(['title' => Db::raw("REPLACE(title, '监控', '运维')")]);
            
        echo "\n已更新 {$updated} 个菜单项\n";
        
        // 查看更新后的结果
        echo "\n更新后的菜单项：\n";
        $updatedMenus = AdminRule::where('title', 'like', '%运维%')->get();
        foreach ($updatedMenus as $menu) {
            echo "ID: {$menu->id}, 标题: {$menu->title}, 规则: {$menu->rule}, 父级ID: {$menu->parent_id}\n";
        }
    } else {
        echo "未找到包含'监控'的菜单项\n";
        
        // 查找所有菜单项，看看是否有其他相关的
        echo "\n所有菜单项：\n";
        $allMenus = AdminRule::where('is_menu', 1)->get();
        foreach ($allMenus as $menu) {
            echo "ID: {$menu->id}, 标题: {$menu->title}, 规则: {$menu->rule}, 父级ID: {$menu->parent_id}\n";
        }
    }
    
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    echo "堆栈跟踪: " . $e->getTraceAsString() . "\n";
}
