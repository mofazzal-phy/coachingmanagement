<?php

namespace Modules\Core\app\Services;

use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class QrCodeService
{
    protected string $disk = 'public';
    protected string $directory = 'qrcodes';

    /**
     * Generate QR Code and save to storage
     * 
     * @param string $data Data to encode in QR code
     * @param string|null $filename Custom filename (without extension)
     * @param int $size QR code size in pixels
     * @param string $format png, svg, eps
     * @return string|null Stored file path
     */
    public function generate(
        string $data, 
        ?string $filename = null, 
        int $size = 300, 
        string $format = 'png'
    ): ?string {
        try {
            $filename = $filename ?? Str::uuid();
            $fullFilename = "{$filename}.{$format}";
            
            $qrCode = QrCode::format($format)
                ->size($size)
                ->margin(1)
                ->errorCorrection('H')
                ->generate($data);

            $path = "{$this->directory}/{$fullFilename}";
            Storage::disk($this->disk)->put($path, $qrCode);

            return $path;
        } catch (\Exception $e) {
            report($e);
            return null;
        }
    }

    /**
     * Generate QR Code as base64 string
     * 
     * @param string $data
     * @param int $size
     * @return string|null Base64 encoded image
     */
    public function generateBase64(string $data, int $size = 300): ?string
    {
        try {
            $qrCode = QrCode::format('png')
                ->size($size)
                ->margin(1)
                ->errorCorrection('H')
                ->generate($data);

            return 'data:image/png;base64,' . base64_encode($qrCode);
        } catch (\Exception $e) {
            report($e);
            return null;
        }
    }

    /**
     * Generate QR Code as HTML inline SVG
     * 
     * @param string $data
     * @param int $size
     * @return string|null Inline SVG HTML
     */
    public function generateInlineSvg(string $data, int $size = 300): ?string
    {
        try {
            return QrCode::size($size)
                ->margin(1)
                ->generate($data);
        } catch (\Exception $e) {
            report($e);
            return null;
        }
    }

    /**
     * Generate QR Code with custom colors
     * 
     * @param string $data
     * @param int $size
     * @param string $background Background color (RGBA or hex)
     * @param string $foreground Foreground color (RGBA or hex)
     * @return string|null Base64 encoded image
     */
    public function generateColored(string $data, int $size = 300, string $background = '#FFFFFF', string $foreground = '#000000'): ?string
    {
        try {
            $qrCode = QrCode::format('png')
                ->size($size)
                ->backgroundColor(...$this->parseColor($background))
                ->color(...$this->parseColor($foreground))
                ->margin(1)
                ->generate($data);

            return 'data:image/png;base64,' . base64_encode($qrCode);
        } catch (\Exception $e) {
            report($e);
            return null;
        }
    }

    /**
     * Parse color string to RGB array
     * 
     * @param string $color
     * @return array
     */
    protected function parseColor(string $color): array
    {
        // Remove # if present
        $color = ltrim($color, '#');
        
        // Convert hex to RGB
        if (strlen($color) === 6) {
            return [
                hexdec(substr($color, 0, 2)),
                hexdec(substr($color, 2, 2)),
                hexdec(substr($color, 4, 2)),
            ];
        }
        
        // Default black
        return [0, 0, 0];
    }

    /**
     * Generate QR Code for attendance
     * 
     * @param string $sessionId Attendance session ID
     * @param int $expiresInMinutes
     * @return string|null Base64 encoded QR image
     */
    public function generateAttendanceQr(string $sessionId, int $expiresInMinutes = 30): ?string
    {
        $data = json_encode([
            'type' => 'attendance',
            'session_id' => $sessionId,
            'timestamp' => now()->timestamp,
            'expires_at' => now()->addMinutes($expiresInMinutes)->timestamp,
        ]);

        return $this->generateBase64($data);
    }

    /**
     * Delete a QR code file
     * 
     * @param string|null $path
     * @return bool
     */
    public function delete(?string $path): bool
    {
        if (empty($path) || !Storage::disk($this->disk)->exists($path)) {
            return false;
        }
        return Storage::disk($this->disk)->delete($path);
    }

    /**
     * Get QR code URL
     * 
     * @param string|null $path
     * @return string|null
     */
    public function url(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }
        return Storage::disk($this->disk)->url($path);
    }
}