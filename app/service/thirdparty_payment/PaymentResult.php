<?php

namespace app\service\thirdparty_payment;

/**
 * 支付结果类
 * 统一封装支付操作的结果数据
 */
class PaymentResult
{
    public const STATUS_SUCCESS = true;
    public const STATUS_FAILED = 'failed';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_PENDING = 'pending';

    private string $status;
    private string $message;
    private array $data;
    private string $orderNo;
    private string $transactionId;
    private float $amount;
    private string $currency;
    private array $rawResponse;
    private int $timestamp;
    private array $header = [];
    private array $usedChannel = [];
    private int $httpStatus = 0;

    public function __construct(
        string $status,
        string $message = '',
        array $data = [],
        string $orderNo = '',
        string $transactionId = '',
        float $amount = 0.0,
        string $currency = 'CNY',
        array $rawResponse = []
    ) {
        $this->status = $status;
        $this->message = $message;
        $this->data = $data;
        $this->orderNo = $orderNo;
        $this->transactionId = $transactionId;
        $this->amount = $amount;
        $this->currency = $currency;
        $this->rawResponse = $rawResponse;
        $this->timestamp = time();
    }

    /**
     * 获取状态
     * @return string
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * 获取消息
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * 获取数据
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * 获取订单号
     * @return string
     */
    public function getOrderNo(): string
    {
        return $this->orderNo;
    }

    /**
     * 获取交易ID
     * @return string
     */
    public function getTransactionId(): string
    {
        return $this->transactionId;
    }

    /**
     * 获取金额
     * @return float
     */
    public function getAmount(): float
    {
        return $this->amount;
    }

    /**
     * 获取货币
     * @return string
     */
    public function getCurrency(): string
    {
        return $this->currency;
    }

    /**
     * 获取原始响应
     * @return array
     */
    public function getRawResponse(): array
    {
        return $this->rawResponse;
    }

    /**
     * 获取时间戳
     * @return int
     */
    public function getTimestamp(): int
    {
        return $this->timestamp;
    }

    /**
     * 是否成功
     * @return bool
     */
    public function isSuccess(): bool
    {
        // 优先检查统一状态封装的 status 字段
        if (isset($this->rawResponse['status'])) {
            return $this->rawResponse['status'] === true;
        }
        
        // 兼容原有的状态判断方式
        return false;
    }

    /**
     * 是否失败
     * @return bool
     */
    public function isFailed(): bool
    {
        // 优先检查统一状态封装的 status 字段
        if (isset($this->rawResponse['status'])) {
            return $this->rawResponse['status'] === false;
        }
        
        // 兼容原有的状态判断方式
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * 是否处理中
     * @return bool
     */
    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    /**
     * 设置请求头信息
     * @param array $header
     * @return void
     */
    public function setDebugInfo(array $header): void
    {
        $this->header = $header;
    }

    /**
     * 获取请求头信息
     * @return array
     */
    public function getHeader(): array
    {
        return $this->header;
    }

    /**
     * 设置使用的通道信息
     * @param array $channel
     * @return void
     */
    public function setUsedChannel(array $channel): void
    {
        $this->usedChannel = $channel;
    }

    /**
     * 获取使用的通道信息
     * @return array
     */
    public function getUsedChannel(): array
    {
        return $this->usedChannel;
    }

    /**
     * 转换为数组
     * @return array
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'message' => $this->message,
            'data' => $this->data,
            'order_no' => $this->orderNo,
            'transaction_id' => $this->transactionId,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'raw_response' => $this->rawResponse,
            'timestamp' => $this->timestamp,
            'header' => $this->header,
            'used_channel' => $this->usedChannel,
            // 统一的基础字段
            'payment_url' => $this->getPaymentUrl(),
            'payment_data' => $this->getPaymentData()
        ];
    }

    /**
     * 获取支付URL
     * @return string
     */
    public function getPaymentUrl(): string
    {
        // 优先从data中获取payment_url
        if (isset($this->data['payment_url']) && !empty($this->data['payment_url'])) {
            return $this->data['payment_url'];
        }
        
        // 从raw_response中获取pay_url
        if (isset($this->rawResponse['data']['pay_url']) && !empty($this->rawResponse['data']['pay_url'])) {
            return $this->rawResponse['data']['pay_url'];
        }
        
        // 从raw_response中获取payment_url
        if (isset($this->rawResponse['data']['payment_url']) && !empty($this->rawResponse['data']['payment_url'])) {
            return $this->rawResponse['data']['payment_url'];
        }
        
        return '';
    }

    /**
     * 获取支付数据
     * @return array
     */
    public function getPaymentData(): array
    {
        $paymentData = [];
        
        // 从data中获取支付相关字段
        $paymentFields = ['qr_code', 'order_id', 'expire_time', 'payment_url', 'form_data', 'redirect_url'];
        foreach ($paymentFields as $field) {
            if (isset($this->data[$field]) && !empty($this->data[$field])) {
                $paymentData[$field] = $this->data[$field];
            }
        }
        
        // 从raw_response中获取支付相关字段
        if (isset($this->rawResponse['data']) && is_array($this->rawResponse['data'])) {
            foreach ($paymentFields as $field) {
                if (isset($this->rawResponse['data'][$field]) && !empty($this->rawResponse['data'][$field])) {
                    $paymentData[$field] = $this->rawResponse['data'][$field];
                }
            }
        }
        
        return $paymentData;
    }

    /**
     * 创建成功结果
     * @param string $message
     * @param array $data
     * @param string $orderNo
     * @param string $transactionId
     * @param float $amount
     * @param string $currency
     * @param array $rawResponse
     * @return static
     */
    public static function success(
        string $message = '操作成功',
        array $data = [],
        string $orderNo = '',
        string $transactionId = '',
        float $amount = 0.0,
        string $currency = 'CNY',
        array $rawResponse = []
    ): self {
        return new self(
            self::STATUS_SUCCESS,
            $message,
            $data,
            $orderNo,
            $transactionId,
            $amount,
            $currency,
            $rawResponse
        );
    }

    /**
     * 创建失败结果
     * @param string $message
     * @param array $data
     * @param string $orderNo
     * @param array $rawResponse
     * @return static
     */
    public static function failed(
        string $message = '操作失败',
        array $data = [],
        string $orderNo = '',
        array $rawResponse = []
    ): self {
        return new self(
            self::STATUS_FAILED,
            $message,
            $data,
            $orderNo,
            '',
            0.0,
            'CNY',
            $rawResponse
        );
    }

    /**
     * 创建处理中结果
     * @param string $message
     * @param array $data
     * @param string $orderNo
     * @param array $rawResponse
     * @return static
     */
    public static function processing(
        string $message = '处理中',
        array $data = [],
        string $orderNo = '',
        array $rawResponse = []
    ): self {
        return new self(
            self::STATUS_PROCESSING,
            $message,
            $data,
            $orderNo,
            '',
            0.0,
            'CNY',
            $rawResponse
        );
    }

    /**
     * 设置HTTP状态码
     * @param int $httpStatus
     * @return $this
     */
    public function setHttpStatus(int $httpStatus): self
    {
        $this->httpStatus = $httpStatus;
        return $this;
    }

    /**
     * 获取HTTP状态码
     * @return int
     */
    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }
}


