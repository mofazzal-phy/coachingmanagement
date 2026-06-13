<?php

namespace Modules\Attendance\app\Services\Biometric;

use Modules\Attendance\app\Interfaces\BiometricServiceInterface;
use Modules\Attendance\app\Models\BiometricDevice;
use Modules\Attendance\app\Services\Biometric\Drivers\BaseDriver;
use Modules\Attendance\app\Services\Biometric\Drivers\FakeDriver;
use Modules\Attendance\app\Services\Biometric\Drivers\SimulatorDriver;
use Modules\Attendance\app\Services\Biometric\Drivers\ZKTecoDriver;
use Modules\Attendance\app\Services\Biometric\Drivers\ESSLDriver;

class BiometricServiceManager
{
    /**
     * Registered driver map.
     */
    protected array $drivers = [];

    /**
     * Cached driver instances.
     */
    protected array $instances = [];

    public function __construct()
    {
        $this->registerDefaultDrivers();
    }

    /**
     * Register the default set of drivers.
     */
    protected function registerDefaultDrivers(): void
    {
        $this->register('fake', FakeDriver::class);
        $this->register('simulator', SimulatorDriver::class);
        $this->register('zkteco', ZKTecoDriver::class);
        $this->register('essl', ESSLDriver::class);
    }

    /**
     * Register a driver.
     */
    public function register(string $name, string $driverClass): void
    {
        $this->drivers[$name] = $driverClass;
    }

    /**
     * Get all registered driver names.
     */
    public function getRegisteredDrivers(): array
    {
        return array_keys($this->drivers);
    }

    /**
     * Get all available driver options (for UI selection).
     */
    public function getDriverOptions(): array
    {
        $options = [];
        foreach ($this->drivers as $name => $class) {
            /** @var BaseDriver $driver */
            $driver = $this->resolve($name);
            $options[] = [
                'name' => $driver->name(),
                'label' => $driver->label(),
            ];
        }
        return $options;
    }

    /**
     * Resolve a driver instance.
     */
    public function resolve(string $driverName, array $config = []): BiometricServiceInterface
    {
        $cacheKey = $driverName . ':' . md5(json_encode($config));

        if (!isset($this->instances[$cacheKey])) {
            $driverClass = $this->drivers[$driverName] ?? FakeDriver::class;
            $this->instances[$cacheKey] = new $driverClass($config);
        }

        return $this->instances[$cacheKey];
    }

    /**
     * Get driver for a specific device model.
     */
    public function driverForDevice(BiometricDevice $device): BiometricServiceInterface
    {
        $config = $device->config ?? [];
        $config['device_id'] = $device->id;
        $config['device_name'] = $device->device_name;
        $config['ip_address'] = $device->ip_address;
        $config['port'] = $device->port;

        return $this->resolve($device->driver, $config);
    }

    /**
     * Test connection for a device.
     */
    public function testDevice(BiometricDevice $device): bool
    {
        $driver = $this->driverForDevice($device);
        return $driver->testConnection();
    }

    /**
     * Pull attendance from a device.
     */
    public function pullFromDevice(BiometricDevice $device): array
    {
        $driver = $this->driverForDevice($device);
        return $driver->pullAttendance();
    }
}
