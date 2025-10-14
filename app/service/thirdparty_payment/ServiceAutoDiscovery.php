<?php

namespace app\service\thirdparty_payment;

use app\service\thirdparty_payment\interfaces\PaymentServiceInterface;
use app\service\thirdparty_payment\exceptions\PaymentException;

/**
 * 支付服务自动发现
 * 自动扫描和注册支付服务类
 */
class ServiceAutoDiscovery
{
    private string $servicesPath;
    private string $servicesNamespace;

    public function __construct()
    {
        $this->servicesPath = __DIR__ . '/services';
        $this->servicesNamespace = 'app\service\thirdparty_payment\services';
    }

    /**
     * 自动发现并注册所有支付服务
     * @return array 返回服务类型到类名的映射
     */
    public function discoverServices(): array
    {
        $services = [];
        
        if (!is_dir($this->servicesPath)) {
            return $services;
        }

        $files = $this->scanServiceFiles();
        
        foreach ($files as $file) {
            $className = $this->getClassNameFromFile($file);
            if ($className) {
                $fullClassName = $this->servicesNamespace . '\\' . $className;
                
                // 优先使用getServiceTypeFromClassName方法
                $serviceType = $this->getServiceTypeFromClassName($fullClassName);
                
                // 如果没有getServiceType方法，使用类名转换
                if (!$serviceType) {
                    $serviceType = $this->getServiceTypeFromClass($className);
                }
                
                if ($serviceType) {
                    $services[$serviceType] = $fullClassName;
                }
            }
        }

        return $services;
    }

    /**
     * 扫描服务文件
     * @return array
     */
    private function scanServiceFiles(): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->servicesPath)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    /**
     * 从文件路径获取类名
     * @param string $filePath
     * @return string|null
     */
    private function getClassNameFromFile(string $filePath): ?string
    {
        $relativePath = str_replace($this->servicesPath . DIRECTORY_SEPARATOR, '', $filePath);
        $relativePath = str_replace(DIRECTORY_SEPARATOR, '\\', $relativePath);
        $relativePath = str_replace('.php', '', $relativePath);
        
        return $relativePath;
    }

    /**
     * 从类名获取服务类型
     * @param string $className
     * @return string|null
     */
    private function getServiceTypeFromClass(string $className): ?string
    {
        // 移除Service后缀
        $baseName = preg_replace('/Service$/', '', $className);
        
        // 如果移除Service后缀后为空，返回null
        if (empty($baseName)) {
            return null;
        }
        
        // 转换为服务类型格式（驼峰转下划线或保持原样）
        $serviceType = $this->convertToServiceType($baseName);
        
        return $serviceType;
    }

    /**
     * 转换为服务类型格式
     * @param string $name
     * @return string
     */
    private function convertToServiceType(string $name): string
    {
        // 如果已经是小写，直接返回
        if (strtolower($name) === $name) {
            return $name;
        }
        
        // 检查是否包含大写字母（驼峰命名）
        if (preg_match('/[A-Z]/', $name)) {
            // 驼峰转下划线
            $converted = preg_replace('/([a-z])([A-Z])/', '$1_$2', $name);
            $converted = strtolower($converted);
            return $converted;
        }
        
        // 保持原始格式（如Haitun）
        return $name;
    }

    /**
     * 验证服务类是否有效
     * @param string $className
     * @return bool
     */
    private function isValidServiceClass(string $className): bool
    {
        try {
            if (!class_exists($className)) {
                return false;
            }

            $reflection = new \ReflectionClass($className);
            
            // 检查是否实现了PaymentServiceInterface接口
            if (!$reflection->implementsInterface(PaymentServiceInterface::class)) {
                return false;
            }

            // 检查是否是抽象类
            if ($reflection->isAbstract()) {
                return false;
            }

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 获取服务类的服务类型
     * @param string $className
     * @return string|null
     */
    public function getServiceTypeFromClassName(string $className): ?string
    {
        try {
            if (!class_exists($className)) {
                return null;
            }

            $reflection = new \ReflectionClass($className);
            
            // 检查是否有getServiceType方法
            if ($reflection->hasMethod('getServiceType')) {
                $method = $reflection->getMethod('getServiceType');
                if ($method->isPublic()) {
                    // 如果是静态方法，直接调用
                    if ($method->isStatic()) {
                        return $method->invoke(null);
                    } else {
                        // 如果是实例方法，尝试创建实例后调用
                        try {
                            // 尝试使用空配置创建实例
                            $instance = $reflection->newInstance([]);
                            return $method->invoke($instance);
                        } catch (\Exception $e) {
                            // 如果创建实例失败，使用类名转换
                            $shortName = $reflection->getShortName();
                            return $this->getServiceTypeFromClass($shortName);
                        }
                    }
                }
            }

            // 如果没有getServiceType方法，使用类名转换
            $shortName = $reflection->getShortName();
            return $this->getServiceTypeFromClass($shortName);
            
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * 获取服务类的服务名称
     * @param string $className
     * @return string|null
     */
    public function getServiceNameFromClassName(string $className): ?string
    {
        try {
            if (!class_exists($className)) {
                return null;
            }

            $reflection = new \ReflectionClass($className);
            
            // 检查是否有getServiceName方法
            if ($reflection->hasMethod('getServiceName')) {
                $method = $reflection->getMethod('getServiceName');
                if ($method->isStatic() && $method->isPublic()) {
                    return $method->invoke(null);
                }
            }

            // 如果没有getServiceName方法，使用类名
            return $reflection->getShortName();
            
        } catch (\Exception $e) {
            return null;
        }
    }
}
