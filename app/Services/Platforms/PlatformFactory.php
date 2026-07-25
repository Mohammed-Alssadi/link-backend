<?php

namespace App\Services\Platforms;

use App\Contracts\PlatformProvider;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

class PlatformFactory
{
    public function __construct(protected Container $container) {}

    /**
     * تصنيع وإرجاع كلاس منصة التجارة المطلوب ديناميكياً (Factory Method Pattern)
     */
    public function make(string $platform): PlatformProvider
    {
        return match (strtolower($platform)) {
            'salla' => $this->container->make(SallaProvider::class),
            'zid' => $this->container->make(ZidProvider::class),
            default => throw new InvalidArgumentException("المنصة التجارية [{$platform}] غير مدعومة حالياً."),
        };
    }
}
