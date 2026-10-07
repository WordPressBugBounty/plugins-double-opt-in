<?php
/**
 * Reads the bundled help articles from disk.
 *
 * Layout per source: `help/<lang>/<name>.md`, images under
 * `help/images/<lang>/`. Article ids are `<source>--<name>`; lookups go through
 * the list of files that exist, never through the id as a path, so a crafted
 * id cannot reach any other file.
 *
 * @package Forge12\DoubleOptIn\Help
 * @since   5.11.0
 */

declare( strict_types=1 );

namespace Forge12\DoubleOptIn\Help;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HelpRepository {

	public const LANGUAGES = array( 'de', 'en' );

	/** Core articles first, in reading order; anything else follows alphabetically. */
	private const CORE_ORDER = array( 'guide', 'troubleshooting', 'shortcodes' );

	/** @var HelpSource[] */
	private $sources;

	/** @var string 'de' or 'en' */
	private $language;

	/**
	 * @param HelpSource[] $sources
	 * @param string       $locale  WordPress locale, e.g. "de_DE_formal".
	 */
	public function __construct( array $sources, string $locale ) {
		$this->sources  = $sources;
		$this->language = self::languageFor( $locale );
	}

	public static function languageFor( string $locale ): string {
		return 0 === strpos( strtolower( $locale ), 'de' ) ? 'de' : 'en';
	}

	/**
	 * Table of contents: id, title, group, source name — no bodies.
	 *
	 * @return array<int, array{id: string, title: string, group: string, source: string}>
	 */
	public function listArticles(): array {
		$out = array();
		foreach ( $this->sources as $source ) {
			foreach ( $this->filesOf( $source ) as $name => $info ) {
				$title = MarkdownRenderer::extractTitle( $this->read( $info['path'] ) );
				$out[] = array(
					'id'     => $source->id . '--' . $name,
					'title'  => '' !== $title ? $title : $name,
					'group'  => $source->group,
					'source' => $source->name,
				);
			}
		}
		return $out;
	}

	/**
	 * @return array{id: string, title: string, group: string, source: string, html: string, language: string}|null
	 */
	public function getArticle( string $id ): ?array {
		foreach ( $this->sources as $source ) {
			foreach ( $this->filesOf( $source ) as $name => $info ) {
				if ( $source->id . '--' . $name !== $id ) {
					continue;
				}
				$markdown = $this->read( $info['path'] );
				$lang     = $info['language'];
				$base     = $source->url . 'images/' . $lang . '/';
				$pattern  = '#^images/' . preg_quote( $lang, '#' ) . '/([A-Za-z0-9._-]+\.(?:png|jpe?g|gif|webp))$#';
				$renderer = new MarkdownRenderer(
					static function ( string $src ) use ( $base, $pattern ): string {
						// Only `images/<lang>/<file>`; nothing that climbs out of that folder.
						if ( preg_match( $pattern, $src, $m ) && false === strpos( $m[1], '..' ) ) {
							return $base . $m[1];
						}
						return '';
					}
				);
				$title    = MarkdownRenderer::extractTitle( $markdown );
				return array(
					'id'       => $id,
					'title'    => '' !== $title ? $title : $name,
					'group'    => $source->group,
					'source'   => $source->name,
					'html'     => $renderer->render( $markdown ),
					'language' => $lang,
				);
			}
		}
		return null;
	}

	/**
	 * Markdown files of a source in the active language, falling back to the
	 * other one per file. Keys are file names without extension, ordered.
	 *
	 * @return array<string, array{path: string, language: string}>
	 */
	private function filesOf( HelpSource $source ): array {
		$order = array_values( array_unique( array_merge( array( $this->language ), self::LANGUAGES ) ) );
		$found = array();
		// Walk the fallback language first so the active one overwrites it.
		foreach ( array_reverse( $order ) as $lang ) {
			$files = glob( $source->dir . '/' . $lang . '/*.md' );
			foreach ( false === $files ? array() : $files as $file ) {
				$name = basename( $file, '.md' );
				if ( ! preg_match( '/^[a-z0-9-]+$/', $name ) ) {
					continue;
				}
				$found[ $name ] = array(
					'path'     => $file,
					'language' => $lang,
				);
			}
		}

		if ( HelpSource::GROUP_CORE === $source->group ) {
			uksort(
				$found,
				static function ( string $a, string $b ): int {
					$ia = array_search( $a, self::CORE_ORDER, true );
					$ib = array_search( $b, self::CORE_ORDER, true );
					$ia = false === $ia ? 99 : $ia;
					$ib = false === $ib ? 99 : $ib;
					return $ia === $ib ? strcmp( $a, $b ) : $ia <=> $ib;
				}
			);
		} else {
			ksort( $found );
		}
		return $found;
	}

	private function read( string $path ): string {
		$content = is_readable( $path ) ? file_get_contents( $path ) : false; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		return false === $content ? '' : $content;
	}
}
