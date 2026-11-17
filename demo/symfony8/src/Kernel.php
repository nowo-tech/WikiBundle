<?php

declare(strict_types=1);

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    /**
     * Demo only: with DEMO_KERNEL_RESET=0 a booted kernel kept alive by the FrankenPHP worker is not reset between
     * requests (no services resetter run), the way some hosts run it. The wiki must work in both modes.
     */
    public function boot(): void
    {
        if ($this->booted && '0' === ($_SERVER['DEMO_KERNEL_RESET'] ?? $_ENV['DEMO_KERNEL_RESET'] ?? getenv('DEMO_KERNEL_RESET'))) {
            return;
        }

        parent::boot();
    }
}
