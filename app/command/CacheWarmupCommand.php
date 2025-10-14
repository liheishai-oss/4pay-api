<?php

namespace app\command;

use app\common\helpers\CacheWarmupService;
use support\Log;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * 缓存预热命令
 * 用于在系统启动时预热关键数据到缓存中
 */
class CacheWarmupCommand extends Command
{
    protected static $defaultName = 'cache:warmup';
    protected static $defaultDescription = '预热系统缓存';

    protected function configure()
    {
        $this->addOption('merchant', 'm', InputOption::VALUE_OPTIONAL, '预热指定商户的缓存', null);
        $this->addOption('product', 'p', InputOption::VALUE_OPTIONAL, '预热指定产品的缓存', null);
        $this->addOption('all', 'a', InputOption::VALUE_NONE, '预热所有缓存');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>开始缓存预热...</info>');

        try {
            if ($input->getOption('all')) {
                // 预热所有缓存
                $results = CacheWarmupService::warmupAll();
                
                $output->writeln('<info>缓存预热完成:</info>');
                $output->writeln("  商户缓存: {$results['merchant_cache']} 个");
                $output->writeln("  产品缓存: {$results['product_cache']} 个");
                $output->writeln("  商户产品关系缓存: {$results['product_merchant_cache']} 个");
                $output->writeln("  通道缓存: {$results['channel_cache']} 个");
                $output->writeln("  查询缓存: {$results['query_cache']} 个");
                $output->writeln("  总耗时: {$results['total_time']} ms");
                
            } elseif ($merchantKey = $input->getOption('merchant')) {
                // 预热指定商户缓存
                $success = CacheWarmupService::warmupMerchantCache($merchantKey);
                if ($success) {
                    $output->writeln("<info>商户 {$merchantKey} 缓存预热完成</info>");
                } else {
                    $output->writeln("<error>商户 {$merchantKey} 缓存预热失败</error>");
                    return Command::FAILURE;
                }
                
            } elseif ($productId = $input->getOption('product')) {
                // 预热指定产品缓存
                $success = CacheWarmupService::warmupProductCache((int)$productId);
                if ($success) {
                    $output->writeln("<info>产品 {$productId} 缓存预热完成</info>");
                } else {
                    $output->writeln("<error>产品 {$productId} 缓存预热失败</error>");
                    return Command::FAILURE;
                }
                
            } else {
                $output->writeln('<error>请指定预热选项: --all, --merchant=KEY, 或 --product=ID</error>');
                return Command::FAILURE;
            }

            $output->writeln('<info>缓存预热成功完成!</info>');
            return Command::SUCCESS;

        } catch (\Throwable $e) {
            $output->writeln("<error>缓存预热失败: {$e->getMessage()}</error>");
            Log::error('缓存预热命令执行失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }
}

