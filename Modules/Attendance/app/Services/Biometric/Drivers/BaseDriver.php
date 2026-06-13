<?php

namespace Modules\Attendance\app\Services\Biometric\Drivers;

use Modules\Attendance\app\Interfaces\BiometricServiceInterface;

abstract class BaseDriver implements BiometricServiceInterface
{
    protected array $config = [];

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * Get the driver name.
     */
    abstract public function name(): string;

    /**
     * Get the driver display label.
     */
    abstract public function label(): string;
}
