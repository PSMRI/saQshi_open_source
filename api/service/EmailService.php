<?php

/*!
 * ==========================================================
 * SaQshi Open Source
 * Email Notification Service
 * EmailService.php
 * Version 1.0.0 | Updated 2026-07-18
 * ==========================================================
 */

class EmailService
{
    private array $config;

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? $this->loadConfig();
    }

    public function send(string $to, string $subject, string $message, array $meta = []): array
    {
        $to = trim($to);

        if ($to === '') {
            return ['sent' => false, 'channel' => 'email', 'message' => 'Email address not available.'];
        }

        $transport = strtolower((string)($this->config['transport'] ?? 'log'));
        $enabled = (bool)($this->config['enabled'] ?? false);

        if ($enabled && $transport === 'mail' && function_exists('mail')) {
            $from = trim((string)($this->config['from_email'] ?? ''));
            $headers = $from !== '' ? "From: {$from}\r\n" : '';
            $sent = mail($to, $subject, $message, $headers);
            $this->log($to, $subject, $message, $meta, $sent ? 'sent' : 'failed');
            return ['sent' => $sent, 'channel' => 'email', 'message' => $sent ? 'Email sent.' : 'Email send failed.'];
        }

        if ($enabled && $transport === 'http') {
            return $this->sendHttp($to, $subject, $message, $meta);
        }

        $this->log($to, $subject, $message, $meta, 'logged');
        return ['sent' => false, 'channel' => 'email', 'message' => 'Email logged for configured transport.'];
    }

    public function sendTemplate(string $templateKey, string $to, array $vars = [], array $meta = []): array
    {
        $template = $this->config['templates'][$templateKey] ?? [];
        $subject = $this->render((string)($template['subject'] ?? 'SaQshi notification'), $vars);
        $message = $this->render((string)($template['body'] ?? ''), $vars);

        return $this->send($to, $subject, $message, array_merge($meta, [
            'template' => $templateKey
        ]));
    }

    private function loadConfig(): array
    {
        $path = dirname(__DIR__) . '/config/notifications/email.json';
        $data = file_exists($path) ? json_decode((string)file_get_contents($path), true) : [];
        return is_array($data) ? $data : [];
    }

    private function log(string $to, string $subject, string $message, array $meta, string $status): void
    {
        $path = (string)($this->config['log_path'] ?? 'api/storage/notifications/email.log');
        $fullPath = dirname(__DIR__) . '/../' . ltrim($path, '/\\');
        $dir = dirname($fullPath);

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        file_put_contents($fullPath, json_encode([
            'channel' => 'email',
            'to' => $to,
            'subject' => $subject,
            'message' => $message,
            'meta' => $meta,
            'status' => $status,
            'created_at' => date('c')
        ], JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND);
    }

    private function sendHttp(string $to, string $subject, string $message, array $meta): array
    {
        $http = $this->config['http'] ?? [];
        $url = trim((string)($http['url'] ?? ''));

        if ($url === '') {
            $this->log($to, $subject, $message, $meta, 'missing_url');
            return ['sent' => false, 'channel' => 'email', 'message' => 'Email gateway URL is not configured.'];
        }

        $vars = array_merge($meta, [
            'to' => $to,
            'subject' => $subject,
            'message' => $message,
            'from_email' => (string)($this->config['from_email'] ?? ''),
            'from_name' => (string)($this->config['from_name'] ?? '')
        ]);
        $payload = $this->renderArray($http['body'] ?? [], $vars);
        $headers = $this->headers($http);
        $contentType = strtolower((string)($http['content_type'] ?? 'json'));
        $body = $contentType === 'form'
            ? http_build_query($payload)
            : json_encode($payload, JSON_UNESCAPED_SLASHES);

        if ($contentType === 'form') {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        } else {
            $headers[] = 'Content-Type: application/json';
        }

        $result = $this->httpRequest($url, (string)($http['method'] ?? 'POST'), $headers, (string)$body, (int)($http['timeout_seconds'] ?? 10));
        $this->log($to, $subject, $message, array_merge($meta, ['http_status' => $result['http_status']]), $result['sent'] ? 'sent' : 'failed');

        return ['sent' => $result['sent'], 'channel' => 'email', 'message' => $result['message']];
    }

    private function headers(array $http): array
    {
        $headers = [];

        foreach (($http['headers'] ?? []) as $key => $value) {
            $headers[] = $key . ': ' . $value;
        }

        $auth = $http['auth'] ?? [];
        $type = strtolower((string)($auth['type'] ?? 'none'));

        if ($type === 'bearer') {
            $token = getenv((string)($auth['bearer_token_env'] ?? '')) ?: '';
            if ($token !== '') {
                $headers[] = 'Authorization: Bearer ' . $token;
            }
        } elseif ($type === 'basic') {
            $username = getenv((string)($auth['username_env'] ?? '')) ?: '';
            $password = getenv((string)($auth['password_env'] ?? '')) ?: '';
            if ($username !== '' || $password !== '') {
                $headers[] = 'Authorization: Basic ' . base64_encode($username . ':' . $password);
            }
        } elseif ($type === 'api_key') {
            $key = getenv((string)($auth['api_key_env'] ?? '')) ?: '';
            $header = (string)($auth['api_key_header'] ?? 'X-API-Key');
            if ($key !== '') {
                $headers[] = $header . ': ' . $key;
            }
        }

        return $headers;
    }

    private function httpRequest(string $url, string $method, array $headers, string $body, int $timeout): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => strtoupper($method),
                'header' => implode("\r\n", $headers),
                'content' => $body,
                'timeout' => max(1, $timeout),
                'ignore_errors' => true
            ]
        ]);
        $response = @file_get_contents($url, false, $context);
        $statusLine = $http_response_header[0] ?? '';
        preg_match('/\s(\d{3})\s/', $statusLine, $matches);
        $status = (int)($matches[1] ?? 0);
        $sent = $status >= 200 && $status < 300;

        return [
            'sent' => $sent,
            'http_status' => $status,
            'message' => $sent ? 'Email gateway accepted request.' : 'Email gateway request failed.'
        ];
    }

    private function renderArray(array $data, array $vars): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            $result[$key] = is_array($value)
                ? $this->renderArray($value, $vars)
                : $this->render((string)$value, $vars);
        }

        return $result;
    }

    private function render(string $template, array $vars): string
    {
        $vars['main_url'] = $vars['main_url'] ?? (getenv('SAQSHI_MAIN_URL') ?: '{main_url}');

        foreach ($vars as $key => $value) {
            $template = str_replace('{{' . $key . '}}', (string)$value, $template);
        }

        return $template;
    }
}
