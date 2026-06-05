<?php

declare(strict_types=1);

/**
 * Loads Symfony runtime secrets from Infisical before Symfony Runtime/Dotenv boots.
 *
 * Required machine identity settings:
 * - INFISICAL_UNIVERSAL_AUTH_CLIENT_ID
 * - INFISICAL_UNIVERSAL_AUTH_CLIENT_SECRET
 * - INFISICAL_PROJECT_ID
 *
 * Optional:
 * - INFISICAL_ENV=prod
 * - INFISICAL_SECRET_PATH=/
 * - INFISICAL_HOST=https://app.infisical.com
 * - INFISICAL_BOOTSTRAP=0 disables this file
 */

if (infisical_bootstrap_value('INFISICAL_BOOTSTRAP') === '0') {
    return;
}

$clientId = infisical_bootstrap_value('INFISICAL_UNIVERSAL_AUTH_CLIENT_ID');
$clientSecret = infisical_bootstrap_value('INFISICAL_UNIVERSAL_AUTH_CLIENT_SECRET');
$projectId = infisical_bootstrap_value('INFISICAL_PROJECT_ID');

if (!$clientId || !$clientSecret || !$projectId) {
    return;
}

$host = rtrim(infisical_bootstrap_value('INFISICAL_HOST') ?: 'https://app.infisical.com', '/');
$environment = infisical_bootstrap_normalize_environment(infisical_bootstrap_value('INFISICAL_ENV') ?: 'prod');
$secretPath = infisical_bootstrap_value('INFISICAL_SECRET_PATH') ?: '/';

try {
    $token = infisical_bootstrap_login($host, $clientId, $clientSecret);
    if (!$token) {
        return;
    }

    $secrets = infisical_bootstrap_fetch_secrets($host, $token, $projectId, $environment, $secretPath);
    foreach ($secrets as $key => $value) {
        if (!is_string($key) || $key === '' || $value === null || $value === '') {
            continue;
        }

        if (infisical_bootstrap_has_runtime_value($key)) {
            continue;
        }

        $stringValue = (string) $value;
        $_ENV[$key] = $stringValue;
        $_SERVER[$key] = $stringValue;
        putenv($key.'='.$stringValue);
    }
} catch (Throwable $e) {
    if ((infisical_bootstrap_value('INFISICAL_DEBUG') ?: '') === '1') {
        error_log('[Infisical] Bootstrap failed: '.$e->getMessage());
    }
}

function infisical_bootstrap_value(string $key): ?string
{
    $value = $_SERVER[$key] ?? $_ENV[$key] ?? getenv($key);
    if (is_string($value) && $value !== '') {
        return $value;
    }

    static $localValues = null;
    if ($localValues === null) {
        $root = dirname(__DIR__);
        $localValues = [];
        foreach (['.env', '.env.local'] as $file) {
            $path = $root.DIRECTORY_SEPARATOR.$file;
            if (!is_file($path)) {
                continue;
            }

            foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $line = trim((string) $line);
                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }

                if (str_starts_with($line, 'export ')) {
                    $line = trim(substr($line, 7));
                }

                $idx = strpos($line, '=');
                if ($idx === false || $idx === 0) {
                    continue;
                }

                $name = trim(substr($line, 0, $idx));
                if (!str_starts_with($name, 'INFISICAL_')) {
                    continue;
                }

                $raw = trim(substr($line, $idx + 1));
                $value = infisical_bootstrap_unquote($raw);
                if ($value === '' && isset($localValues[$name]) && $localValues[$name] !== '') {
                    continue;
                }

                $localValues[$name] = $value;
            }
        }
    }

    $local = $localValues[$key] ?? null;

    return is_string($local) && $local !== '' ? $local : null;
}

function infisical_bootstrap_has_runtime_value(string $key): bool
{
    $value = $_SERVER[$key] ?? $_ENV[$key] ?? getenv($key);

    return is_string($value) && $value !== '';
}

function infisical_bootstrap_unquote(string $value): string
{
    if (strlen($value) >= 2) {
        $first = $value[0];
        $last = $value[strlen($value) - 1];
        if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
            return substr($value, 1, -1);
        }
    }

    return $value;
}

function infisical_bootstrap_normalize_environment(string $environment): string
{
    return match (strtolower(trim($environment))) {
        'production' => 'prod',
        'development' => 'dev',
        default => trim($environment),
    };
}

function infisical_bootstrap_login(string $host, string $clientId, string $clientSecret): ?string
{
    $response = infisical_bootstrap_request(
        'POST',
        $host.'/api/v1/auth/universal-auth/login',
        null,
        [
            'clientId' => $clientId,
            'clientSecret' => $clientSecret,
        ]
    );

    return infisical_bootstrap_find_string($response, ['accessToken', 'token']);
}

/**
 * @return array<string, string>
 */
function infisical_bootstrap_fetch_secrets(
    string $host,
    string $token,
    string $projectId,
    string $environment,
    string $secretPath
): array {
    $query = [
        'environment' => $environment,
        'secretPath' => $secretPath,
        'include_imports' => 'true',
        'expandSecretReferences' => 'true',
    ];

    foreach (['workspaceId', 'projectId'] as $projectParam) {
        $query[$projectParam] = $projectId;
        $url = $host.'/api/v3/secrets/raw?'.http_build_query($query);
        $response = infisical_bootstrap_request('GET', $url, $token);
        $secrets = infisical_bootstrap_normalize_secrets($response);
        if ($secrets !== []) {
            return $secrets;
        }
        unset($query[$projectParam]);
    }

    return [];
}

/**
 * @param array<string, mixed>|null $body
 *
 * @return array<string, mixed>
 */
function infisical_bootstrap_request(string $method, string $url, ?string $token = null, ?array $body = null): array
{
    $headers = ['Content-Type: application/json'];
    if ($token) {
        $headers[] = 'Authorization: Bearer '.$token;
    }

    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'header' => implode("\n", $headers),
            'content' => $body === null ? null : json_encode($body, JSON_THROW_ON_ERROR),
            'ignore_errors' => true,
            'timeout' => 8,
        ],
    ]);

    $raw = @file_get_contents($url, false, $context);
    if (!is_string($raw) || $raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);

    return is_array($decoded) ? $decoded : [];
}

/**
 * @param array<string, mixed> $payload
 * @param list<string>        $keys
 */
function infisical_bootstrap_find_string(array $payload, array $keys): ?string
{
    foreach ($keys as $key) {
        $value = $payload[$key] ?? null;
        if (is_string($value) && $value !== '') {
            return $value;
        }
    }

    foreach ($payload as $value) {
        if (is_array($value)) {
            $found = infisical_bootstrap_find_string($value, $keys);
            if ($found) {
                return $found;
            }
        }
    }

    return null;
}

/**
 * @param array<string, mixed> $payload
 *
 * @return array<string, string>
 */
function infisical_bootstrap_normalize_secrets(array $payload): array
{
    $items = $payload['secrets'] ?? $payload['data']['secrets'] ?? $payload['data'] ?? [];
    $normalized = [];

    if (is_array($items)) {
        foreach ($items as $key => $item) {
            if (is_string($key) && (is_string($item) || is_numeric($item) || is_bool($item))) {
                $normalized[$key] = (string) $item;
                continue;
            }

            if (!is_array($item)) {
                continue;
            }

            $secretKey = $item['secretKey'] ?? $item['key'] ?? $item['name'] ?? null;
            $secretValue = $item['secretValue'] ?? $item['value'] ?? null;
            if (is_string($secretKey) && $secretKey !== '' && (is_string($secretValue) || is_numeric($secretValue) || is_bool($secretValue))) {
                $normalized[$secretKey] = (string) $secretValue;
            }
        }
    }

    return $normalized;
}
