<?php
declare(strict_types=1);

/**
 * iboltnetworks E-Commerce - Enterprise Mailer Service
 * 
 * High-performance direct socket SMTP mailer for Hostinger, Gmail, and custom SMTP.
 * Pure PHP, zero external dependencies (no Composer required).
 */

require_once __DIR__ . '/../config/config.php';

class MailerService
{
    /**
     * Send password recovery email with responsive, branded HTML template
     */
    public static function sendPasswordResetEmail(
        string $toEmail,
        string $toName,
        string $resetUrl,
        string $userType = 'customer'
    ): array {
        $subject = ($userType === 'admin')
            ? 'iboltnetworks Staff: Password Recovery Request'
            : 'Reset Your iboltnetworks Account Password';

        $greetingName = !empty($toName) ? htmlspecialchars($toName, ENT_QUOTES, 'UTF-8') : 'Valued Customer';
        $siteUrl = config('app_url', 'http://localhost:8888');
        $storePhone = config('store_phone', '+91 99110 03865');
        $storeEmail = config('store_email', 'sales@iboltnetworks.com');
        $storeAddress = config('store_address', 'Vishal Bhawan, Nehru Place, New Delhi, Delhi 110019');

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{$subject}</title>
  <style>
    body { margin: 0; padding: 0; background-color: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #1e293b; }
    .email-wrapper { width: 100%; background-color: #f1f5f9; padding: 36px 12px; }
    .email-container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.06); border: 1px solid #e2e8f0; }
    .email-header { background: #0a192f; padding: 32px 36px; text-align: center; }
    .brand-logo { font-size: 26px; font-weight: 800; color: #ffffff; text-decoration: none; letter-spacing: -0.5px; }
    .brand-logo span { color: #078e49; }
    .brand-tagline { color: #94a3b8; font-size: 12px; margin-top: 6px; text-transform: uppercase; letter-spacing: 1px; }
    .email-body { padding: 36px; }
    .email-title { font-size: 20px; font-weight: 800; color: #0f172a; margin: 0 0 16px; }
    .email-text { font-size: 15px; line-height: 1.6; color: #334155; margin: 0 0 20px; }
    .btn-container { text-align: center; margin: 32px 0; }
    .btn-reset { display: inline-block; background-color: #078e49; color: #ffffff !important; text-decoration: none; font-size: 15px; font-weight: 700; padding: 14px 32px; border-radius: 8px; box-shadow: 0 4px 14px rgba(7, 142, 73, 0.35); }
    .link-box { background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 8px; padding: 14px; font-size: 12.5px; color: #64748b; word-break: break-all; margin: 24px 0; }
    .security-note { background: #fef3c7; border-left: 4px solid #f59e0b; padding: 14px 16px; border-radius: 4px; font-size: 13.5px; color: #92400e; line-height: 1.5; margin: 24px 0 0; }
    .email-footer { background: #f8fafc; padding: 24px 36px; border-top: 1px solid #e2e8f0; font-size: 12.5px; color: #64748b; text-align: center; line-height: 1.6; }
    .email-footer a { color: #078e49; text-decoration: none; font-weight: 600; }
  </style>
</head>
<body>
  <div class="email-wrapper">
    <div class="email-container">
      <div class="email-header">
        <a href="{$siteUrl}" class="brand-logo">ibolt<span>networks</span></a>
        <div class="brand-tagline">Enterprise Datacenter Solutions</div>
      </div>
      <div class="email-body">
        <h1 class="email-title">Password Reset Request</h1>
        <p class="email-text">Hello {$greetingName},</p>
        <p class="email-text">
          We received a request to reset the password for your iboltnetworks account. To proceed and choose a new password, click the button below:
        </p>
        <div class="btn-container">
          <a href="{$resetUrl}" class="btn-reset" target="_blank">Reset My Password</a>
        </div>
        <p class="email-text" style="font-size: 13.5px; color: #64748b; margin-bottom: 8px;">
          If the button does not work, copy and paste this link into your web browser:
        </p>
        <div class="link-box">
          <a href="{$resetUrl}" style="color: #078e49; text-decoration: underline;">{$resetUrl}</a>
        </div>
        <div class="security-note">
          <strong>Security Notice:</strong> This password reset link is valid for <strong>60 minutes</strong> and can only be used once. If you did not initiate this request, you can safely ignore this email—your account credentials remain completely secure.
        </div>
      </div>
      <div class="email-footer">
        <strong>iboltnetworks Private Limited</strong><br>
        {$storeAddress}<br>
        Support: <a href="tel:{$storePhone}">{$storePhone}</a> | <a href="mailto:{$storeEmail}">{$storeEmail}</a><br>
        &copy; 2026 iboltnetworks. All rights reserved.
      </div>
    </div>
  </div>
</body>
</html>
HTML;

        $plainText = "Hello {$toName},\n\n"
            . "We received a request to reset the password for your iboltnetworks account.\n\n"
            . "Please click or open the link below to set your new password:\n"
            . "{$resetUrl}\n\n"
            . "This link is valid for 60 minutes only. If you did not request this, please disregard this email.\n\n"
            . "--\niboltnetworks Support\n{$storePhone}\n{$storeEmail}";

        return self::sendMail($toEmail, $toName, $subject, $html, $plainText);
    }

    /**
     * Dispatch email via SMTP or native mail
     */
    public static function sendMail(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        string $textBody = ''
    ): array {
        // Log locally for development preview
        self::logEmail($toEmail, $subject, $htmlBody);

        $driver = strtolower((string)config('mail_driver', 'smtp'));
        $smtpHost = (string)config('smtp_host', '');
        $smtpUser = (string)config('smtp_user', '');

        if ($driver === 'smtp' && !empty($smtpHost) && !empty($smtpUser)) {
            $res = self::sendViaHostingerSmtp($toEmail, $toName, $subject, $htmlBody, $textBody);
            if ($res['success']) {
                return $res;
            }
            error_log("SMTP delivery error: " . $res['message']);
        }

        // In production on Hostinger/cPanel, native mail() works if configured
        if (function_exists('mail') && php_uname('s') !== 'Darwin') {
            return self::sendViaNativeMail($toEmail, $toName, $subject, $htmlBody, $textBody);
        }

        return [
            'success' => false,
            'message' => 'Email could not be delivered directly. Please check SMTP settings.'
        ];
    }

    /**
     * Robust Direct Socket SMTP Delivery (Hostinger, SSL 465 / TLS 587)
     */
    private static function sendViaHostingerSmtp(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        string $textBody
    ): array {
        $host = (string)config('smtp_host', 'smtp.hostinger.com');
        $port = (int)config('smtp_port', 465);
        $encryption = strtolower((string)config('smtp_encryption', 'ssl'));
        $username = (string)config('smtp_user', '');
        $password = (string)config('smtp_pass', '');
        $fromAddress = (string)config('mail_from_address', 'sales@iboltnetworks.com');
        $fromName = (string)config('mail_from_name', 'iboltnetworks Support');

        $protocol = ($encryption === 'ssl' || $port === 465) ? 'ssl://' : '';
        $remoteSocket = $protocol . $host . ':' . $port;

        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ]);

        $timeout = 8;
        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client($remoteSocket, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);

        if (!$socket) {
            return ['success' => false, 'message' => "Could not connect to {$remoteSocket}: {$errstr} ({$errno})"];
        }

        stream_set_timeout($socket, $timeout);

        $readResponse = function() use ($socket): string {
            $resp = '';
            while ($line = fgets($socket, 512)) {
                $resp .= $line;
                if (isset($line[3]) && $line[3] === ' ') {
                    break;
                }
            }
            return $resp;
        };

        $sendCmd = function(string $cmd, array $expectedCodes = []) use ($socket, $readResponse): array {
            fwrite($socket, $cmd . "\r\n");
            $resp = $readResponse();
            $code = (int)substr($resp, 0, 3);
            if (!empty($expectedCodes) && !in_array($code, $expectedCodes, true)) {
                return ['success' => false, 'code' => $code, 'message' => trim($resp)];
            }
            return ['success' => true, 'code' => $code, 'message' => trim($resp)];
        };

        // 1. Initial Greeting
        $greet = $readResponse();
        if ((int)substr($greet, 0, 3) !== 220) {
            fclose($socket);
            return ['success' => false, 'message' => "Bad greeting: " . trim($greet)];
        }

        // 2. EHLO (use clean domain name, NOT mac hostname)
        $domain = parse_url((string)config('app_url', 'iboltnetworks.com'), PHP_URL_HOST) ?: 'iboltnetworks.com';
        $ehlo = $sendCmd("EHLO " . $domain, [250]);
        if (!$ehlo['success']) {
            fclose($socket);
            return ['success' => false, 'message' => "EHLO failed: " . $ehlo['message']];
        }

        // 3. STARTTLS if port 587
        if ($port === 587 || $encryption === 'tls') {
            $tls = $sendCmd("STARTTLS", [220]);
            if ($tls['success']) {
                if (!@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    fclose($socket);
                    return ['success' => false, 'message' => "TLS crypto upgrade failed."];
                }
                $ehlo = $sendCmd("EHLO " . $domain, [250]);
                if (!$ehlo['success']) {
                    fclose($socket);
                    return ['success' => false, 'message' => "EHLO after TLS failed: " . $ehlo['message']];
                }
            }
        }

        // 4. AUTH LOGIN
        if (!empty($username)) {
            $auth = $sendCmd("AUTH LOGIN", [334]);
            if (!$auth['success']) {
                fclose($socket);
                return ['success' => false, 'message' => "AUTH LOGIN rejected: " . $auth['message']];
            }

            $userRes = $sendCmd(base64_encode($username), [334]);
            if (!$userRes['success']) {
                fclose($socket);
                return ['success' => false, 'message' => "Username rejected: " . $userRes['message']];
            }

            $passRes = $sendCmd(base64_encode($password), [235]);
            if (!$passRes['success']) {
                fclose($socket);
                return ['success' => false, 'message' => "Password rejected: " . $passRes['message']];
            }
        }

        // 5. MAIL FROM
        $mailFrom = $sendCmd("MAIL FROM: <{$fromAddress}>", [250]);
        if (!$mailFrom['success']) {
            fclose($socket);
            return ['success' => false, 'message' => "MAIL FROM rejected: " . $mailFrom['message']];
        }

        // 6. RCPT TO
        $rcptTo = $sendCmd("RCPT TO: <{$toEmail}>", [250, 251]);
        if (!$rcptTo['success']) {
            fclose($socket);
            return ['success' => false, 'message' => "RCPT TO rejected: " . $rcptTo['message']];
        }

        // 7. DATA
        $dataInit = $sendCmd("DATA", [354]);
        if (!$dataInit['success']) {
            fclose($socket);
            return ['success' => false, 'message' => "DATA init rejected: " . $dataInit['message']];
        }

        // 8. Build RFC compliant email payload
        $boundary = '=_ibolt_' . md5((string)microtime(true));
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $toHeader = !empty($toName) ? "=?UTF-8?B?" . base64_encode($toName) . "?= <{$toEmail}>" : "<{$toEmail}>";
        $fromHeader = "=?UTF-8?B?" . base64_encode($fromName) . "?= <{$fromAddress}>";

        $headers = [];
        $headers[] = "From: {$fromHeader}";
        $headers[] = "To: {$toHeader}";
        $headers[] = "Subject: {$encodedSubject}";
        $headers[] = "Date: " . date('r');
        $headers[] = "Message-ID: <" . md5(uniqid((string)mt_rand(), true)) . "@{$domain}>";
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "Content-Type: multipart/alternative; boundary=\"{$boundary}\"";
        $headers[] = "X-Mailer: iboltnetworks SMTP Engine";

        $body = [];
        if (!empty($textBody)) {
            $body[] = "--{$boundary}";
            $body[] = "Content-Type: text/plain; charset=UTF-8";
            $body[] = "Content-Transfer-Encoding: base64";
            $body[] = "";
            $body[] = chunk_split(base64_encode($textBody));
        }

        $body[] = "--{$boundary}";
        $body[] = "Content-Type: text/html; charset=UTF-8";
        $body[] = "Content-Transfer-Encoding: base64";
        $body[] = "";
        $body[] = chunk_split(base64_encode($htmlBody));
        $body[] = "--{$boundary}--";

        $payload = implode("\r\n", $headers) . "\r\n\r\n" . implode("\r\n", $body) . "\r\n.\r\n";
        fwrite($socket, $payload);

        $dataResp = $readResponse();
        $code = (int)substr($dataResp, 0, 3);
        $sendCmd("QUIT", [221]);
        fclose($socket);

        if ($code === 250) {
            return ['success' => true, 'message' => 'Email delivered successfully via Hostinger SMTP.'];
        }

        return ['success' => false, 'message' => "SMTP Delivery Error [{$code}]: " . trim($dataResp)];
    }

    /**
     * Native PHP mail() fallback
     */
    private static function sendViaNativeMail(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        string $textBody
    ): array {
        $fromAddress = config('mail_from_address', config('store_email', 'sales@iboltnetworks.com'));
        $fromName = config('mail_from_name', 'iboltnetworks Support');

        $boundary = '=_ibolt_' . md5((string)microtime(true));
        $toHeader = !empty($toName) ? "=?UTF-8?B?" . base64_encode($toName) . "?= <{$toEmail}>" : "<{$toEmail}>";
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

        $headers = [];
        $headers[] = "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$fromAddress}>";
        $headers[] = "Reply-To: <{$fromAddress}>";
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "Content-Type: multipart/alternative; boundary=\"{$boundary}\"";
        $headers[] = "X-Mailer: PHP/" . phpversion();

        $body = [];
        if (!empty($textBody)) {
            $body[] = "--{$boundary}";
            $body[] = "Content-Type: text/plain; charset=UTF-8";
            $body[] = "Content-Transfer-Encoding: base64";
            $body[] = "";
            $body[] = chunk_split(base64_encode($textBody));
        }

        $body[] = "--{$boundary}";
        $body[] = "Content-Type: text/html; charset=UTF-8";
        $body[] = "Content-Transfer-Encoding: base64";
        $body[] = "";
        $body[] = chunk_split(base64_encode($htmlBody));
        $body[] = "--{$boundary}--";

        $fullBody = implode("\r\n", $body);
        $headersStr = implode("\r\n", $headers);

        $sent = @mail($toEmail, $encodedSubject, $fullBody, $headersStr);

        return $sent
            ? ['success' => true, 'message' => 'Email sent via PHP mail()']
            : ['success' => false, 'message' => 'PHP mail() dispatch failed.'];
    }

    /**
     * Log email for developer inspection
     */
    private static function logEmail(string $toEmail, string $subject, string $htmlBody): void
    {
        try {
            $storageDir = dirname(__DIR__) . '/storage';
            if (!is_dir($storageDir)) {
                @mkdir($storageDir, 0775, true);
            }
            $logFile = $storageDir . '/mail.log';
            $logEntry = "[" . date('Y-m-d H:i:s') . "] TO: {$toEmail} | SUBJECT: {$subject}\n";
            @file_put_contents($logFile, $logEntry, FILE_APPEND);

            $previewFile = $storageDir . '/latest_email_preview.html';
            @file_put_contents($previewFile, $htmlBody);
        } catch (Exception $e) {}
    }
}
