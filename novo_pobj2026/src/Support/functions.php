<?php

declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function app_url(array $query = []): string
{
    $queryString = http_build_query($query);

    return $queryString === '' ? 'index.php' : 'index.php?' . $queryString;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(?string $token): bool
{
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function format_decimal(float $value, int $decimals = 2, string $suffix = ''): string
{
    return number_format($value, $decimals, ',', '.') . $suffix;
}

function format_currency(float $value): string
{
    return 'R$ ' . number_format($value, 2, ',', '.');
}

function format_metric(float $value, string $typeId): string
{
    $typeId = strtoupper($typeId);

    if ($typeId === 'PERCENTUAL') {
        return format_decimal($value, 2, '%');
    }

    if ($typeId === 'QUANTIDADE') {
        return format_decimal($value, 0);
    }

    return format_currency($value);
}

function selected_attr(string $value, ?string $currentValue): string
{
    return $value === $currentValue ? ' selected' : '';
}

function badge_class(bool $condition): string
{
    return $condition ? 'badge-success' : 'badge-neutral';
}