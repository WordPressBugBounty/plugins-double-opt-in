<?php
namespace Forge12\Shared;

// Sicherstellen, dass Interface existiert
if (! interface_exists('Forge12\Shared\LoggerInterface')) {
    require_once __DIR__ . '/logger.interface.php';
}

if (!defined('F12_DEBUG')) {
    define('F12_DEBUG', false);
}

if (!defined('F12_DEBUG_LOG_LEVEL')) {
    define('F12_DEBUG_LOG_LEVEL', 200);
}

if (!class_exists('Forge12\Shared\Logger')) {
	class Logger implements LoggerInterface
	{
		private static $instance;
		private $log_file;
		private $log_level;
		private $log_dir;

		const DEBUG    = 100;
		const INFO     = 200;
		const NOTICE   = 250;
		const WARNING  = 300;
		const ERROR    = 400;
		const CRITICAL = 500;

		private function __construct()
		{
			// Debug aus → Logger ist komplett deaktiviert
			if (!defined('F12_DEBUG') || !F12_DEBUG) {
				return;
			}

			// Sicher WordPress Upload-DIR holen
			$upload_dir = wp_upload_dir();

			if (empty($upload_dir['basedir']) || !is_string($upload_dir['basedir'])) {
				$base = WP_CONTENT_DIR . '/uploads';
			} else {
				$base = $upload_dir['basedir'];
			}

			// Pfad normalisieren
			$base = $this->normalizePath($base);

			// Log-Ordner festlegen
			$this->log_dir = $this->normalizePath($base . '/f12-logs');

			// Falls Pfad relativ ist → absolut machen
			$this->log_dir = $this->ensureAbsolutePath($this->log_dir);

			// Sicherstellen, dass Verzeichnis existiert
			if (!is_dir($this->log_dir)) {
				wp_mkdir_p($this->log_dir);
			}

			// The directory is inside uploads/ and therefore web-reachable.
			// A guessable name (plugins-<date>.log) was downloadable by
			// anyone on a live site. Deny rules first, unguessable file
			// names second — the latter also holds on servers that ignore
			// .htaccess (nginx).
			$secret = self::fileSecret();
			self::protectDirectory($this->log_dir);
			self::migrateGuessableFiles($this->log_dir, $secret);

			// Logdatei definieren
			$this->log_file = $this->normalizePath(
				self::logFilePath($this->log_dir, date('Y-m-d'), $secret)
			);

			// Logdatei 100% absolut sicher machen
			$this->log_file = $this->ensureAbsolutePath($this->log_file);

			// Log-Level
			$this->log_level = defined('F12_DEBUG_LOG_LEVEL')
				? F12_DEBUG_LOG_LEVEL
				: self::INFO;
		}

		/**
		 * Daily log file: plugins-<date>-<secret>.log. The glob in
		 * cleanupOldLogs() (plugins-*.log*) matches both shapes.
		 */
		public static function logFilePath(string $dir, string $date, string $secret): string
		{
			return rtrim($dir, '/\\') . '/plugins-' . $date . ($secret !== '' ? '-' . $secret : '') . '.log';
		}

		/**
		 * A per-site value nobody outside the server knows. Derived from
		 * the wp-config keys when they are set (no database access — the
		 * logger is constructed while plugins load); otherwise a random
		 * value kept in an option.
		 */
		public static function fileSecret(): string
		{
			$material = '';
			foreach (array('AUTH_KEY', 'AUTH_SALT', 'SECURE_AUTH_KEY') as $constant) {
				if (defined($constant)) {
					$value = (string) constant($constant);
					if ($value !== '' && $value !== 'put your unique phrase here') {
						$material .= $value;
					}
				}
			}

			if ($material === '' && function_exists('get_option')) {
				$stored = get_option('f12_logs_file_secret');
				if (!is_string($stored) || strlen($stored) < 32) {
					$stored = bin2hex(random_bytes(16));
					if (function_exists('update_option')) {
						update_option('f12_logs_file_secret', $stored, false);
					}
				}
				$material = $stored;
			}

			return $material === '' ? '' : substr(hash_hmac('sha256', 'f12-logs', $material), 0, 20);
		}

		/**
		 * Deny web access to the log directory: Apache 2.2 and 2.4
		 * (.htaccess), IIS (web.config), and no directory listing
		 * (index.php). Existing files are left alone, so an admin's own
		 * rules are never overwritten.
		 */
		public static function protectDirectory(string $dir): void
		{
			if (!is_dir($dir) || !is_writable($dir)) {
				return;
			}

			$files = array(
				'.htaccess'  => "# Written by the Forge12 logger. Log files must not be downloadable.\n"
					. "<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n"
					. "<IfModule !mod_authz_core.c>\n\tOrder allow,deny\n\tDeny from all\n</IfModule>\n",
				'web.config' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration>\n"
					. "\t<system.webServer>\n\t\t<authorization>\n\t\t\t<deny users=\"*\" />\n\t\t</authorization>\n"
					. "\t</system.webServer>\n</configuration>\n",
				'index.php'  => "<?php\n// Silence is golden.\n",
			);

			foreach ($files as $name => $content) {
				$path = $dir . '/' . $name;
				if (!file_exists($path)) {
					@file_put_contents($path, $content);
				}
			}
		}

		/**
		 * Rename log files from the guessable scheme (plugins-<date>.log,
		 * plugins-<date>.log.<n>) so their known URLs stop working.
		 */
		public static function migrateGuessableFiles(string $dir, string $secret): void
		{
			if ($secret === '' || !is_dir($dir)) {
				return;
			}

			foreach ((array) glob($dir . '/plugins-*.log*') as $file) {
				if (!is_string($file)) {
					continue;
				}
				if (!preg_match('/^plugins-(\d{4}-\d{2}-\d{2})\.log(\.\d+)?$/', basename($file), $m)) {
					continue;
				}
				$target = self::logFilePath($dir, $m[1], $secret) . ($m[2] ?? '');
				if (file_exists($target)) {
					// Both exist (concurrent request): keep the content.
					@file_put_contents($target, (string) @file_get_contents($file), FILE_APPEND);
					@unlink($file);
				} else {
					@rename($file, $target);
				}
			}
		}

		public static function getInstance()
		{
			if (!self::$instance) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Pfade bereinigen (Windows + Linux)
		 */
		private function normalizePath(string $path): string
		{
			// Backslashes → Slashes
			$path = str_replace('\\', '/', $path);

			// Doppelte Slashes entfernen, außer nach C:
			$path = preg_replace('#(?<!:)/{2,}#', '/', $path);

			return rtrim($path, '/');
		}

		/**
		 * ABSOLUTEN Pfad erzwingen
		 */
		private function ensureAbsolutePath(string $path): string
		{
			$path = $this->normalizePath($path);

			// Linux absolute Pfade: /var/www/...
			if (substr($path, 0, 1) === '/') {
				return $path;
			}

			// Windows absolute Pfade: C:/xampp/...
			if (preg_match('#^[A-Za-z]:/#', $path)) {
				return $path;
			}

			// → Pfad ist relativ → ABSPATH davor hängen
			$absolute = $this->normalizePath(ABSPATH . '/' . $path);

			return $absolute;
		}


		private function sanitizeContext(array $context): array
		{
			foreach ($context as $key => $value) {
				if (in_array(strtolower($key), ['ip', 'user_ip'])) {
					$context[$key] = $this->mask_ip($value);
				}
				if (in_array(strtolower($key), ['email', 'user_email'])) {
					$context[$key] = $this->mask_email($value);
				}
				if (in_array(strtolower($key), ['password', 'pwd'])) {
					$context[$key] = $this->mask_password($value);
				}
			}
			return $context;
		}

		private function mask_password(string $password): string
		{
			return '********';
		}

		private function mask_email(string $email): string
		{
			if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
				return 'invalid';
			}
			[$user, $domain] = explode('@', $email, 2);
			$len = strlen($user);
			if ($len <= 2) {
				$maskedUser = substr($user, 0, 1) . '*';
			} else {
				$maskedUser = substr($user, 0, 1) . str_repeat('*', $len - 2) . substr($user, -1);
			}
			return $maskedUser . '@' . $domain;
		}

		private function mask_ip(string $ip): string
		{
			if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
				$parts = explode('.', $ip);
				$parts[3] = '0';
				return implode('.', $parts);
			}
			if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
				return substr($ip, 0, 20) . '::';
			}
			return 'unknown';
		}

		private function rotateLogs(int $maxSize = 52428800, int $maxFiles = 5): void
		{
			if (file_exists($this->log_file) && filesize($this->log_file) > $maxSize) {

				for ($i = $maxFiles - 1; $i >= 1; $i--) {
					$old = $this->log_file . '.' . $i;
					$new = $this->log_file . '.' . ($i + 1);
					if (file_exists($old)) {
						rename($old, $new);
					}
				}

				rename($this->log_file, $this->log_file . '.1');
			}
		}

		private function cleanupOldLogs(int $days = 7): void
		{
			foreach (glob($this->log_dir . '/plugins-*.log*') as $file) {
				if (filemtime($file) < strtotime("-{$days} days")) {
					@unlink($file);
				}
			}
		}

		private function writeLog($level, $levelName, $message, array $context = [])
		{
			if (!defined('F12_DEBUG') || !F12_DEBUG) {
				return;
			}

			if ($level < $this->log_level) {
				return;
			}

			// Vor jedem Schreiben absolut sicherstellen
			$this->log_file = $this->ensureAbsolutePath($this->log_file);

			// Cleanup & Rotation
			$this->cleanupOldLogs();
			$this->rotateLogs();

			$context = $this->sanitizeContext($context);

			$time   = date('Y-m-d H:i:s');
			$plugin = $context['plugin'] ?? 'unknown';

			$msg = sprintf(
				"[%s] [%s] [%s] %s %s\n",
				$time,
				strtoupper($levelName),
				$plugin,
				$message,
				$context ? json_encode($context) : ''
			);

			// Schreiben in absolut sicheren Pfad
			error_log($msg, 3, $this->log_file);
		}

		public function debug($message, array $context = []): void
		{
			if ( ! F12_DEBUG ) { return; }
			$this->writeLog(self::DEBUG, 'DEBUG', $message, $context);
		}

		public function info($message, array $context = []): void
		{
			if ( ! F12_DEBUG ) { return; }
			$this->writeLog(self::INFO, 'INFO', $message, $context);
		}

		public function error($message, array $context = []): void
		{
			if ( ! F12_DEBUG ) { return; }
			$this->writeLog(self::ERROR, 'ERROR', $message, $context);
		}

		public function warning($message, array $context = []): void
		{
			if ( ! F12_DEBUG ) { return; }
			$this->writeLog(self::WARNING, 'WARNING', $message, $context);
		}

		public function notice($message, array $context = []): void
		{
			if ( ! F12_DEBUG ) { return; }
			$this->writeLog(self::NOTICE, 'NOTICE', $message, $context);
		}

		public function critical($message, array $context = []): void
		{
			if ( ! F12_DEBUG ) { return; }
			$this->writeLog(self::CRITICAL, 'CRITICAL', $message, $context);
		}
	}
}
