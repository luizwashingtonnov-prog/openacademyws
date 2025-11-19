<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function load_env_file(): array
{
    static $cached = null;

    if ($cached !== null) {
        return $cached;
    }

    $cached = [];

    $envPath = __DIR__ . DIRECTORY_SEPARATOR . '.env';
    if (!is_file($envPath)) {
        return $cached;
    }

    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return $cached;
    }

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        if (!str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        if ($key === '') {
            continue;
        }

        $value = trim($value);
        if ($value !== '' && ($value[0] === '"' || $value[0] === "'") && str_ends_with($value, $value[0])) {
            $value = substr($value, 1, -1);
        }

        $cached[$key] = $value;
    }

    return $cached;
}

function env(string $key, $default = null)
{
    $fileEnv = load_env_file();
    $sources = [
        $_ENV,
        $_SERVER,
    ];

    foreach ($sources as $source) {
        if (array_key_exists($key, $source)) {
            $value = $source[$key];

            if ($value === '' && array_key_exists($key, $fileEnv) && $fileEnv[$key] !== '') {
                return $fileEnv[$key];
            }

            return $value;
        }
    }

    $value = getenv($key);
    if ($value !== false) {
        if ($value === '' && array_key_exists($key, $fileEnv) && $fileEnv[$key] !== '') {
            return $fileEnv[$key];
        }

        return $value;
    }

    if (array_key_exists($key, $fileEnv)) {
        return $fileEnv[$key];
    }

    return $default;
}

define('APP_NAME', env('APP_NAME', 'Training'));
$customBase = (string) env('APP_BASE_URL', '');
if ($customBase !== '') {
    $customBase = rtrim($customBase, '/');
    define('BASE_URL', $customBase === '' ? '/' : $customBase);
} else {
    $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
    $scriptDir = str_replace('\\', '/', $scriptDir);
    $scriptDir = preg_replace('#/+#', '/', $scriptDir);
    if ($scriptDir !== '/' && str_ends_with($scriptDir, '/public')) {
        $scriptDir = substr($scriptDir, 0, -strlen('/public'));
    }
    $scriptDir = rtrim($scriptDir, '/');
    define('BASE_URL', $scriptDir === '' ? '/' : $scriptDir);
}
define('PASSING_GRADE', 7.0);
define('QUESTION_COUNT', 12);
define('MODULE_QUESTION_COUNT', 2);
define('COURSE_COMPLETION_ASSESSMENT', 'assessment');
define('COURSE_COMPLETION_ACK', 'acknowledgement');

function course_completion_modes(): array
{
    return [
        COURSE_COMPLETION_ASSESSMENT => [
            'label' => 'Avaliacao final',
            'description' => 'Requer prova final antes da emissao do certificado.',
        ],
        COURSE_COMPLETION_ACK => [
            'label' => 'Marcar como lido',
            'description' => 'O aluno confirma a leitura para emitir o certificado.',
        ],
    ];
}

function normalize_course_completion_type(?string $type): string
{
    $modes = course_completion_modes();
    $key = $type ?? '';
    return array_key_exists($key, $modes) ? $key : COURSE_COMPLETION_ASSESSMENT;
}

define('DB_DRIVER', strtolower((string) env('DB_DRIVER', env('DB_CONNECTION', 'mysql'))));
define('DB_HOST', env('DB_HOST', env('DB_HOSTNAME', '127.0.0.1')));
define('DB_USER', env('DB_USER', env('DB_USERNAME', 'root')));
define('DB_PASS', env('DB_PASS', env('DB_PASSWORD', '')));
define('DB_NAME', env('DB_NAME', env('DB_DATABASE', 'sistemaead')));
define('DB_PORT', (int) env('DB_PORT', env('MYSQL_PORT', 3306)));

define('DEFAULT_ADMIN_NAME', env('DEFAULT_ADMIN_NAME', 'Administrador'));
define('DEFAULT_ADMIN_EMAIL', env('DEFAULT_ADMIN_EMAIL', 'admin@ead.test'));
define('DEFAULT_ADMIN_PASSWORD', env('DEFAULT_ADMIN_PASSWORD', 'Senha@123'));

if (!defined('MYSQLI_ASSOC')) {
    define('MYSQLI_ASSOC', 1);
}

function db_driver(): string
{
    return DB_DRIVER;
}

function using_mysql(): bool
{
    return db_driver() === 'mysql';
}

function using_pgsql(): bool
{
    return db_driver() === 'pgsql';
}

final class DatabaseConnection
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function prepare(string $sql): DatabaseStatement
    {
        return new DatabaseStatement($this->pdo, $sql);
    }

    public function query(string $sql): DatabaseResult
    {
        $statement = $this->pdo->query($sql);
        return new DatabaseResult($statement);
    }

    public function exec(string $sql): int
    {
        return $this->pdo->exec($sql);
    }

    public function begin_transaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    public function rollback(): bool
    {
        return $this->pdo->rollBack();
    }

    public function lastInsertId(?string $sequence = null): string
    {
        return $sequence ? $this->pdo->lastInsertId($sequence) : $this->pdo->lastInsertId();
    }

    public function set_charset(string $charset): bool
    {
        return true;
    }

    public function getPdo(): \PDO
    {
        return $this->pdo;
    }
}

final class DatabaseStatement
{
    private \PDO $pdo;
    private \PDOStatement $statement;
    private array $boundParams = [];
    private ?array $prefetchedRows = null;
    public int $insert_id = 0;
    public int $affected_rows = 0;

    public function __construct(\PDO $pdo, string $sql)
    {
        $this->pdo = $pdo;
        $this->statement = $pdo->prepare($sql);
    }

    public function bind_param(string $types, ...$vars): bool
    {
        $this->boundParams = [];
        foreach ($vars as $value) {
            $this->boundParams[] = $value;
        }

        return true;
    }

    public function execute(?array $params = null): bool
    {
        $this->statement->closeCursor();
        $this->prefetchedRows = null;
        $this->insert_id = 0;

        $values = $params ?? $this->collectBoundValues();
        $executed = $this->statement->execute($values);
        $this->affected_rows = $this->statement->rowCount();

        if (!$executed) {
            return false;
        }

        $queryString = strtolower($this->statement->queryString);
        if (preg_match('/\\breturning\\b/', $queryString)) {
            $rows = $this->statement->fetchAll(\PDO::FETCH_ASSOC);
            $this->prefetchedRows = $rows;
            if (!empty($rows)) {
                $firstRow = $rows[0];
                $firstValue = reset($firstRow);
                if ($firstValue !== false && $firstValue !== null) {
                    $this->insert_id = (int) $firstValue;
                }
            }
            $this->statement->closeCursor();
        } elseif (str_starts_with(ltrim($queryString), 'insert')) {
            $lastId = $this->pdo->lastInsertId();
            if ($lastId !== false && $lastId !== null && $lastId !== '') {
                $this->insert_id = (int) $lastId;
            }
        }

        return true;
    }

    public function get_result(): DatabaseResult
    {
        if ($this->prefetchedRows !== null) {
            $rows = $this->prefetchedRows;
            $this->prefetchedRows = null;
            return new DatabaseResult(null, $rows);
        }

        return new DatabaseResult($this->statement);
    }

    public function close(): bool
    {
        $this->statement->closeCursor();
        $this->boundParams = [];
        $this->prefetchedRows = null;
        return true;
    }

    private function collectBoundValues(): array
    {
        return $this->boundParams;
    }
}

final class DatabaseResult
{
    private array $rows;
    private int $position = 0;
    public int $num_rows = 0;

    public function __construct(?\PDOStatement $statement, ?array $prefetched = null)
    {
        if ($prefetched !== null) {
            $this->rows = array_values($prefetched);
        } elseif ($statement !== null) {
            $this->rows = $statement->fetchAll(\PDO::FETCH_ASSOC);
            $statement->closeCursor();
        } else {
            $this->rows = [];
        }

        $this->num_rows = count($this->rows);
    }

    public function fetch_assoc(): ?array
    {
        if ($this->position >= $this->num_rows) {
            return null;
        }

        $row = $this->rows[$this->position];
        $this->position++;

        return $row;
    }

    public function fetch_all(int $mode = MYSQLI_ASSOC): array
    {
        return $this->rows;
    }
}

function get_db(): DatabaseConnection
{
    static $connection;

    if ($connection instanceof DatabaseConnection) {
        return $connection;
    }

    $driver = DB_DRIVER;
    $options = [
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        \PDO::ATTR_EMULATE_PREPARES => false,
    ];

    if ($driver === 'pgsql') {
        $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s', DB_HOST, DB_PORT, DB_NAME);
    } elseif ($driver === 'mysql') {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
    } else {
        throw new RuntimeException('DB_DRIVER nao suportado: ' . $driver);
    }

    try {
        $pdo = new \PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (\PDOException $exception) {
        throw new RuntimeException('Falha ao conectar ao banco de dados: ' . $exception->getMessage(), 0, $exception);
    }

    $connection = new DatabaseConnection($pdo);

    ensure_module_tables($connection);
    ensure_default_admin($connection);

    return $connection;
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function flash(string $message, string $type = 'info'): void
{
    $_SESSION['flash'][] = [
        'message' => $message,
        'type' => $type,
    ];
}

function get_flash(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

// CSRF Protection
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function verify_csrf_token(?string $token): bool
{
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function require_csrf_token(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!verify_csrf_token($token)) {
            flash('Token de segurança inválido. Por favor, tente novamente.', 'danger');
            redirect($_SERVER['PHP_SELF'] . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''));
        }
    }
}

// Password validation
function validate_password(string $password): array
{
    $errors = [];
    
    if (strlen($password) < 8) {
        $errors[] = 'A senha deve ter pelo menos 8 caracteres.';
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'A senha deve conter pelo menos uma letra maiúscula.';
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'A senha deve conter pelo menos uma letra minúscula.';
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'A senha deve conter pelo menos um número.';
    }
    
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = 'A senha deve conter pelo menos um caractere especial.';
    }
    
    return $errors;
}

// Input sanitization
function sanitize_string(?string $value, int $maxLength = 255): string
{
    if ($value === null) {
        return '';
    }
    $value = trim($value);
    if ($maxLength > 0 && strlen($value) > $maxLength) {
        $value = substr($value, 0, $maxLength);
    }
    return $value;
}

function sanitize_email(?string $value): string
{
    if ($value === null) {
        return '';
    }
    return strtolower(trim(filter_var($value, FILTER_SANITIZE_EMAIL)));
}

function sanitize_int(?string $value, int $min = PHP_INT_MIN, int $max = PHP_INT_MAX): int
{
    if ($value === null) {
        return 0;
    }
    $int = (int) $value;
    return max($min, min($max, $int));
}
function resolve_video_embed(?string $url): ?array
{
    if ($url === null) {
        return null;
    }

    $url = trim($url);
    if ($url === '') {
        return null;
    }

    $streamable = ['mp4', 'webm', 'ogg', 'ogv', 'm4v'];

    if (!is_external_url($url)) {
        $normalized = $url;
        if ($normalized === '' || $normalized[0] !== '/') {
            $normalized = asset_url($normalized);
        }

        $path = parse_url($normalized, PHP_URL_PATH) ?? $normalized;
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($extension !== '' && in_array($extension, $streamable, true)) {
            return [
                'type' => 'video',
                'src' => $normalized
            ];
        }

        return null;
    }

    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return null;
    }

    $parsed = parse_url($url);
    if ($parsed === false || !isset($parsed['host'])) {
        return null;
    }

    $host = strtolower($parsed['host']);
    $path = $parsed['path'] ?? '';

    if (str_contains($host, 'youtube.com') || str_contains($host, 'youtu.be')) {
        $videoId = '';

        if (str_contains($host, 'youtube.com')) {
            parse_str($parsed['query'] ?? '', $query);
            if (!empty($query['v'])) {
                $videoId = $query['v'];
            } elseif (!empty($path)) {
                $segments = array_values(array_filter(explode('/', $path)));
                if (!empty($segments)) {
                    $videoId = end($segments);
                }
            }
        }

        if ($videoId === '' && str_contains($host, 'youtu.be')) {
            $videoId = ltrim($path, '/');
        }

        if ($videoId !== '') {
            return [
                'type' => 'iframe',
                'src' => 'https://www.youtube.com/embed/' . urlencode($videoId),
                'allow' => 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share'
            ];
        }
    }

    if (str_contains($host, 'vimeo.com')) {
        $segments = array_values(array_filter(explode('/', $path)));
        if (!empty($segments)) {
            $videoId = end($segments);
            if (ctype_digit($videoId)) {
                return [
                    'type' => 'iframe',
                    'src' => 'https://player.vimeo.com/video/' . $videoId,
                    'allow' => 'autoplay; fullscreen; picture-in-picture'
                ];
            }
        }
    }

    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if ($extension !== '' && in_array($extension, $streamable, true)) {
        return [
            'type' => 'video',
            'src' => $url
        ];
    }

    return null;
}

function render_video_player(?string $url): ?string
{
    $embed = resolve_video_embed($url);
    if ($embed === null) {
        return null;
    }

    $src = htmlspecialchars($embed['src'], ENT_QUOTES, 'UTF-8');
    if ($embed['type'] === 'iframe') {
        $allow = htmlspecialchars($embed['allow'] ?? 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture', ENT_QUOTES, 'UTF-8');
        return '<iframe src="' . $src . '" allow="' . $allow . '" allowfullscreen loading="lazy" title="Video do curso" style="width:100%;height:100%;border:0;"></iframe>';
    }

    if ($embed['type'] === 'video') {
        return '<video src="' . $src . '" controls class="w-full h-full" preload="metadata"></video>';
    }

    return null;
}
function is_external_url(?string $value): bool
{
    if ($value === null || $value === '') {
        return false;
    }

    return (bool) preg_match('#^https?://#i', $value);
}

function asset_url(?string $path): string
{
    if ($path === null || $path === '') {
        return '';
    }

    if (is_external_url($path)) {
        return $path;
    }

    $prefix = BASE_URL === '/' ? '' : BASE_URL;

    return $prefix . '/' . ltrim($path, '/');
}

function store_uploaded_signature(array $file): array
{
    $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($error !== UPLOAD_ERR_OK) {
        $messages = [
            UPLOAD_ERR_INI_SIZE => 'Arquivo de assinatura excede o tamanho maximo permitido.',
            UPLOAD_ERR_FORM_SIZE => 'Arquivo de assinatura excede o tamanho maximo permitido.',
            UPLOAD_ERR_PARTIAL => 'Upload da assinatura foi concluido parcialmente. Tente novamente.',
            UPLOAD_ERR_NO_FILE => 'Nenhum arquivo de assinatura enviado.',
        ];

        return [
            'success' => false,
            'message' => $messages[$error] ?? 'Falha ao enviar o arquivo da assinatura.',
        ];
    }

    $tmpName = $file['tmp_name'] ?? '';
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        return [
            'success' => false,
            'message' => 'Upload de assinatura invalido. Tente novamente.',
        ];
    }

    $maxSize = 2 * 1024 * 1024; // 2 MB
    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > $maxSize) {
        return [
            'success' => false,
            'message' => 'A assinatura deve ter ate 2 MB no formato PNG.',
        ];
    }

    $imageInfo = @getimagesize($tmpName);
    if ($imageInfo === false || ($imageInfo[2] ?? null) !== IMAGETYPE_PNG) {
        return [
            'success' => false,
            'message' => 'Envie uma imagem PNG valida para a assinatura.',
        ];
    }

    $uploadDir = __DIR__ . '/public/uploads/signatures';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
        return [
            'success' => false,
            'message' => 'Nao foi possivel preparar a pasta para a assinatura.',
        ];
    }

    $original = pathinfo($file['name'] ?? 'assinatura', PATHINFO_FILENAME);
    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $original));
    $slug = trim($slug, '-');
    if ($slug === '') {
        $slug = 'assinatura';
    }

    try {
        $filename = $slug . '-' . bin2hex(random_bytes(6)) . '.png';
    } catch (Throwable $exception) {
        $filename = $slug . '-' . uniqid('', true) . '.png';
    }

    $destination = $uploadDir . '/' . $filename;
    if (!move_uploaded_file($tmpName, $destination)) {
        return [
            'success' => false,
            'message' => 'Nao foi possivel salvar a assinatura enviada.',
        ];
    }

    return [
        'success' => true,
        'path' => 'uploads/signatures/' . $filename,
    ];
}

function store_user_photo(array $file): array
{
    $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($error !== UPLOAD_ERR_OK) {
        $messages = [
            UPLOAD_ERR_INI_SIZE => 'A foto excede o tamanho permitido.',
            UPLOAD_ERR_FORM_SIZE => 'A foto excede o tamanho permitido.',
            UPLOAD_ERR_PARTIAL => 'Upload da foto foi concluido parcialmente. Tente novamente.',
            UPLOAD_ERR_NO_FILE => 'Nenhuma foto enviada.',
        ];

        return [
            'success' => false,
            'message' => $messages[$error] ?? 'Falha ao enviar a foto.',
        ];
    }

    $tmpName = $file['tmp_name'] ?? '';
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        return [
            'success' => false,
            'message' => 'Upload de foto invalido. Tente novamente.',
        ];
    }

    $maxSize = 2 * 1024 * 1024;
    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > $maxSize) {
        return [
            'success' => false,
            'message' => 'A foto deve ter ate 2 MB nos formatos JPG ou PNG.',
        ];
    }

    $imageInfo = @getimagesize($tmpName);
    if ($imageInfo === false) {
        return [
            'success' => false,
            'message' => 'Envie uma imagem JPG ou PNG válida.',
        ];
    }

    $imageType = $imageInfo[2] ?? null;
    if (!in_array($imageType, [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
        return [
            'success' => false,
            'message' => 'A foto precisa estar em JPG ou PNG.',
        ];
    }

    $width = (int) ($imageInfo[0] ?? 0);
    $height = (int) ($imageInfo[1] ?? 0);
    if ($width <= 0 || $height <= 0) {
        return [
            'success' => false,
            'message' => 'Dimensoes da foto invalidas.',
        ];
    }

    $extension = $imageType === IMAGETYPE_PNG ? 'png' : 'jpg';
    $uploadDir = __DIR__ . '/public/uploads/user_photos';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
        return [
            'success' => false,
            'message' => 'Nao foi possivel preparar a pasta para a foto.',
        ];
    }

    $original = pathinfo($file['name'] ?? 'foto', PATHINFO_FILENAME);
    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $original));
    $slug = trim($slug, '-');
    if ($slug === '') {
        $slug = 'foto';
    }

    try {
        $filename = $slug . '-' . bin2hex(random_bytes(8)) . '.' . $extension;
    } catch (Throwable $exception) {
        $filename = $slug . '-' . uniqid('', true) . '.' . $extension;
    }

    $destination = $uploadDir . '/' . $filename;
    $gdAvailable = extension_loaded('gd') && function_exists('imagecreatetruecolor');
    if ($imageType === IMAGETYPE_JPEG && !function_exists('imagecreatefromjpeg')) {
        $gdAvailable = false;
    }
    if ($imageType === IMAGETYPE_PNG && !function_exists('imagecreatefrompng')) {
        $gdAvailable = false;
    }

    if (!$gdAvailable) {
        if (!move_uploaded_file($tmpName, $destination)) {
            return [
                'success' => false,
                'message' => 'Nao foi possivel salvar a foto enviada.',
            ];
        }
        return [
            'success' => true,
            'path' => 'uploads/user_photos/' . $filename,
        ];
    }

    $sourceImage = $imageType === IMAGETYPE_PNG ? @imagecreatefrompng($tmpName) : @imagecreatefromjpeg($tmpName);
    if (!$sourceImage) {
        if (!move_uploaded_file($tmpName, $destination)) {
            return [
                'success' => false,
                'message' => 'Nao foi possivel salvar a foto enviada.',
            ];
        }
        return [
            'success' => true,
            'path' => 'uploads/user_photos/' . $filename,
        ];
    }

    $targetWidth = 600;
    $targetHeight = 800;
    $targetRatio = 3 / 4;

    $ratio = $width / $height;
    if ($ratio > $targetRatio) {
        $cropHeight = $height;
        $cropWidth = (int) round($height * $targetRatio);
        $srcX = (int) (($width - $cropWidth) / 2);
        $srcY = 0;
    } else {
        $cropWidth = $width;
        $cropHeight = (int) round($width / $targetRatio);
        $srcX = 0;
        $srcY = (int) (($height - $cropHeight) / 2);
    }

    $destinationImage = imagecreatetruecolor($targetWidth, $targetHeight);
    if ($imageType === IMAGETYPE_PNG) {
        imagealphablending($destinationImage, false);
        imagesavealpha($destinationImage, true);
        $transparent = imagecolorallocatealpha($destinationImage, 255, 255, 255, 127);
        imagefill($destinationImage, 0, 0, $transparent);
    } else {
        $white = imagecolorallocate($destinationImage, 255, 255, 255);
        imagefill($destinationImage, 0, 0, $white);
    }

    imagecopyresampled(
        $destinationImage,
        $sourceImage,
        0,
        0,
        max(0, $srcX),
        max(0, $srcY),
        $targetWidth,
        $targetHeight,
        max(1, $cropWidth),
        max(1, $cropHeight)
    );

    $saved = $imageType === IMAGETYPE_PNG
        ? imagepng($destinationImage, $destination)
        : imagejpeg($destinationImage, $destination, 90);

    imagedestroy($sourceImage);
    imagedestroy($destinationImage);

    if (!$saved) {
        return [
            'success' => false,
            'message' => 'Nao foi possivel salvar a foto enviada.',
        ];
    }

    return [
        'success' => true,
        'path' => 'uploads/user_photos/' . $filename,
    ];
}

function store_uploaded_video(array $file): array
{
    $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($error !== UPLOAD_ERR_OK) {
        $messages = [
            UPLOAD_ERR_INI_SIZE => 'Arquivo de video excede o tamanho maximo permitido.',
            UPLOAD_ERR_FORM_SIZE => 'Arquivo de video excede o tamanho maximo permitido.',
            UPLOAD_ERR_PARTIAL => 'Upload do video foi concluido parcialmente. Tente novamente.',
            UPLOAD_ERR_NO_FILE => 'Nenhum arquivo de video enviado.',
        ];

        return [
            'success' => false,
            'message' => $messages[$error] ?? 'Falha ao enviar o arquivo de video.',
        ];
    }

    $tmpName = $file['tmp_name'] ?? '';
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        return [
            'success' => false,
            'message' => 'Upload de video invalido. Tente novamente.',
        ];
    }

    $maxSize = 200 * 1024 * 1024; // 200 MB
    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > $maxSize) {
        return [
            'success' => false,
            'message' => 'O video deve ter ate 200 MB nos formatos MP4, WEBM ou OGG.',
        ];
    }

    $allowedExtensions = ['mp4', 'webm', 'ogg', 'ogv', 'm4v'];
    $allowedMime = ['video/mp4', 'video/webm', 'video/ogg', 'video/ogv', 'video/x-m4v', 'application/octet-stream'];

    $extension = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions, true)) {
        return [
            'success' => false,
            'message' => 'Formato de video invalido. Utilize MP4, WEBM ou OGG.',
        ];
    }

    $mime = null;
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $detected = $finfo->file($tmpName);
        if ($detected !== false) {
            $mime = $detected;
        }
    }

    if ($mime !== null && !in_array($mime, $allowedMime, true)) {
        return [
            'success' => false,
            'message' => 'Formato de video invalido. Utilize MP4, WEBM ou OGG.',
        ];
    }

    $uploadDir = __DIR__ . '/public/uploads/videos';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
        return [
            'success' => false,
            'message' => 'Nao foi possivel preparar a pasta para o video.',
        ];
    }

    $original = pathinfo($file['name'] ?? 'video', PATHINFO_FILENAME);
    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $original));
    $slug = trim($slug, '-');
    if ($slug === '') {
        $slug = 'video';
    }

    try {
        $filename = $slug . '-' . bin2hex(random_bytes(8)) . '.' . $extension;
    } catch (Throwable $exception) {
        $filename = $slug . '-' . uniqid('', true) . '.' . $extension;
    }

    $destination = $uploadDir . '/' . $filename;
    if (!move_uploaded_file($tmpName, $destination)) {
        return [
            'success' => false,
            'message' => 'Nao foi possivel salvar o video enviado.',
        ];
    }

    return [
        'success' => true,
        'path' => 'uploads/videos/' . $filename,
    ];
}

function store_uploaded_pdf(array $file): array
{
    $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($error !== UPLOAD_ERR_OK) {
        $messages = [
            UPLOAD_ERR_INI_SIZE => 'Arquivo PDF excede o tamanho maximo permitido.',
            UPLOAD_ERR_FORM_SIZE => 'Arquivo PDF excede o tamanho maximo permitido.',
            UPLOAD_ERR_PARTIAL => 'Upload do PDF foi concluido parcialmente. Tente novamente.',
            UPLOAD_ERR_NO_FILE => 'Nenhum arquivo PDF enviado.',
        ];

        return [
            'success' => false,
            'message' => $messages[$error] ?? 'Falha ao enviar o arquivo PDF.',
        ];
    }

    $tmpName = $file['tmp_name'] ?? '';
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        return [
            'success' => false,
            'message' => 'Upload de PDF invalido. Tente novamente.',
        ];
    }

    $maxSize = 10 * 1024 * 1024; // 10 MB
    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > $maxSize) {
        return [
            'success' => false,
            'message' => 'O PDF deve ter ate 10 MB.',
        ];
    }

    $mime = 'application/pdf';
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $detected = $finfo->file($tmpName);
        if ($detected !== false) {
            $mime = $detected;
        }
    }

    $allowedMime = ['application/pdf', 'application/x-pdf', 'application/octet-stream'];
    $extension = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    if ($extension !== 'pdf' || !in_array($mime, $allowedMime, true)) {
        return [
            'success' => false,
            'message' => 'Envie apenas arquivos no formato PDF.',
        ];
    }

    $uploadDir = __DIR__ . '/public/uploads/pdfs';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
        return [
            'success' => false,
            'message' => 'Nao foi possivel preparar a pasta para o PDF.',
        ];
    }

    $original = pathinfo($file['name'] ?? '', PATHINFO_FILENAME);
    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $original));
    $slug = trim($slug, '-');
    if ($slug === '') {
        $slug = 'material';
    }

    try {
        $filename = $slug . '-' . bin2hex(random_bytes(8)) . '.pdf';
    } catch (Throwable $exception) {
        $filename = $slug . '-' . uniqid('', true) . '.pdf';
    }

    $destination = $uploadDir . '/' . $filename;
    if (!move_uploaded_file($tmpName, $destination)) {
        return [
            'success' => false,
            'message' => 'Nao foi possivel salvar o PDF enviado.',
        ];
    }

    return [
        'success' => true,
        'path' => 'uploads/pdfs/' . $filename,
    ];
}

function delete_uploaded_file(?string $path): void
{
    if ($path === null || $path === '' || is_external_url($path)) {
        return;
    }

    if (str_contains($path, '..')) {
        return;
    }

    $absolute = __DIR__ . '/public/' . ltrim($path, '/');
    if (is_file($absolute)) {
        @unlink($absolute);
    }
}



function ensure_module_tables(DatabaseConnection $db): void
{
    static $initialized = false;

    if ($initialized) {
        return;
    }

    if (DB_DRIVER !== 'mysql') {
        $initialized = true;
        return;
    }

    $result = $db->query("SHOW COLUMNS FROM courses LIKE 'deadline'");
    if ($result->num_rows === 0) {
        $db->exec("ALTER TABLE courses ADD COLUMN deadline DATE DEFAULT NULL AFTER workload");
    }

    $result = $db->query("SHOW COLUMNS FROM courses LIKE 'certificate_validity_months'");
    if ($result->num_rows === 0) {
        $db->exec("ALTER TABLE courses ADD COLUMN certificate_validity_months INT UNSIGNED DEFAULT NULL AFTER deadline");
    }

    $result = $db->query("SHOW COLUMNS FROM courses LIKE 'completion_type'");
    if ($result->num_rows === 0) {
        $db->exec("ALTER TABLE courses ADD COLUMN completion_type VARCHAR(32) NOT NULL DEFAULT 'assessment' AFTER certificate_validity_months");
    }

    $result = $db->query("SHOW COLUMNS FROM users LIKE 'photo_path'");
    if ($result->num_rows === 0) {
        $db->exec("ALTER TABLE users ADD COLUMN photo_path VARCHAR(255) DEFAULT NULL AFTER role");
    }

    $columns = [
        'signature_name' => "ALTER TABLE users ADD COLUMN signature_name VARCHAR(160) DEFAULT NULL AFTER photo_path",
        'signature_title' => "ALTER TABLE users ADD COLUMN signature_title VARCHAR(160) DEFAULT NULL AFTER signature_name",
        'signature_path' => "ALTER TABLE users ADD COLUMN signature_path VARCHAR(255) DEFAULT NULL AFTER signature_title",
    ];

    foreach ($columns as $column => $statement) {
        $result = $db->query("SHOW COLUMNS FROM users LIKE '{$column}'");
        if ($result->num_rows === 0) {
            $db->exec($statement);
        }
    }

    $result = $db->query("SHOW COLUMNS FROM assessment_results LIKE 'attempts'");
    if ($result->num_rows === 0) {
        $db->exec("ALTER TABLE assessment_results ADD COLUMN attempts INT UNSIGNED NOT NULL DEFAULT 0 AFTER approved");
    }

    $result = $db->query("SHOW COLUMNS FROM course_modules LIKE 'video_url'");
    if ($result->num_rows === 0) {
        $db->exec("ALTER TABLE course_modules ADD COLUMN video_url VARCHAR(255) DEFAULT NULL AFTER description");
    }

    $result = $db->query("SHOW COLUMNS FROM course_modules LIKE 'pdf_url'");
    if ($result->num_rows === 0) {
        $db->exec("ALTER TABLE course_modules ADD COLUMN pdf_url VARCHAR(255) DEFAULT NULL AFTER video_url");
    }

    $queries = [
        'CREATE TABLE IF NOT EXISTS course_modules (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            course_id INT UNSIGNED NOT NULL,
            title VARCHAR(160) NOT NULL,
            description TEXT,
            video_url VARCHAR(255) DEFAULT NULL,
            pdf_url VARCHAR(255) DEFAULT NULL,
            position INT UNSIGNED NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_modules_course (course_id),
            KEY idx_modules_order (course_id, position),
            CONSTRAINT fk_modules_course FOREIGN KEY (course_id) REFERENCES courses (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'CREATE TABLE IF NOT EXISTS module_questions (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            module_id INT UNSIGNED NOT NULL,
            prompt TEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_module_questions_module (module_id),
            CONSTRAINT fk_module_questions_module FOREIGN KEY (module_id) REFERENCES course_modules (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'CREATE TABLE IF NOT EXISTS module_question_options (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            question_id INT UNSIGNED NOT NULL,
            option_text TEXT NOT NULL,
            is_correct TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY idx_module_options_question (question_id),
            CONSTRAINT fk_module_options_question FOREIGN KEY (question_id) REFERENCES module_questions (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'CREATE TABLE IF NOT EXISTS module_topics (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            module_id INT UNSIGNED NOT NULL,
            title VARCHAR(160) NOT NULL,
            description TEXT,
            video_url VARCHAR(255) DEFAULT NULL,
            pdf_url VARCHAR(255) DEFAULT NULL,
            position INT UNSIGNED NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_topics_module (module_id),
            KEY idx_topics_order (module_id, position),
            CONSTRAINT fk_topics_module FOREIGN KEY (module_id) REFERENCES course_modules (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'CREATE TABLE IF NOT EXISTS module_topic_progress (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            module_topic_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            completed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_topic_user (module_topic_id, user_id),
            KEY idx_progress_user (user_id),
            CONSTRAINT fk_progress_topic FOREIGN KEY (module_topic_id) REFERENCES module_topics (id) ON DELETE CASCADE,
            CONSTRAINT fk_progress_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'CREATE TABLE IF NOT EXISTS module_results (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            module_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            score DECIMAL(4,1) NOT NULL,
            approved TINYINT(1) NOT NULL DEFAULT 0,
            attempts INT UNSIGNED NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_module_user (module_id, user_id),
            KEY idx_module_results_user (user_id),
            CONSTRAINT fk_module_results_module FOREIGN KEY (module_id) REFERENCES course_modules (id) ON DELETE CASCADE,
            CONSTRAINT fk_module_results_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'CREATE TABLE IF NOT EXISTS module_answers (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            result_id INT UNSIGNED NOT NULL,
            question_id INT UNSIGNED NOT NULL,
            option_id INT UNSIGNED NOT NULL,
            is_correct TINYINT(1) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_module_answers_result (result_id),
            KEY idx_module_answers_question (question_id),
            CONSTRAINT fk_module_answers_result FOREIGN KEY (result_id) REFERENCES module_results (id) ON DELETE CASCADE,
            CONSTRAINT fk_module_answers_question FOREIGN KEY (question_id) REFERENCES module_questions (id) ON DELETE CASCADE,
            CONSTRAINT fk_module_answers_option FOREIGN KEY (option_id) REFERENCES module_question_options (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    ];

    foreach ($queries as $sql) {
        $db->exec($sql);
    }

    $initialized = true;
}


function ensure_default_admin(DatabaseConnection $db): void
{
    static $checked = false;

    if ($checked) {
        return;
    }

    $email = trim(DEFAULT_ADMIN_EMAIL);
    if ($email === '') {
        $checked = true;
        return;
    }

    $stmt = $db->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_assoc();
        if (!$exists) {
            $name = DEFAULT_ADMIN_NAME;
            $password = DEFAULT_ADMIN_PASSWORD;
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $role = 'admin';
            $stmtInsert = $db->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)');
            if ($stmtInsert) {
                $stmtInsert->bind_param('ssss', $name, $email, $hash, $role);
                $stmtInsert->execute();
                $stmtInsert->close();
            }
        }
        $stmt->close();
    }

    $checked = true;
}
