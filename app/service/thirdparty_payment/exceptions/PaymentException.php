<?php

namespace app\service\thirdparty_payment\exceptions;

use Exception;

/**
 * 支付异常基类
 */
class PaymentException extends Exception
{
    protected string $errorCode;
    protected array $context;

    public function __construct(
        string $message = '',
        string $errorCode = 'PAYMENT_ERROR',
        int $code = 0,
        array $context = [],
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->errorCode = $errorCode;
        $this->context = $context;
    }

    /**
     * 获取错误代码
     * @return string
     */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * 获取上下文信息
     * @return array
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * 创建参数验证异常
     * @param string $message
     * @param array $context
     * @return static
     */
    public static function invalidParams(string $message, array $context = []): self
    {
        return new self($message, 'INVALID_PARAMS', 400, $context);
    }

    /**
     * 创建服务不存在异常
     * @param string $serviceType
     * @return static
     */
    public static function serviceNotFound(string $serviceType): self
    {
        return new self(
            "支付服务类型 '{$serviceType}' 不存在",
            'SERVICE_NOT_FOUND',
            404,
            ['service_type' => $serviceType]
        );
    }

    /**
     * 创建配置错误异常
     * @param string $message
     * @param array $context
     * @return static
     */
    public static function configError(string $message, array $context = []): self
    {
        return new self($message, 'CONFIG_ERROR', 500, $context);
    }

    /**
     * 创建网络异常
     * @param string $message
     * @param array $context
     * @return static
     */
    public static function networkError(string $message, array $context = []): self
    {
        return new self($message, 'NETWORK_ERROR', 500, $context);
    }

    /**
     * 创建业务异常
     * @param string $message
     * @param array $context
     * @return static
     */
    public static function businessError(string $message, array $context = []): self
    {
        return new self($message, 'BUSINESS_ERROR', 400, $context);
    }
}


