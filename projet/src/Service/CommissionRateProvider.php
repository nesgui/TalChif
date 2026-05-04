<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\AppSettingRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class CommissionRateProvider
{
    public const KEY = 'commission_taux';

    public function __construct(
        private AppSettingRepository $appSettingRepository,
        #[Autowire('%app.commission_taux%')]
        private float $defaultCommissionRate,
    ) {
    }

    public function getRate(): float
    {
        $setting = $this->appSettingRepository->get(self::KEY);
        if (!$setting) {
            return $this->defaultCommissionRate;
        }

        $value = str_replace(',', '.', trim($setting->getValue()));
        if ($value === '') {
            return $this->defaultCommissionRate;
        }

        $rate = (float) $value;
        if ($rate <= 0 || $rate >= 1) {
            return $this->defaultCommissionRate;
        }

        return $rate;
    }

    public function setRate(float $rate): void
    {
        $rate = max(0.0001, min(0.9999, $rate));
        $this->appSettingRepository->set(self::KEY, number_format($rate, 4, '.', ''), true);
    }
}
