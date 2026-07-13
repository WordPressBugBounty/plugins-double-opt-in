<?php
/**
 * File-storage service — pending-area lifecycle for opt-in uploads.
 *
 * @package Forge12\DoubleOptIn\Files
 * @since   4.3.0
 */

namespace Forge12\DoubleOptIn\Files;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Forge12\Shared\LoggerInterface;

/**
 * Centralised handling of file uploads attached to opt-in records.
 *
 * Pre-4.3 file uploads were copied (not moved) into PHP's
 * upload_tmp_dir with random names. The OS would eventually clean
 * them up — unpredictable. Plus, integrations had no consistent
 * way to hand the file off to their form system on confirmation.
 *
 * This service centralises the storage location and the
 * delete-by-paths primitive. The hand-off itself stays per-
 * integration (each form system has its own attachment-storage
 * conventions) — see AbstractFormIntegration::handOffFilesToFormSystem.
 *
 * Lifecycle (single source of truth):
 *
 *   submit  ─►  store($files)        moves to pending/
 *                                    paths saved in OptIn::files
 *   confirm ─►  hand-off succeeds    integration's own DB has the file
 *               deletePaths($paths)  pending/{file} unlinked
 *   delete  ─►  deletePaths($paths)  cascade-delete on OptIn removal
 *
 * Pending dir layout (FLAT — no per-hash subdir):
 *   wp-content/uploads/f12-doi/
 *     ├── .htaccess         deny from all (Apache)
 *     ├── index.php         empty file (universal blocker)
 *     └── pending/
 *         ├── .htaccess
 *         ├── index.php
 *         └── {32-hex}.{ext}    files with random non-guessable names
 *
 * Why flat instead of pending/{hash}/: at storeFiles() call-time
 * (line 259 of AbstractFormIntegration::processSubmission), the OptIn
 * record has not been constructed yet — we have no hash. Re-shuffling
 * that flow is invasive in a security-critical code path. Flat layout
 * is sufficient: random hex names are non-guessable + not web-served
 * (deny-from-all).
 */
class FileStorage {

	/**
	 * Subdirectory under wp-content/uploads/ for opt-in file storage.
	 * NOT web-served: the .htaccess + index.php blockers below ensure
	 * direct URL access returns 403 / empty.
	 */
	public const SUBDIR = 'f12-doi';

	/**
	 * Phase-subdir under SUBDIR for files awaiting confirmation +
	 * hand-off. Files removed from here on either:
	 *   - successful confirm + hand-off (immediate)
	 *   - OptIn cascade-delete (cron expiry / manual REST / hash)
	 */
	public const PENDING = 'pending';

	/**
	 * Allowed MIME types for uploaded opt-in files.
	 *
	 * Extension via the `f12_cf7_doubleoptin_allowed_mime_types` filter.
	 * Anything not on this list is rejected at store() time and never
	 * reaches the pending dir — defence in depth against the form
	 * system's own validation drifting.
	 */
	private const ALLOWED_MIME_TYPES = array(
		'jpg'  => 'image/jpeg',
		'jpeg' => 'image/jpeg',
		'png'  => 'image/png',
		'gif'  => 'image/gif',
		'webp' => 'image/webp',
		'pdf'  => 'application/pdf',
		'doc'  => 'application/msword',
		'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
		'txt'  => 'text/plain',
		'csv'  => 'text/csv',
	);

	private LoggerInterface $logger;

	public function __construct( LoggerInterface $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Move uploaded files into the pending dir under random hex names.
	 *
	 * Each file is MIME-validated against the allowlist. Invalid files
	 * are skipped + logged at warning level. The returned array has
	 * exactly the paths that were actually stored — caller persists
	 * these in OptIn::files.
	 *
	 * Accepts either a flat `[path, path, ...]` array or a nested
	 * `[fieldName => path-or-paths]` map (Avada/CF7 fields can carry
	 * multiple files).
	 *
	 * @param array $files Either flat list of source paths, or
	 *                     [fieldName => path|paths] map.
	 *
	 * @return array<int,string> Final paths in pending/. Empty if none stored.
	 */
	public function store( array $files ): array {
		$stored = array();

		if ( empty( $files ) ) {
			return $stored;
		}

		$pendingDir = $this->ensureSecureDir();
		if ( $pendingDir === '' ) {
			$this->logger->error(
				'Aborting file storage: pending dir not writable',
				array(
					'plugin' => 'double-opt-in',
				)
			);
			return $stored;
		}

		$allowedMimes = apply_filters(
			'f12_cf7_doubleoptin_allowed_mime_types',
			self::ALLOWED_MIME_TYPES
		);

		foreach ( $files as $entry ) {
			// Tolerant of nested (fieldName => path|paths) shapes.
			$paths = is_array( $entry ) ? $entry : array( $entry );

			foreach ( $paths as $sourcePath ) {
				if ( ! is_string( $sourcePath ) || $sourcePath === '' || ! is_file( $sourcePath ) ) {
					continue;
				}

				$movedPath = $this->moveOneFile( $sourcePath, $pendingDir, $allowedMimes );
				if ( $movedPath !== null ) {
					$stored[] = $movedPath;
				}
			}
		}

		return $stored;
	}

	/**
	 * Unlink every path that lives under the pending dir. Paths
	 * outside it are silently rejected — defence-in-depth against
	 * a future caller passing tainted strings into a delete primitive.
	 *
	 * Idempotent: missing files are not an error (could already have
	 * been deleted by hand-off, or removed by an admin manually).
	 *
	 * @param array<int,string> $paths Paths to unlink.
	 */
	public function deletePaths( array $paths ): void {
		if ( empty( $paths ) ) {
			return;
		}

		$pendingDir = $this->getPendingDirRealPath();
		if ( $pendingDir === '' ) {
			return; // dir doesn't exist yet → nothing to delete
		}

		foreach ( $paths as $path ) {
			if ( ! is_string( $path ) || $path === '' ) {
				continue;
			}

			// Path-traversal defense. realpath() returns the canonical
			// path with all `..` resolved. If the result doesn't begin
			// with our pending dir, the path is rejected — no matter
			// what the caller passed in.
			$realPath = @realpath( $path );
			if ( $realPath === false ) {
				continue; // file already gone — idempotent
			}

			if ( ! str_starts_with( $realPath, $pendingDir ) ) {
				$this->logger->error(
					'Refusing to unlink path outside pending dir',
					array(
						'plugin'      => 'double-opt-in',
						'path'        => $path,
						'real_path'   => $realPath,
						'pending_dir' => $pendingDir,
					)
				);
				continue;
			}

			if ( @unlink( $realPath ) ) {
				$this->logger->debug(
					'Unlinked file from pending',
					array(
						'plugin' => 'double-opt-in',
						'path'   => $realPath,
					)
				);
			} else {
				$this->logger->warning(
					'Failed to unlink file (will retry on next cleanup)',
					array(
						'plugin' => 'double-opt-in',
						'path'   => $realPath,
					)
				);
			}
		}
	}

	/**
	 * Ensure the pending dir exists and is locked down against direct
	 * web access. Returns the absolute path on success, '' on failure.
	 *
	 * Idempotent — safe to call on every store() invocation.
	 */
	public function ensureSecureDir(): string {
		$uploads = wp_upload_dir();
		if ( empty( $uploads['basedir'] ) ) {
			return '';
		}

		$base    = trailingslashit( $uploads['basedir'] ) . self::SUBDIR;
		$pending = trailingslashit( $base ) . self::PENDING;

		// Two nested dirs to secure. wp_mkdir_p is recursive +
		// idempotent — safe to call repeatedly.
		if ( ! file_exists( $pending ) ) {
			if ( ! wp_mkdir_p( $pending ) ) {
				return '';
			}
		}

		// Drop blockers in BOTH dirs (base + pending). Belt and braces:
		// shared hosts vary in which file-types the webserver serves.
		$this->writeBlockers( $base );
		$this->writeBlockers( $pending );

		// Return canonical path — used by deletePaths() for the
		// path-traversal check.
		$real = @realpath( $pending );
		return $real === false ? '' : $real;
	}

	/**
	 * Single-file move with MIME validation + non-guessable rename.
	 * Returns the new path or null on rejection/failure.
	 *
	 * @param string                $source       Source path (from $_FILES tmp).
	 * @param string                $pendingDir   Absolute pending dir path.
	 * @param array<string, string> $allowedMimes ext => mime allowlist.
	 */
	private function moveOneFile( string $source, string $pendingDir, array $allowedMimes ): ?string {
		$check = wp_check_filetype_and_ext( $source, wp_basename( $source ), $allowedMimes );

		if ( empty( $check['type'] ) || empty( $check['ext'] ) ) {
			$this->logger->warning(
				'File rejected: MIME type not on allowlist',
				array(
					'plugin'  => 'double-opt-in',
					'source'  => $source,
				)
			);
			return null;
		}

		$newName = bin2hex( random_bytes( 16 ) ) . '.' . $check['ext'];
		$dest    = trailingslashit( $pendingDir ) . $newName;

		// Prefer move (rename) over copy — rename is atomic + faster +
		// doesn't double-store. Falls back to copy+unlink for cross-
		// device sources (PHP's tmp dir on a different filesystem
		// than uploads/ — common in containerised hosts).
		if ( @rename( $source, $dest ) ) {
			return $dest;
		}

		if ( @copy( $source, $dest ) ) {
			@unlink( $source );
			return $dest;
		}

		$this->logger->error(
			'Failed to move file into pending dir',
			array(
				'plugin' => 'double-opt-in',
				'source' => $source,
				'dest'   => $dest,
			)
		);
		return null;
	}

	/**
	 * Write .htaccess + index.php into the given dir. Idempotent.
	 */
	private function writeBlockers( string $dir ): void {
		$htaccess = trailingslashit( $dir ) . '.htaccess';
		$index    = trailingslashit( $dir ) . 'index.php';

		if ( ! file_exists( $htaccess ) ) {
			@file_put_contents(
				$htaccess,
				"# Forge12 DOI — never serve from this dir directly\n" .
				"<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n" .
				"<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n"
			);
		}

		if ( ! file_exists( $index ) ) {
			// Standard WP-style empty PHP file — silent in case the
			// host's webserver serves index.php for directory requests.
			@file_put_contents( $index, "<?php\n// Silence is golden.\n" );
		}
	}

	/**
	 * Canonical absolute path of the pending dir, or '' if not yet
	 * created. Used by deletePaths for the path-traversal check.
	 */
	private function getPendingDirRealPath(): string {
		$uploads = wp_upload_dir();
		if ( empty( $uploads['basedir'] ) ) {
			return '';
		}
		$pending = trailingslashit( $uploads['basedir'] ) . self::SUBDIR . '/' . self::PENDING;

		$real = @realpath( $pending );
		return $real === false ? '' : $real;
	}
}
