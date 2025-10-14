<?php

namespace app\service\thirdparty_payment\status;

/**
 * 状态检查器工厂
 */
class StatusCheckerFactory
{
    /**
     * 状态检查器映射
     * @var array
     */
    private static $checkers = [
        'baiyi' => BaiyiStatusChecker::class,
        'haitun' => HaitunStatusChecker::class,
        'paofen' => PaofenStatusChecker::class,
    ];
    
    /**
     * 创建状态检查器
     * @param string $interfaceCode
     * @return StatusCheckerInterface
     */
    public static function create(string $interfaceCode): StatusCheckerInterface
    {
        $interfaceCode = strtolower($interfaceCode);
        
        if (isset(self::$checkers[$interfaceCode])) {
            return new self::$checkers[$interfaceCode];
        }
        
        // 如果没有找到对应的检查器，返回默认检查器
        return new DefaultStatusChecker();
    }
    
    /**
     * 注册自定义状态检查器
     * @param string $interfaceCode
     * @param string $checkerClass
     */
    public static function register(string $interfaceCode, string $checkerClass): void
    {
        self::$checkers[strtolower($interfaceCode)] = $checkerClass;
    }
    
    /**
     * 获取所有支持的接口代码
     * @return array
     */
    public static function getSupportedInterfaceCodes(): array
    {
        return array_keys(self::$checkers);
    }
}



