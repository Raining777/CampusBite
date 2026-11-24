<?php

declare(strict_types=1);

final class RuleViolation extends RuntimeException {}

function config(): array
{
    static $config;
    if ($config === null) {
        $config = [
            'host' => '127.0.0.1', 'port' => 3306, 'database' => 'campusbite',
            'username' => 'campusbite', 'password' => '',
            'timezone' => 'Asia/Kuala_Lumpur', 'currency' => 'RM', 'session_secure' => false,
        ];
        $local = dirname(__DIR__) . '/config.local.php';
        if (is_file($local)) {
            $config = array_replace($config, require $local);
        }
        foreach (['host', 'port', 'database', 'username', 'password'] as $option) {
            $value = getenv('CAMPUSBITE_DB_' . strtoupper($option));
            if ($value !== false) {
                $config[$option] = $value;
            }
        }
        $secure = getenv('CAMPUSBITE_SESSION_SECURE');
        if ($secure !== false) {
            $config['session_secure'] = filter_var($secure, FILTER_VALIDATE_BOOL);
        }
        date_default_timezone_set($config['timezone']);
    }
    return $config;
}

function db(): PDO
{
    static $connection;
    if ($connection === null) {
        $config = config();
        $connection = new PDO(
            sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $config['host'], $config['port'], $config['database']),
            $config['username'],
            $config['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
        );
        $connection->exec("SET time_zone = '+00:00'");
    }
    return $connection;
}

function query(string $sql, array $parameters = []): PDOStatement
{
    $statement = db()->prepare($sql);
    $statement->execute($parameters);
    return $statement;
}

function rows(string $sql, array $parameters = []): array
{
    return query($sql, $parameters)->fetchAll();
}

function row(string $sql, array $parameters = []): ?array
{
    return query($sql, $parameters)->fetch() ?: null;
}

function transaction(callable $work): mixed
{
    $db = db();
    $db->beginTransaction();
    try {
        $result = $work();
        $db->commit();
        return $result;
    } catch (Throwable $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $error;
    }
}

function start_session(): void
{
    session_name('campusbite');
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'path' => '/', 'httponly' => true, 'samesite' => 'Lax',
        'secure' => config()['session_secure'] || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ]);
    session_start();
    $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function value(array $input, string $name): string
{
    return is_string($input[$name] ?? null) ? $input[$name] : '';
}

function natural(mixed $value): int
{
    if (!is_string($value) && !is_int($value)) {
        return 0;
    }
    return filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
}

function length(string $value): int
{
    return preg_match_all('/./us', $value) ?: 0;
}

function field_text(array $input, string $name, string $label, int $maximum, int $minimum = 1): string
{
    $text = trim(value($input, $name));
    if ($minimum === 1 && length($text) === 0) {
        throw new RuleViolation("$label is required.");
    }
    if (length($text) < $minimum || length($text) > $maximum || !preg_match('//u', $text)) {
        throw new RuleViolation("$label must be between $minimum and $maximum characters.");
    }
    return $text;
}

function cents(string $decimal): int
{
    [$whole, $fraction] = array_pad(explode('.', $decimal, 2), 2, '');
    return (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
}

function decimal(int $amount): string
{
    return sprintf('%d.%02d', intdiv($amount, 100), $amount % 100);
}

function price(string $decimal): string
{
    if (!preg_match('/^\d{1,4}(?:\.\d{1,2})?$/D', $decimal) || cents($decimal) < 1) {
        throw new RuleViolation('Enter a price between 0.01 and 9999.99 with up to two decimal places.');
    }
    return decimal(cents($decimal));
}

function money(int $amount): string
{
    return config()['currency'] . ' ' . number_format($amount / 100, 2, '.', ',');
}

function date_label(string $utc): string
{
    return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))
        ->setTimezone(new DateTimeZone(config()['timezone']))->format('j M Y, g:i a');
}

function e(string|int|null $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $page = 'menu', array $parameters = []): string
{
    return 'index.php?' . http_build_query(['page' => $page] + $parameters);
}

function redirect(string $page, array $parameters = []): never
{
    header('Location: ' . url($page, $parameters), true, 303);
    exit;
}

function notice(string $type, string $message): void
{
    $_SESSION['notice'] = ['type' => $type, 'message' => $message];
}

function take_session(string $key): mixed
{
    $value = $_SESSION[$key] ?? null;
    unset($_SESSION[$key]);
    return $value;
}
