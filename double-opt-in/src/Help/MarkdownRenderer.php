<?php
/**
 * Minimal Markdown to HTML renderer for the bundled help articles.
 *
 * Supports exactly what the articles use: headings, paragraphs, bullet and
 * numbered lists, tables, fenced code, block quotes, rules, images, links,
 * bold, italic and inline code. Everything else is shown as plain text.
 *
 * The source is HTML-escaped first and formatted afterwards, so raw HTML in
 * an article can never reach the page. Image sources go through a resolver
 * that decides which paths are allowed.
 *
 * @package Forge12\DoubleOptIn\Help
 * @since   5.11.0
 */

declare( strict_types=1 );

namespace Forge12\DoubleOptIn\Help;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MarkdownRenderer {

	/** @var callable|null fn( string $src ): string — empty string drops the image */
	private $imageResolver;

	/** @var string[] */
	private $codeSpans = array();

	public function __construct( ?callable $imageResolver = null ) {
		$this->imageResolver = $imageResolver;
	}

	/**
	 * First level-1 heading of the source, or an empty string.
	 */
	public static function extractTitle( string $markdown ): string {
		if ( preg_match( '/^#\s+(.+?)\s*#*\s*$/m', $markdown, $m ) ) {
			return trim( preg_replace( '/[`*_]/', '', $m[1] ) ?? $m[1] );
		}
		return '';
	}

	public function render( string $markdown ): string {
		$lines = preg_split( '/\r\n|\r|\n/', $markdown );
		if ( false === $lines ) {
			return '';
		}

		/** @var string[] $html */
		$html = array();
		/** @var string[] $paragraph */
		$paragraph = array();
		$count     = count( $lines );
		$i         = 0;

		$flush = function () use ( &$paragraph, &$html ): void {
			if ( $paragraph !== array() ) {
				$html[]    = '<p>' . $this->inline( implode( ' ', $paragraph ) ) . '</p>';
				$paragraph = array();
			}
		};

		while ( $i < $count ) {
			$line = $lines[ $i ];

			if ( trim( $line ) === '' ) {
				$flush();
				++$i;
				continue;
			}

			// Fenced code.
			if ( preg_match( '/^```/', $line ) ) {
				$flush();
				$code = array();
				++$i;
				while ( $i < $count && ! preg_match( '/^```/', $lines[ $i ] ) ) {
					$code[] = $lines[ $i ];
					++$i;
				}
				++$i;
				$html[] = '<pre><code>' . $this->escape( implode( "\n", $code ) ) . '</code></pre>';
				continue;
			}

			// Heading.
			if ( preg_match( '/^(#{1,6})\s+(.*?)\s*#*\s*$/', $line, $m ) ) {
				$flush();
				$level  = strlen( $m[1] );
				$html[] = '<h' . $level . '>' . $this->inline( $m[2] ) . '</h' . $level . '>';
				++$i;
				continue;
			}

			// Rule.
			if ( preg_match( '/^\s*(-{3,}|\*{3,})\s*$/', $line ) ) {
				$flush();
				$html[] = '<hr />';
				++$i;
				continue;
			}

			// Table: header row followed by a separator row.
			if ( $i + 1 < $count && strpos( $line, '|' ) !== false && $this->isTableSeparator( $lines[ $i + 1 ] ) ) {
				$flush();
				$header = $this->splitRow( $line );
				$rows   = array();
				$i     += 2;
				while ( $i < $count && trim( $lines[ $i ] ) !== '' && strpos( $lines[ $i ], '|' ) !== false ) {
					$rows[] = $this->splitRow( $lines[ $i ] );
					++$i;
				}
				$html[] = $this->table( $header, $rows );
				continue;
			}

			// Block quote.
			if ( preg_match( '/^>\s?/', $line ) ) {
				$flush();
				$quote = array();
				while ( $i < $count && preg_match( '/^>\s?(.*)$/', $lines[ $i ], $m ) ) {
					$quote[] = $m[1];
					++$i;
				}
				$html[] = '<blockquote><p>' . $this->inline( implode( ' ', $quote ) ) . '</p></blockquote>';
				continue;
			}

			// Lists (flat; an indented continuation line extends the item).
			if ( preg_match( '/^\s*([-*]|\d+\.)\s+(.*)$/', $line, $m ) ) {
				$flush();
				$ordered = ctype_digit( rtrim( $m[1], '.' ) );
				$items   = array();
				while ( $i < $count ) {
					if ( preg_match( '/^\s*([-*]|\d+\.)\s+(.*)$/', $lines[ $i ], $im ) ) {
						$items[] = $im[2];
						++$i;
					} elseif ( trim( $lines[ $i ] ) !== '' && preg_match( '/^\s{2,}\S/', $lines[ $i ] ) && $items !== array() ) {
						$items[ count( $items ) - 1 ] .= ' ' . trim( $lines[ $i ] );
						++$i;
					} else {
						break;
					}
				}
				$tag    = $ordered ? 'ol' : 'ul';
				$html[] = '<' . $tag . '><li>' . implode( '</li><li>', array_map( array( $this, 'inline' ), $items ) ) . '</li></' . $tag . '>';
				continue;
			}

			$paragraph[] = trim( $line );
			++$i;
		}

		$flush();

		return implode( "\n", $html );
	}

	private function escape( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	}

	private function isTableSeparator( string $line ): bool {
		return (bool) preg_match( '/^\s*\|?\s*:?-{2,}:?\s*(\|\s*:?-{2,}:?\s*)*\|?\s*$/', $line ) && strpos( $line, '-' ) !== false;
	}

	/**
	 * @return string[]
	 */
	private function splitRow( string $line ): array {
		$line = trim( $line );
		$line = preg_replace( '/^\||\|$/', '', $line ) ?? $line;
		// A pipe inside inline code stays part of the cell.
		$line  = preg_replace_callback(
			'/`[^`]*`/',
			static function ( array $m ): string {
				return str_replace( '|', "\x01", $m[0] );
			},
			$line
		) ?? $line;
		$cells = array_map(
			static function ( string $cell ): string {
				return trim( str_replace( "\x01", '|', $cell ) );
			},
			explode( '|', $line )
		);
		return $cells;
	}

	/**
	 * @param string[]   $header
	 * @param string[][] $rows
	 */
	private function table( array $header, array $rows ): string {
		$out = '<table><thead><tr>';
		foreach ( $header as $cell ) {
			$out .= '<th>' . $this->inline( $cell ) . '</th>';
		}
		$out .= '</tr></thead><tbody>';
		foreach ( $rows as $row ) {
			$out .= '<tr>';
			foreach ( $row as $cell ) {
				$out .= '<td>' . $this->inline( $cell ) . '</td>';
			}
			$out .= '</tr>';
		}
		return $out . '</tbody></table>';
	}

	private function inline( string $text ): string {
		$this->codeSpans = array();

		// Code spans are lifted out first so their content is not formatted.
		$text = preg_replace_callback(
			'/`([^`]+)`/',
			function ( array $m ): string {
				$this->codeSpans[] = '<code>' . $this->escape( $m[1] ) . '</code>';
				return "\x02" . ( count( $this->codeSpans ) - 1 ) . "\x03";
			},
			$text
		) ?? $text;

		$text = $this->escape( $text );

		// Images before links: ![alt](src).
		$text = preg_replace_callback(
			'/!\[([^\]]*)\]\(([^)\s]+)\)/',
			function ( array $m ): string {
				$src = $this->resolveImage( html_entity_decode( $m[2], ENT_QUOTES, 'UTF-8' ) );
				if ( '' === $src ) {
					return '';
				}
				return '<img src="' . $this->escape( $src ) . '" alt="' . $m[1] . '" loading="lazy" />';
			},
			$text
		) ?? $text;

		$text = preg_replace_callback(
			'/\[([^\]]+)\]\(([^)\s]+)\)/',
			function ( array $m ): string {
				$url = html_entity_decode( $m[2], ENT_QUOTES, 'UTF-8' );
				if ( ! preg_match( '#^(https://|http://|mailto:|\#)#i', $url ) ) {
					return $m[1];
				}
				$external = preg_match( '#^https?://#i', $url ) ? ' target="_blank" rel="noopener noreferrer"' : '';
				return '<a href="' . $this->escape( $url ) . '"' . $external . '>' . $m[1] . '</a>';
			},
			$text
		) ?? $text;

		$text = preg_replace( '/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $text ) ?? $text;
		$text = preg_replace( '/(?<![\w*])\*(?!\s)(.+?)(?<!\s)\*(?![\w*])/s', '<em>$1</em>', $text ) ?? $text;

		return (string) preg_replace_callback(
			'/\x02(\d+)\x03/',
			function ( array $m ): string {
				return $this->codeSpans[ (int) $m[1] ] ?? '';
			},
			$text
		);
	}

	private function resolveImage( string $src ): string {
		if ( null === $this->imageResolver ) {
			return '';
		}
		return (string) call_user_func( $this->imageResolver, $src );
	}
}
