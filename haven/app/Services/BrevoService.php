<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BrevoService
{
    protected ?string $apiKey;

    protected string $fromEmail;

    protected string $fromName;

    protected string $smsSender;

    public function __construct()
    {
        $this->apiKey = config('services.brevo.api_key');
        $this->fromEmail = config('services.brevo.from_email', 'noreply@haven.local');
        $this->fromName = config('services.brevo.from_name', 'Haven AI');
        $this->smsSender = config('services.brevo.sms_sender', 'HavenAI');
    }

    /**
     * Send a 6-digit OTP code to a user via Transactional Email.
     *
     * @param  string  $purpose  e.g. "Registration" or "Two-Factor Authentication"
     * @return array{success: bool, messageId: ?string, error: ?string}
     */
    public function sendEmailOtp(string $recipientEmail, string $recipientName, string $code, string $purpose = 'Two-Factor Authentication'): array
    {
        if (empty($this->apiKey)) {
            $loggedCode = app()->environment('production') ? '[REDACTED]' : $code;
            Log::warning('[BrevoService] API key not configured. OTP email simulated for '.$recipientEmail.' with code: '.$loggedCode);

            return [
                'success' => true,
                'messageId' => 'simulated_local_'.uniqid(),
                'error' => null,
            ];
        }

        $htmlContent = $this->buildOtpEmailTemplate($recipientName, $code, $purpose);

        try {
            $response = Http::withHeaders([
                'api-key' => $this->apiKey,
                'accept' => 'application/json',
                'content-type' => 'application/json',
            ])->post('https://api.brevo.com/v3/smtp/email', [
                'sender' => [
                    'name' => $this->fromName,
                    'email' => $this->fromEmail,
                ],
                'to' => [
                    [
                        'email' => $recipientEmail,
                        'name' => $recipientName ?: 'Valued User',
                    ],
                ],
                'subject' => "{$code} is your {$purpose} code for Haven AI",
                'htmlContent' => $htmlContent,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                Log::info("[BrevoService] OTP email sent successfully to {$recipientEmail}. MessageId: ".($data['messageId'] ?? 'N/A'));

                return [
                    'success' => true,
                    'messageId' => $data['messageId'] ?? null,
                    'error' => null,
                ];
            }

            $errorMessage = $response->json('message') ?? $response->body();
            Log::error("[BrevoService] Failed to send email to {$recipientEmail}: {$errorMessage} (Status: {$response->status()})");

            return [
                'success' => false,
                'messageId' => null,
                'error' => $errorMessage,
            ];
        } catch (\Throwable $e) {
            Log::error('[BrevoService] Exception sending OTP email: '.$e->getMessage());

            return [
                'success' => false,
                'messageId' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send an OTP code via Transactional SMS.
     *
     * @param  string  $recipientPhone  E.164 formatted international phone number (e.g. +639...)
     * @return array{success: bool, messageId: ?string, error: ?string}
     */
    public function sendSmsOtp(string $recipientPhone, string $code, string $purpose = '2FA'): array
    {
        if (empty($this->apiKey)) {
            $loggedCode = app()->environment('production') ? '[REDACTED]' : $code;
            Log::warning('[BrevoService] API key not configured. OTP SMS simulated for '.$recipientPhone.' with code: '.$loggedCode);

            return [
                'success' => true,
                'messageId' => 'simulated_sms_'.uniqid(),
                'error' => null,
            ];
        }

        // Clean sender: max 11 alphanumeric characters
        $sender = preg_replace('/[^a-zA-Z0-9]/', '', substr($this->smsSender, 0, 11)) ?: 'HavenAI';

        try {
            $response = Http::withHeaders([
                'api-key' => $this->apiKey,
                'accept' => 'application/json',
                'content-type' => 'application/json',
            ])->post('https://api.brevo.com/v3/transactionalSMS/sms', [
                'sender' => $sender,
                'recipient' => $recipientPhone,
                'content' => "Your Haven AI {$purpose} code is: {$code}. Valid for 10 minutes. Do not share this code.",
                'type' => 'transactional',
            ]);

            if ($response->successful()) {
                $data = $response->json();
                Log::info("[BrevoService] OTP SMS sent successfully to {$recipientPhone}. Reference: ".($data['reference'] ?? 'N/A'));

                return [
                    'success' => true,
                    'messageId' => $data['reference'] ?? null,
                    'error' => null,
                ];
            }

            $errorMessage = $response->json('message') ?? $response->body();
            Log::error("[BrevoService] Failed to send SMS to {$recipientPhone}: {$errorMessage} (Status: {$response->status()})");

            return [
                'success' => false,
                'messageId' => null,
                'error' => $errorMessage,
            ];
        } catch (\Throwable $e) {
            Log::error('[BrevoService] Exception sending OTP SMS: '.$e->getMessage());

            return [
                'success' => false,
                'messageId' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Modern, responsive email template styled to match Haven AI's automotive tech aesthetic.
     */
    protected function buildOtpEmailTemplate(string $name, string $code, string $purpose): string
    {
        $safeName = htmlspecialchars($name ?: 'Valued User');
        $safePurpose = htmlspecialchars($purpose);
        $safeCode = htmlspecialchars($code);

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Haven AI Security Code</title>
</head>
<body style="margin:0;padding:0;background-color:#0d1117;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#e6edf3;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#0d1117;padding:40px 20px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" style="max-width:540px;background:#161b22;border:1px solid #30363d;border-radius:12px;overflow:hidden;box-shadow:0 10px 30px rgba(0,0,0,0.5);">
                    <!-- Header -->
                    <tr>
                        <td style="padding:32px 32px 20px 32px;border-bottom:1px solid #21262d;text-align:center;">
                            <div style="display:inline-block;padding:8px 16px;background:rgba(20,184,166,0.1);border:1px solid rgba(20,184,166,0.3);border-radius:20px;">
                                <span style="color:#14b8a6;font-size:13px;font-weight:600;letter-spacing:1px;text-transform:uppercase;">Haven AI Security</span>
                            </div>
                            <h1 style="color:#ffffff;font-size:22px;font-weight:700;margin:16px 0 4px 0;">{$safePurpose}</h1>
                            <p style="color:#8b949e;font-size:14px;margin:0;">Vehicle Diagnostics &amp; Telemetry Platform</p>
                        </td>
                    </tr>
                    <!-- Body -->
                    <tr>
                        <td style="padding:32px;">
                            <p style="color:#c9d1d9;font-size:15px;line-height:1.6;margin:0 0 20px 0;">
                                Hello <strong style="color:#ffffff;">{$safeName}</strong>,
                            </p>
                            <p style="color:#8b949e;font-size:14px;line-height:1.6;margin:0 0 24px 0;">
                                Use the verification code below to complete your {$safePurpose}. This code will expire in <strong style="color:#e6edf3;">10 minutes</strong>.
                            </p>
                            
                            <!-- OTP Box -->
                            <div style="background:#0d1117;border:1px dashed #30363d;border-radius:8px;padding:24px;text-align:center;margin:0 0 28px 0;">
                                <span style="font-family:'Courier New',Courier,monospace;font-size:36px;font-weight:700;letter-spacing:8px;color:#14b8a6;display:inline-block;">{$safeCode}</span>
                            </div>

                            <p style="color:#8b949e;font-size:13px;line-height:1.5;margin:0 0 10px 0;">
                                If you did not request this verification code, please ignore this email or review your account security settings.
                            </p>
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td style="padding:20px 32px;background:#0d1117;border-top:1px solid #21262d;text-align:center;">
                            <p style="color:#6e7681;font-size:12px;margin:0;">
                                &copy; 2026 Haven AI Platform. All rights reserved.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }
}
