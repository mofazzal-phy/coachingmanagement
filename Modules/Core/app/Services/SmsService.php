<?php

namespace Modules\Core\app\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    protected string $apiKey;
    protected string $senderId;
    protected string $baseUrl;
    protected bool $enabled;
    protected string $defaultCountryCode;

    public function __construct()
    {
        $this->enabled = config('services.sms.enabled', false);
        $this->apiKey = config('services.sms.api_key', '');
        $this->senderId = config('services.sms.sender_id', '');
        $this->baseUrl = config('services.sms.base_url', '');
        $this->defaultCountryCode = config('services.sms.default_country_code', '88');
    }

    /**
     * Send SMS to a single recipient
     * 
     * @param string $phone
     * @param string $message
     * @return bool
     */
    public function send(string $phone, string $message): bool
    {
        if (!$this->enabled) {
            Log::info('SMS sending is disabled', ['phone' => $phone, 'message' => $message]);
            return true; // Return true in development
        }

        try {
            $phone = $this->formatPhoneNumber($phone);

            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post($this->baseUrl . '/api/send-sms', [
                    'recipient' => $phone,
                    'sender_id' => $this->senderId,
                    'message' => $message,
                    'type' => 'plain',
                ]);

            if ($response->successful()) {
                Log::info('SMS sent successfully', [
                    'phone' => substr($phone, 0, -4) . '****',
                    'message_id' => $response->json('data.message_id') ?? null,
                ]);
                return true;
            }

            Log::error('SMS sending failed', [
                'phone' => substr($phone, 0, -4) . '****',
                'error' => $response->body(),
            ]);
            return false;

        } catch (\Exception $e) {
            Log::error('SMS sending exception', [
                'phone' => substr($phone, 0, -4) . '****',
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send bulk SMS to multiple recipients
     * 
     * @param array $phones
     * @param string $message
     * @return array Results for each recipient
     */
    public function sendBulk(array $phones, string $message): array
    {
        $results = [
            'success' => [],
            'failed' => [],
        ];

        foreach (array_unique($phones) as $phone) {
            if ($this->send($phone, $message)) {
                $results['success'][] = $phone;
            } else {
                $results['failed'][] = $phone;
            }
        }

        return $results;
    }

    /**
     * Send SMS with templated message
     * 
     * @param string $phone
     * @param string $templateKey
     * @param array $data Variables to replace in template
     * @return bool
     */
    public function sendTemplated(string $phone, string $templateKey, array $data = []): bool
    {
        $template = config("sms-templates.{$templateKey}");
        
        if (!$template) {
            Log::error('SMS template not found', ['template_key' => $templateKey]);
            return false;
        }

        $message = $template;

        // Replace placeholders
        foreach ($data as $key => $value) {
            $message = str_replace("{{$key}}", $value, $message);
        }

        return $this->send($phone, $message);
    }

    /**
     * Send attendance notification
     * 
     * @param string $phone
     * @param string $studentName
     * @param string $date
     * @param string $status present/absent
     * @return bool
     */
    public function sendAttendanceNotification(string $phone, string $studentName, string $date, string $status): bool
    {
        $statusText = $status === 'present' ? 'Present' : 'Absent';
        $message = "Dear Guardian, {$studentName} was {$statusText} on {$date}.";
        
        return $this->send($phone, $message);
    }

    /**
     * Send fee reminder
     * 
     * @param string $phone
     * @param string $studentName
     * @param float $amount
     * @param string $dueDate
     * @return bool
     */
    public function sendFeeReminder(string $phone, string $studentName, float $amount, string $dueDate): bool
    {
        $message = "Dear Guardian, payment of BDT {$amount} is due for {$studentName}. Please pay by {$dueDate}.";
        
        return $this->send($phone, $message);
    }

    /**
     * Send exam result notification
     * 
     * @param string $phone
     * @param string $studentName
     * @param string $examName
     * @param float $gpa
     * @return bool
     */
    public function sendResultNotification(string $phone, string $studentName, string $examName, float $gpa): bool
    {
        $message = "Dear Guardian, {$studentName} scored GPA {$gpa} in {$examName}.";
        
        return $this->send($phone, $message);
    }

    /**
     * Send employee salary notification
     * 
     * @param string $phone
     * @param string $month
     * @param float $amount
     * @return bool
     */
    public function sendSalaryNotification(string $phone, string $month, float $amount): bool
    {
        $message = "Your salary of BDT {$amount} for {$month} has been disbursed. Please check your payslip.";
        
        return $this->send($phone, $message);
    }

    /**
     * Send leave status notification
     * 
     * @param string $phone
     * @param string $employeeName
     * @param string $status approved/rejected
     * @param string $leaveType
     * @return bool
     */
    public function sendLeaveStatusNotification(string $phone, string $employeeName, string $status, string $leaveType): bool
    {
        $statusText = ucfirst($status);
        $message = "{$employeeName}, your {$leaveType} leave has been {$statusText}.";
        
        return $this->send($phone, $message);
    }

    /**
     * Send OTP
     * 
     * @param string $phone
     * @param string $otp
     * @param int $expiresInMinutes
     * @return bool
     */
    public function sendOtp(string $phone, string $otp, int $expiresInMinutes = 5): bool
    {
        $message = "Your OTP is {$otp}. It will expire in {$expiresInMinutes} minutes. Do not share this with anyone.";
        
        return $this->send($phone, $message);
    }

    /**
     * Format phone number to international format
     * 
     * @param string $phone
     * @return string
     */
    protected function formatPhoneNumber(string $phone): string
    {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Add country code if not present
        if (strlen($phone) <= 11) {
            // Local number, add country code
            if (substr($phone, 0, 1) === '0') {
                $phone = substr($phone, 1);
            }
            $phone = $this->defaultCountryCode . $phone;
        }

        return $phone;
    }

    /**
     * Validate phone number
     * 
     * @param string $phone
     * @return bool
     */
    public function validatePhone(string $phone): bool
    {
        $phone = $this->formatPhoneNumber($phone);
        return strlen($phone) >= 10 && strlen($phone) <= 15;
    }

    /**
     * Get SMS balance
     * 
     * @return int|null Remaining SMS count or null if failed
     */
    public function getBalance(): ?int
    {
        if (!$this->enabled) {
            return 99999; // Return fake balance in development
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
            ])->get($this->baseUrl . '/api/balance');

            if ($response->successful()) {
                return (int) $response->json('data.balance');
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Failed to get SMS balance', ['error' => $e->getMessage()]);
            return null;
        }
    }
}