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

function format_int_readable(float $value): string
{
    return number_format((int) round($value), 0, ',', '.');
}

function format_points_readable(float $value): string
{
    $rounded = round($value, 2);
    $decimals = abs($rounded - round($rounded)) < 0.005 || abs($rounded) >= 1000 ? 0 : 2;

    return number_format($rounded, $decimals, ',', '.');
}

function format_compact_number(float $value, int $baseDecimals = 2): string
{
    $absolute = abs($value);
    $scales = [
        ['divisor' => 1000000000000.0, 'suffix' => ' tri'],
        ['divisor' => 1000000000.0, 'suffix' => ' bi'],
        ['divisor' => 1000000.0, 'suffix' => ' mi'],
        ['divisor' => 1000.0, 'suffix' => ' mil'],
    ];

    foreach ($scales as $scale) {
        $divisor = (float) ($scale['divisor'] ?? 0.0);
        $suffix = (string) ($scale['suffix'] ?? '');

        if ($divisor <= 0) {
            continue;
        }

        if ($absolute < $divisor) {
            continue;
        }

        $scaled = $value / $divisor;
        $scaledAbsolute = abs($scaled);
        $decimals = $scaledAbsolute >= 100 ? 0 : 1;

        if (abs($scaled - round($scaled)) < 0.05) {
            $decimals = 0;
        }

        return number_format($scaled, $decimals, ',', '.') . $suffix;
    }

    $rounded = round($value, $baseDecimals);
    $decimals = $baseDecimals;

    if ($baseDecimals > 0 && abs($rounded - round($rounded)) < 0.005) {
        $decimals = 0;
    }

    return number_format($rounded, $decimals, ',', '.');
}

function format_number_readable(float $value): string
{
    return format_compact_number($value, 2);
}

function format_quantity_readable(float $value): string
{
    return format_compact_number($value, 0);
}

function format_currency_readable(float $value): string
{
    return 'R$ ' . format_compact_number($value, 2);
}

function clamp_date_to_today(?string $value): string
{
    $value = trim((string) $value);

    if ($value === '') {
        return '';
    }

    $today = (new DateTimeImmutable('today'))->format('Y-m-d');

    return strcmp($value, $today) > 0 ? $today : $value;
}

function format_date_br(?string $value): string
{
    $value = trim((string) $value);

    if ($value === '') {
        return '';
    }

    try {
        return (new DateTimeImmutable($value))->format('d/m/Y');
    } catch (Throwable $exception) {
        return $value;
    }
}

function resolve_period_id_by_date_range(array $periods, ?string $startDate, ?string $endDate, ?string $fallbackPeriodId = null): ?string
{
    $today = (new DateTimeImmutable('today'))->format('Y-m-d');
    $startDate = clamp_date_to_today($startDate);
    $endDate = clamp_date_to_today($endDate);

    if ($startDate === '' || $endDate === '') {
        return $fallbackPeriodId;
    }

    try {
        $requestedStart = new DateTimeImmutable($startDate);
        $requestedEnd = new DateTimeImmutable($endDate);
    } catch (Throwable $exception) {
        return $fallbackPeriodId;
    }

    $bestPeriodId = $fallbackPeriodId;
    $bestScore = null;

    foreach ($periods as $period) {
        $periodId = (string) ($period['id_periodo'] ?? '');
        $periodStartRaw = trim((string) ($period['dt_inicio_periodo'] ?? ''));
        $periodEndRaw = trim((string) ($period['dt_fim_periodo'] ?? ''));

        if ($periodId === '' || $periodStartRaw === '' || $periodEndRaw === '') {
            continue;
        }

        if (strcmp($periodStartRaw, $today) > 0) {
            continue;
        }

        if ($periodStartRaw === $startDate && $periodEndRaw === $endDate) {
            return $periodId;
        }

        try {
            $periodStart = new DateTimeImmutable($periodStartRaw);
            $periodEnd = new DateTimeImmutable($periodEndRaw);
        } catch (Throwable $exception) {
            continue;
        }

        $overlapStart = max($requestedStart->getTimestamp(), $periodStart->getTimestamp());
        $overlapEnd = min($requestedEnd->getTimestamp(), $periodEnd->getTimestamp());
        $overlapDays = max(0, ($overlapEnd - $overlapStart) / 86400);
        $distancePenalty = abs($requestedStart->getTimestamp() - $periodStart->getTimestamp()) / 86400;
        $distancePenalty += abs($requestedEnd->getTimestamp() - $periodEnd->getTimestamp()) / 86400;
        $score = $overlapDays > 0 ? $overlapDays - ($distancePenalty * 0.01) : -$distancePenalty;

        if ($bestScore === null || $score > $bestScore) {
            $bestScore = $score;
            $bestPeriodId = $periodId;
        }
    }

    return $bestPeriodId;
}

function resolve_accumulated_view_date_range(string $view, ?string $referenceEndDate = null): array
{
    $view = strtoupper(trim($view));
    $endDateRaw = clamp_date_to_today($referenceEndDate);

    try {
        $endDate = new DateTimeImmutable($endDateRaw !== '' ? $endDateRaw : 'today');
    } catch (Throwable $exception) {
        $endDate = new DateTimeImmutable('today');
    }

    switch ($view) {
        case 'SEMESTRAL':
            $startDate = $endDate
                ->modify('first day of this month')
                ->sub(new DateInterval('P5M'));
            break;

        case 'ANUAL':
            $startDate = $endDate->setDate((int) $endDate->format('Y'), 1, 1);
            break;

        case 'MENSAL':
        default:
            $startDate = $endDate->modify('first day of this month');
            break;
    }

    return [
        'start' => $startDate->format('Y-m-d'),
        'end' => $endDate->format('Y-m-d'),
    ];
}

function resolve_business_day_snapshot(?string $startDate, ?string $endDate, ?string $referenceDate = null): array
{
    $startDate = trim((string) $startDate);
    $endDate = trim((string) $endDate);

    if ($startDate === '' || $endDate === '') {
        return [
            'total' => 0,
            'elapsed' => 0,
            'remaining' => 0,
            'start' => '',
            'end' => '',
            'today' => (new DateTimeImmutable('today'))->format('Y-m-d'),
        ];
    }

    try {
        $start = new DateTimeImmutable($startDate);
        $end = new DateTimeImmutable($endDate);
        $today = new DateTimeImmutable(clamp_date_to_today($referenceDate));
    } catch (Throwable $exception) {
        return [
            'total' => 0,
            'elapsed' => 0,
            'remaining' => 0,
            'start' => $startDate,
            'end' => $endDate,
            'today' => (new DateTimeImmutable('today'))->format('Y-m-d'),
        ];
    }

    if ($start > $end) {
        [$start, $end] = [$end, $start];
    }

    $effectiveToday = $today > $end ? $end : $today;
    $total = count_business_days_inclusive($start, $end);
    $elapsed = $effectiveToday >= $start ? count_business_days_inclusive($start, $effectiveToday) : 0;

    return [
        'total' => $total,
        'elapsed' => $elapsed,
        'remaining' => max(0, $total - $elapsed),
        'start' => $start->format('Y-m-d'),
        'end' => $end->format('Y-m-d'),
        'today' => $effectiveToday->format('Y-m-d'),
    ];
}

function count_business_days_inclusive(DateTimeImmutable $start, DateTimeImmutable $end): int
{
    if ($start > $end) {
        return 0;
    }

    $days = 0;
    $current = $start;

    while ($current <= $end) {
        $weekday = (int) $current->format('N');

        if ($weekday < 6) {
            $days++;
        }

        $current = $current->add(new DateInterval('P1D'));
    }

    return $days;
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

function format_metric_readable(float $value, string $typeId): string
{
    $typeId = strtoupper($typeId);

    if ($typeId === 'PERCENTUAL') {
        return format_decimal($value, 2, '%');
    }

    if ($typeId === 'QUANTIDADE') {
        return format_quantity_readable($value);
    }

    return format_currency_readable($value);
}

function selected_attr(string $value, ?string $currentValue): string
{
    return $value === $currentValue ? ' selected' : '';
}

function badge_class(bool $condition): string
{
    return $condition ? 'badge-success' : 'badge-neutral';
}

function user_initials(?string $name): string
{
    $name = trim((string) $name);

    if ($name === '') {
        return 'NP';
    }

    $parts = preg_split('/\s+/', $name) ?: [];
    $parts = array_values(array_filter($parts, static function (string $part): bool {
        return $part !== '';
    }));

    if ($parts === []) {
        return 'NP';
    }

    $firstPart = $parts[0];
    $lastPart = $parts[count($parts) - 1];
    $firstInitial = function_exists('mb_substr') ? mb_substr($firstPart, 0, 1) : substr($firstPart, 0, 1);
    $lastInitial = function_exists('mb_substr') ? mb_substr($lastPart, 0, 1) : substr($lastPart, 0, 1);

    if (count($parts) === 1) {
        $secondInitial = function_exists('mb_substr') ? mb_substr($firstPart, 1, 1) : substr($firstPart, 1, 1);

        return strtoupper($firstInitial . ($secondInitial !== false ? $secondInitial : ''));
    }

    return strtoupper($firstInitial . $lastInitial);
}

function first_name(?string $name): string
{
    $name = trim((string) $name);

    if ($name === '') {
        return 'Usuario';
    }

    $parts = preg_split('/\s+/', $name) ?: [];

    return $parts[0] !== '' ? $parts[0] : 'Usuario';
}