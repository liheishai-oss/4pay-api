<?php
// 简单的菜单名称更新脚本
// 使用原生SQL查询，避免ORM连接问题

try {
    // 数据库连接配置
    $host = 'mysql';
    $dbname = 'fourth_party_payment';
    $username = 'fourth_party_payment';
    $password = 'q7esCgVO{9},.JGA';
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "数据库连接成功\n";
    
    // 1. 查找包含"监控"的菜单项
    $stmt = $pdo->prepare("SELECT id, title, rule, parent_id, is_menu FROM permission_rule WHERE title LIKE ? ORDER BY id");
    $stmt->execute(['%监控%']);
    $monitorMenus = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($monitorMenus) > 0) {
        echo "找到以下包含'监控'的菜单项：\n";
        foreach ($monitorMenus as $menu) {
            echo "ID: {$menu['id']}, 标题: {$menu['title']}, 规则: {$menu['rule']}, 父级ID: {$menu['parent_id']}\n";
        }
        
        // 2. 更新菜单标题
        $updateStmt = $pdo->prepare("UPDATE permission_rule SET title = REPLACE(title, '监控', '运维') WHERE title LIKE ?");
        $result = $updateStmt->execute(['%监控%']);
        
        if ($result) {
            echo "\n菜单名称更新成功！\n";
            
            // 3. 查看更新后的结果
            echo "\n更新后的菜单项：\n";
            $stmt = $pdo->prepare("SELECT id, title, rule, parent_id, is_menu FROM permission_rule WHERE title LIKE ? ORDER BY id");
            $stmt->execute(['%运维%']);
            $updatedMenus = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($updatedMenus as $menu) {
                echo "ID: {$menu['id']}, 标题: {$menu['title']}, 规则: {$menu['rule']}, 父级ID: {$menu['parent_id']}\n";
            }
        } else {
            echo "更新失败\n";
        }
    } else {
        echo "未找到包含'监控'的菜单项\n";
        
        // 显示所有菜单项
        echo "\n所有菜单项：\n";
        $stmt = $pdo->prepare("SELECT id, title, rule, parent_id, is_menu FROM permission_rule WHERE is_menu = 1 ORDER BY parent_id, weight DESC");
        $stmt->execute();
        $allMenus = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($allMenus as $menu) {
            echo "ID: {$menu['id']}, 标题: {$menu['title']}, 规则: {$menu['rule']}, 父级ID: {$menu['parent_id']}\n";
        }
    }
    
} catch (PDOException $e) {
    echo "数据库错误: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
}
