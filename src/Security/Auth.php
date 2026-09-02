<?php

declare(strict_types=1);

namespace App\Security;

use PDO;

/**
 * Управляет сессионной авторизацией и серверной проверкой ролевых разрешений.
 */
final class Auth
{
    /** @var array<string, mixed>|null */
    private ?array $cachedUser = null;

    /** @var array<string, list<string>> Разрешения задаются в коде и не зависят от скрытия пунктов меню. */
    private const ROLE_PERMISSIONS = [
        'admin' => ['*'],
        'manager' => [
            'dashboard.view', 'bookings.manage', 'clients.manage', 'catalog.manage',
            'services.manage', 'operations.manage', 'reports.view', 'reports.export',
        ],
        'reception' => [
            'dashboard.view', 'bookings.manage', 'clients.manage', 'services.orders',
            'operations.manage', 'reports.view',
        ],
        'housekeeping' => ['dashboard.view', 'housekeeping.manage'],
        'analyst' => ['dashboard.view', 'reports.view', 'reports.export'],
        'guest' => ['account.use'],
    ];

    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Проверяет пару логин/email + пароль и при успехе создаёт новую авторизованную сессию.
     *
     * @param list<string>|null $allowedRoles Допустимые роли для выбранного контура входа.
     */
    public function attempt(string $identifier, string $password, ?array $allowedRoles = null): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, client_id, associate_id, login, email, password_hash, role, active'
            . ' FROM users WHERE login = :login OR email = :email LIMIT 1'
        );
        $stmt->execute(['login' => $identifier, 'email' => $identifier]);
        $user = $stmt->fetch();

        if (
            !$user
            || (int) $user['active'] !== 1
            || !password_verify($password, (string) $user['password_hash'])
            || ($allowedRoles !== null && !in_array((string) $user['role'], $allowedRoles, true))
        ) {
            return false;
        }

        $this->loginUser($user);
        $this->pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id')
            ->execute(['id' => $user['id']]);
        audit_log($this->pdo, 'auth.login', 'user', (string) $user['id'], ['role' => $user['role']]);

        return true;
    }

    public function attemptStaff(string $identifier, string $password): bool
    {
        return $this->attempt($identifier, $password, ['admin', 'manager', 'reception', 'housekeeping', 'analyst']);
    }

    public function attemptGuest(string $identifier, string $password): bool
    {
        return $this->attempt($identifier, $password, ['guest']);
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function isStaff(): bool
    {
        $role = $this->role();
        return $role !== null && $role !== 'guest';
    }

    public function isGuest(): bool
    {
        return $this->role() === 'guest';
    }

    /** @return array<string, mixed>|null */
    public function user(): ?array
    {
        $id = (int) ($_SESSION['user_id'] ?? 0);
        if ($id <= 0) {
            return null;
        }

        // В пределах одного HTTP-запроса повторно не читаем одного пользователя из БД.
        if ($this->cachedUser !== null && (int) $this->cachedUser['id'] === $id) {
            return $this->cachedUser;
        }

        $stmt = $this->pdo->prepare(
            'SELECT id, client_id, associate_id, login, email, role, active, created_at, last_login_at'
            . ' FROM users WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();
        if (!$user || (int) $user['active'] !== 1) {
            $this->clearSession();
            return null;
        }

        $this->cachedUser = $user;
        return $user;
    }

    public function id(): ?int
    {
        $user = $this->user();
        return $user === null ? null : (int) $user['id'];
    }

    public function clientId(): ?int
    {
        $user = $this->user();
        return $user === null || $user['client_id'] === null ? null : (int) $user['client_id'];
    }

    public function role(): ?string
    {
        $user = $this->user();
        return $user === null ? null : (string) $user['role'];
    }

    public function can(string $permission): bool
    {
        $role = $this->role();
        if ($role === null) {
            return false;
        }

        $permissions = self::ROLE_PERMISSIONS[$role] ?? [];
        return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
    }

    public function requireLogin(): void
    {
        $this->requireStaff();
    }

    public function requireStaff(): void
    {
        if ($this->isStaff()) {
            return;
        }

        flash('error', 'Войдите с учётной записью сотрудника.');
        redirect('/admin/login.php');
    }

    public function requireGuest(): void
    {
        if ($this->isGuest() && $this->clientId() !== null) {
            return;
        }

        // После входа гость вернётся на исходную защищённую страницу.
        $_SESSION['_intended_url'] = current_request_uri();
        flash('error', 'Войдите или зарегистрируйтесь, чтобы продолжить.');
        redirect('/login.php');
    }

    public function requirePermission(string $permission): void
    {
        $this->requireStaff();
        if ($this->can($permission)) {
            return;
        }

        http_response_code(403);
        echo '<h1>403</h1><p>Недостаточно прав для выполнения операции.</p>';
        exit;
    }

    public function logout(): void
    {
        $id = $this->id();
        if ($id !== null) {
            audit_log($this->pdo, 'auth.logout', 'user', (string) $id);
        }

        $this->clearSession();
        session_regenerate_id(true);
    }

    /** @param array<string, mixed> $user */
    private function loginUser(array $user): void
    {
        // Новый идентификатор сессии защищает от session fixation.
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['user_login'] = (string) $user['login'];
        $_SESSION['user_role'] = (string) $user['role'];
        $_SESSION['client_id'] = $user['client_id'] === null ? null : (int) $user['client_id'];
        $this->cachedUser = $user;
    }

    private function clearSession(): void
    {
        unset(
            $_SESSION['user_id'],
            $_SESSION['user_login'],
            $_SESSION['user_role'],
            $_SESSION['client_id']
        );
        $this->cachedUser = null;
    }
}
