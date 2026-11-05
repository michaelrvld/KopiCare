<?php
declare(strict_types=1);

function env_value(string $name, ?string $default = null): ?string
{
    $value = getenv($name);
    return $value === false || $value === '' ? $default : $value;
}

$dbDriver = strtolower(env_value('DB_DRIVER', 'mysql'));

try {
    if ($dbDriver === 'pgsql') {
        $databaseUrl = env_value('DATABASE_URL');
        if ($databaseUrl !== null) {
            $parts = parse_url($databaseUrl);
            if ($parts === false || empty($parts['host']) || empty($parts['path'])) {
                throw new RuntimeException('DATABASE_URL is invalid.');
            }

            $dbHost = $parts['host'];
            $dbPort = $parts['port'] ?? 5432;
            $dbName = ltrim($parts['path'], '/');
            $dbUser = rawurldecode($parts['user'] ?? '');
            $dbPass = rawurldecode($parts['pass'] ?? '');
        } else {
            $dbHost = env_value('DB_HOST', '127.0.0.1');
            $dbPort = env_value('DB_PORT', '5432');
            $dbName = env_value('DB_NAME', 'postgres');
            $dbUser = env_value('DB_USER', 'postgres');
            $dbPass = env_value('DB_PASS', '');
        }

        $pdo = new PDO(
            "pgsql:host={$dbHost};port={$dbPort};dbname={$dbName};sslmode=require",
            $dbUser,
            $dbPass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => true]
        );
    } elseif ($dbDriver === 'mysql') {
        $dbHost = env_value('DB_HOST', '127.0.0.1');
        $dbName = env_value('DB_NAME', 'kopi_cf');
        $dbUser = env_value('DB_USER', 'root');
        $dbPass = env_value('DB_PASS', '');
        $pdo = new PDO(
            "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
            $dbUser,
            $dbPass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    } else {
        throw new RuntimeException('DB_DRIVER must be mysql or pgsql.');
    }
} catch (Throwable $error) {
    error_log('Database connection failed: '.$error->getMessage());
    http_response_code(500);
    exit('Database belum siap. Periksa konfigurasi koneksi database.');
}

if (env_value('SESSION_STORE') === 'database') {
    final class DatabaseSessionHandler implements SessionHandlerInterface
    {
        public function __construct(private PDO $pdo, private string $driver) {}

        public function open(string $path, string $name): bool { return true; }
        public function close(): bool { return true; }

        public function read(string $id): string|false
        {
            $query = $this->pdo->prepare('SELECT session_data FROM app_sessions WHERE session_id = ? AND expires_at > ?');
            $query->execute([$id, time()]);
            $data = $query->fetchColumn();
            return $data === false ? '' : (string)$data;
        }

        public function write(string $id, string $data): bool
        {
            if ($this->driver === 'pgsql') {
                $sql = 'INSERT INTO app_sessions(session_id, session_data, expires_at) VALUES(?,?,?) ON CONFLICT(session_id) DO UPDATE SET session_data=EXCLUDED.session_data, expires_at=EXCLUDED.expires_at';
            } else {
                $sql = 'INSERT INTO app_sessions(session_id, session_data, expires_at) VALUES(?,?,?) ON DUPLICATE KEY UPDATE session_data=VALUES(session_data), expires_at=VALUES(expires_at)';
            }
            return $this->pdo->prepare($sql)->execute([$id, $data, time() + (int)ini_get('session.gc_maxlifetime')]);
        }

        public function destroy(string $id): bool
        {
            return $this->pdo->prepare('DELETE FROM app_sessions WHERE session_id = ?')->execute([$id]);
        }

        public function gc(int $max_lifetime): int|false
        {
            $query = $this->pdo->prepare('DELETE FROM app_sessions WHERE expires_at <= ?');
            $query->execute([time()]);
            return $query->rowCount();
        }
    }

    session_set_save_handler(new DatabaseSessionHandler($pdo, $dbDriver), true);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
