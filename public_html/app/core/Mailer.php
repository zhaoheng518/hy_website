<?php

namespace App\Core;

class Mailer
{
    private string $host;
    private int $port;
    private string $user;
    private string $pass;
    private string $fromEmail;
    private string $fromName;
    private int $timeout = 30;

    public function __construct()
    {
        $site = JsonStore::globalData('site')->read();
        if (!is_array($site)) {
            $site = [];
        }

        $this->host = (string) ($site['smtp_host'] ?? Config::get('smtp_host', ''));
        $this->port = (int) ($site['smtp_port'] ?? Config::get('smtp_port', 587));
        $this->user = (string) ($site['smtp_user'] ?? Config::get('smtp_user', ''));
        $this->pass = (string) ($site['smtp_pass'] ?? Config::get('smtp_pass', ''));
        $this->fromEmail = (string) ($site['smtp_from'] ?? Config::get('smtp_from', ''));
        $this->fromName = (string) ($site['smtp_from_name'] ?? Config::get('smtp_from_name', ''));

        if ($this->fromEmail === '') {
            $this->fromEmail = $this->user;
        }
        if ($this->fromName === '') {
            $this->fromName = Config::get('site_name', 'Website');
        }
    }

    public function send(string $to, string $subject, string $body, string $replyTo = ''): bool
    {
        if (empty($this->host) || empty($this->user) || empty($this->pass)) {
            return $this->sendViaMail($to, $subject, $body, $replyTo);
        }

        return $this->sendViaSmtp($to, $subject, $body, $replyTo);
    }

    private function sendViaSmtp(string $to, string $subject, string $body, string $replyTo): bool
    {
        $socket = @fsockopen(
            $this->host,
            $this->port,
            $errno,
            $errstr,
            $this->timeout
        );

        if (!$socket) {
            error_log("SMTP connection failed: [{$errno}] {$errstr}");
            return $this->sendViaMail($to, $subject, $body, $replyTo);
        }

        stream_set_timeout($socket, $this->timeout);

        $this->readSmtp($socket);

        $this->sendSmtp($socket, "EHLO " . $this->host);

        $starttls = false;
        $ehloResponse = $this->readSmtp($socket);
        if (strpos($ehloResponse, 'STARTTLS') !== false) {
            $starttls = true;
        }

        if ($starttls) {
            $this->sendSmtp($socket, "STARTTLS");
            $this->readSmtp($socket);

            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($socket);
                error_log("SMTP STARTTLS handshake failed");
                return false;
            }

            $this->sendSmtp($socket, "EHLO " . $this->host);
            $this->readSmtp($socket);
        }

        $this->sendSmtp($socket, "AUTH LOGIN");
        $this->readSmtp($socket);

        $this->sendSmtp($socket, base64_encode($this->user));
        $this->readSmtp($socket);

        $this->sendSmtp($socket, base64_encode($this->pass));
        $authResponse = $this->readSmtp($socket);

        if (strpos($authResponse, '235') !== 0) {
            fclose($socket);
            error_log("SMTP authentication failed: {$authResponse}");
            return false;
        }

        $this->sendSmtp($socket, "MAIL FROM:<{$this->fromEmail}>");
        $this->readSmtp($socket);

        $this->sendSmtp($socket, "RCPT TO:<{$to}>");
        $this->readSmtp($socket);

        $this->sendSmtp($socket, "DATA");
        $this->readSmtp($socket);

        $message = $this->buildSmtpMessage($to, $subject, $body, $replyTo);

        $this->sendSmtp($socket, $message);
        $dataResponse = $this->readSmtp($socket);

        $this->sendSmtp($socket, "QUIT");
        $this->readSmtp($socket);

        fclose($socket);

        return strpos($dataResponse, '250') === 0;
    }

    private function buildSmtpMessage(string $to, string $subject, string $body, string $replyTo): string
    {
        $siteHost = parse_url(Config::get('site_url', 'localhost'), PHP_URL_HOST) ?? 'localhost';

        $headers = [];
        $headers[] = "From: =?UTF-8?B?" . base64_encode($this->fromName) . "?= <{$this->fromEmail}>";
        $headers[] = "To: <{$to}>";
        $headers[] = "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=";
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "Content-Type: text/html; charset=UTF-8";
        $headers[] = "Content-Transfer-Encoding: base64";
        $headers[] = "Date: " . date('r');
        $headers[] = "Message-ID: <" . uniqid('', true) . "@{$siteHost}>";

        if (!empty($replyTo)) {
            $headers[] = "Reply-To: <{$replyTo}>";
        }

        $encodedBody = chunk_split(base64_encode($body));

        return implode("\r\n", $headers) . "\r\n\r\n" . $encodedBody . "\r\n.";
    }

    private function sendViaMail(string $to, string $subject, string $body, string $replyTo): bool
    {
        $headers = [];
        $headers[] = "From: =?UTF-8?B?" . base64_encode($this->fromName) . "?= <{$this->fromEmail}>";
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "Content-Type: text/html; charset=UTF-8";
        $headers[] = "Content-Transfer-Encoding: base64";

        if (!empty($replyTo)) {
            $headers[] = "Reply-To: <{$replyTo}>";
        }

        $encodedSubject = "=?UTF-8?B?" . base64_encode($subject) . "?=";
        $encodedBody = chunk_split(base64_encode($body));

        return mail($to, $encodedSubject, $encodedBody, implode("\r\n", $headers));
    }

    private function sendSmtp($socket, string $command): void
    {
        fwrite($socket, $command . "\r\n");
    }

    private function readSmtp($socket): string
    {
        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $response;
    }

    public static function sendInquiryNotification(array $inquiry): bool
    {
        $mailer = new self();
        $site = JsonStore::globalData('site')->read();
        if (!is_array($site)) {
            $site = [];
        }
        $to = (string) ($site['admin_email'] ?? Config::get('admin_email', ''));
        if ($to === '') {
            $to = (string) ($site['inquiry_email'] ?? '');
        }
        if ($to === '') {
            return false;
        }
        $subject = sprintf(
            "New Inquiry from %s - %s",
            $inquiry['name'] ?? 'Unknown',
            $inquiry['company'] ?? 'N/A'
        );

        $html = self::buildInquiryEmail($inquiry);
        $replyTo = $inquiry['email'] ?? '';

        return $mailer->send($to, $subject, $html, $replyTo);
    }

    private static function buildInquiryEmail(array $inquiry): string
    {
        $rows = '';
        $fields = [
            'name' => 'Name',
            'email' => 'Email',
            'company' => 'Company',
            'phone' => 'WhatsApp/Phone',
            'product_source' => 'Product Source',
            'message' => 'Message',
        ];

        foreach ($fields as $key => $label) {
            $value = htmlspecialchars($inquiry[$key] ?? 'N/A', ENT_QUOTES, 'UTF-8');
            $rows .= "<tr><td style='padding:8px 12px;font-weight:bold;border-bottom:1px solid #eee;white-space:nowrap;'>{$label}</td>";
            $rows .= "<td style='padding:8px 12px;border-bottom:1px solid #eee;'>" . nl2br($value) . "</td></tr>";
        }

        $submittedAt = date('Y-m-d H:i:s');
        $ip = htmlspecialchars($inquiry['ip'] ?? 'N/A', ENT_QUOTES, 'UTF-8');

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family:Arial,sans-serif;color:#333;max-width:600px;margin:0 auto;">
<div style="background:#1a56db;color:#fff;padding:20px;text-align:center;">
    <h2 style="margin:0;">New B2B Inquiry</h2>
</div>
<div style="padding:20px;">
    <p>A new inquiry has been submitted through your website:</p>
    <table style="width:100%;border-collapse:collapse;">
        {$rows}
    </table>
    <p style="margin-top:20px;color:#666;font-size:12px;">
        Submitted at: {$submittedAt}<br>
        IP: {$ip}
    </p>
</div>
</body>
</html>
HTML;

        return $html;
    }
}
