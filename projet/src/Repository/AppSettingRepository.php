<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AppSetting;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AppSetting>
 */
final class AppSettingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AppSetting::class);
    }

    public function get(string $key): ?AppSetting
    {
        return $this->findOneBy(['key' => $key]);
    }

    public function set(string $key, string $value, bool $flush = true): AppSetting
    {
        $setting = $this->get($key);
        if (!$setting) {
            $setting = new AppSetting($key, $value);
            $this->getEntityManager()->persist($setting);
        } else {
            $setting->setValue($value);
        }

        if ($flush) {
            $this->getEntityManager()->flush();
        }

        return $setting;
    }
}
