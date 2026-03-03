<?php

class Auth
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function register(string $fullName, string $email, string $password): array
    {
        $fullName = trim($fullName);
        $email = $this->normalizeEmail($email);
        $errors = [];
        $fullNameLength = $this->strLength($fullName);

        if ($fullName === '' || $fullNameLength < 2) {
            $errors[] = 'Имя должно содержать минимум 2 символа.';
        }

        if ($fullNameLength > 120) {
            $errors[] = 'Имя должно содержать максимум 120 символов.';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Укажите корректный email.';
        }

        if (strlen($password) < 8) {
            $errors[] = 'Пароль должен содержать минимум 8 символов.';
        }

        if ($this->findByEmail($email)) {
            $errors[] = 'Пользователь с таким email уже существует.';
        }

        if ($errors) {
            return ['ok' => false, 'errors' => $errors];
        }

        try {
            $stmt = $this->pdo->prepare('INSERT INTO users (full_name, email, password_hash) VALUES (:full_name, :email, :password_hash)');
            $stmt->execute([
                ':full_name' => $fullName,
                ':email' => $email,
                ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                return ['ok' => false, 'errors' => ['Пользователь с таким email уже существует.']];
            }

            throw $exception;
        }

        return ['ok' => true, 'errors' => []];
    }

    public function login(string $email, string $password): bool
    {
        $user = $this->findByEmail($this->normalizeEmail($email));

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];

        return true;
    }

    public function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 3600, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }

        session_destroy();
    }

    public function user(): ?array
    {
        if (empty($_SESSION['user_id'])) {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT id, full_name, email, created_at FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => (int) $_SESSION['user_id']]);

        $user = $stmt->fetch();
        return $user ?: null;
    }

    private function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, email, password_hash FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);

        $user = $stmt->fetch();
        return $user ?: null;
    }

    private function normalizeEmail(string $email): string
    {
        $email = trim($email);

        if (function_exists('mb_strtolower')) {
            return mb_strtolower($email);
        }

        return strtolower($email);
    }

    private function strLength(string $value): int
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($value);
        }

        return strlen($value);
    }
}
