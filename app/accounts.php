<?php

declare(strict_types=1);

function user_by_id(int $id, bool $lock = false): ?array
{
    $user = row('SELECT id, username, email, role, vendor_id, is_active, auth_version FROM users WHERE id = ?' . ($lock ? ' FOR UPDATE' : ''), [$id]);
    if ($user && $user['role'] === 'vendor') {
        $vendor = row('SELECT name, is_active FROM vendors WHERE id = ?' . ($lock ? ' FOR UPDATE' : ''), [$user['vendor_id']]);
        $user['vendor_name'] = $vendor['name'] ?? '';
        $user['vendor_active'] = (bool) ($vendor['is_active'] ?? false);
    }
    return $user;
}

function account_active(?array $user): bool
{
    return $user && $user['is_active'] && ($user['role'] !== 'vendor' || $user['vendor_active']);
}

function role_home(string $role): string
{
    return match ($role) {
        'admin' => 'admin-students',
        'vendor' => 'vendor-orders',
        default => 'menu',
    };
}

function signed_in_user(): ?array
{
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    $user = user_by_id((int) $_SESSION['user_id']);
    if (account_active($user) && (int) $user['auth_version'] === ($_SESSION['auth_version'] ?? 0)) {
        return $user;
    }
    clear_sign_in();
    notice('error', account_active($user) ? 'Your session changed. Sign in again.' : 'This account is currently disabled. Contact your campus administrator.');
    return null;
}

function clear_sign_in(): void
{
    $_SESSION = ['csrf' => bin2hex(random_bytes(32))];
    session_regenerate_id(true);
}

function sign_in(array $input): array
{
    $identifier = trim(value($input, 'identifier'));
    $password = value($input, 'password');
    $column = str_contains($identifier, '@') ? 'email' : 'username';
    $record = strlen($identifier) <= 254
        ? row("SELECT id, password_hash, auth_version FROM users WHERE $column = ?", [$identifier])
        : null;
    $valid = strlen($password) <= 72 && !str_contains($password, "\0")
        && password_verify($password, $record['password_hash'] ?? '$2y$12$DM.q/FqFrn6X/0AqJ3J8GevHhylLXtYC6GNJH3XdUqUCPXVtf6Ewa');
    if (!$record) {
        throw new RuleViolation('No account was found with that email or username.');
    }
    if (!$valid) {
        throw new RuleViolation('The password is incorrect.');
    }
    $user = user_by_id((int) $record['id']);
    if (!account_active($user)) {
        throw new RuleViolation('This account is currently disabled. Contact your campus administrator.');
    }
    if ($record['auth_version'] !== $user['auth_version']) {
        throw new RuleViolation('Your sign-in details changed. Try signing in again.');
    }
    clear_sign_in();
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['auth_version'] = (int) $user['auth_version'];
    return $user;
}

function account_details(array $input, bool $passwordRequired = true): array
{
    $username = field_text($input, 'username', 'Username', 30, 3);
    if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_.-]*$/D', $username)) {
        throw new RuleViolation('Start your username with a letter or number. Use letters, numbers, periods, underscores or hyphens.');
    }
    $email = strtolower(trim(value($input, 'email')));
    if (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuleViolation('Enter a valid email address.');
    }
    $password = value($input, 'password');
    $confirmation = value($input, 'confirm_password');
    if (!$passwordRequired && $password === '' && $confirmation === '') {
        return [$username, $email, null];
    }
    if (length($password) < 8 || strlen($password) > 72 || str_contains($password, "\0")
        || !preg_match('/[0-9]/', $password)) {
        throw new RuleViolation('Use at least 8 characters, including a number and a special character. Passwords can contain up to 72 bytes.');
    }
    return [$username, $email, password_hash($password, PASSWORD_DEFAULT)];
}

function save_account(array $input, string $role, ?int $vendorId = null, ?int $id = null): int
{
    [$username, $email, $hash] = account_details($input, $id === null);
    $duplicates = rows('SELECT username, email FROM users WHERE id <> ? AND (username = ? OR email = ?)', [$id ?? 0, $username, $email]);
    foreach ($duplicates as $existing) {
        if (strcasecmp($existing['username'], $username) === 0) {
            throw new RuleViolation('This username is already in use.');
        }
        if (strcasecmp($existing['email'], $email) === 0) {
            throw new RuleViolation('This email address is already registered.');
        }
    }
    try {
        if ($id !== null) {
            query('UPDATE users SET username = ?, email = ?, password_hash = COALESCE(?, password_hash), auth_version = auth_version + ? WHERE id = ? AND role = ?',
                [$username, $email, $hash, (int) ($hash !== null), $id, $role]);
            return $id;
        }
        query('INSERT INTO users (username, email, password_hash, role, vendor_id) VALUES (?, ?, ?, ?, ?)', [$username, $email, $hash, $role, $vendorId]);
        return (int) db()->lastInsertId();
    } catch (PDOException $error) {
        if (($error->errorInfo[1] ?? 0) === 1062) {
            throw new RuleViolation('This username or email address is already registered.');
        }
        throw $error;
    }
}

function lock_actor(array $actor, string $role): array
{
    $current = user_by_id((int) $actor['id'], true);
    if (!account_active($current) || $current['role'] !== $role || $current['auth_version'] !== $actor['auth_version']) {
        throw new RuleViolation('Your account does not have access to this action.');
    }
    return $current;
}
