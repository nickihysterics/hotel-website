<?php

declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    // Один криптографический токен действует в пределах текущей сессии.
    if (!isset($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(?string $token): bool
{
    return is_string($token) && isset($_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'], $token);
}

function flash(string $key, ?string $message = null): ?string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if ($message !== null) {
        $_SESSION['_flash'][$key] = $message;
        return null;
    }

    $value = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);

    return $value;
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function current_request_uri(): string
{
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    return str_starts_with($uri, '/') ? $uri : '/';
}

function money(float|int|string $amount): string
{
    return number_format((float) $amount, 0, ',', ' ') . ' ₽';
}

function booking_status_label(string $status): string
{
    return [
        'pending' => 'Ожидает подтверждения',
        'confirmed' => 'Подтверждено',
        'checked_in' => 'Гость заселён',
        'checked_out' => 'Завершено',
        'cancelled' => 'Отменено',
        'no_show' => 'Не заехал',
    ][$status] ?? $status;
}

function service_status_label(string $status): string
{
    return [
        'new' => 'Новый',
        'confirmed' => 'Подтверждён',
        'in_progress' => 'Выполняется',
        'completed' => 'Выполнен',
        'cancelled' => 'Отменён',
    ][$status] ?? $status;
}

function room_status_label(string $status): string
{
    return [
        'available' => 'Свободен',
        'occupied' => 'Занят',
        'dirty' => 'Требуется уборка',
        'cleaning' => 'Уборка',
        'maintenance' => 'Ремонт',
    ][$status] ?? $status;
}

/** @param array<string, mixed> $payload */
function audit_log(
    PDO $pdo,
    string $action,
    ?string $entityType = null,
    ?string $entityId = null,
    array $payload = []
): void {
    try {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'console');
        $stmt = $pdo->prepare(
            'INSERT INTO audit_logs (user_id, action, entity_type, entity_id, payload_json, ip_hash)'
            . ' VALUES (:user_id, :action, :entity_type, :entity_id, :payload_json, :ip_hash)'
        );
        $stmt->execute([
            'user_id' => isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'payload_json' => $payload === [] ? null : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'ip_hash' => hash('sha256', $ip),
        ]);
    } catch (Throwable) {
        // Недоступность журнала не должна откатывать уже выполненную пользовательскую операцию.
    }
}
