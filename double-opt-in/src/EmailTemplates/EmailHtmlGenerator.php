<?php
/**
 * Email HTML Generator
 *
 * @package Forge12\DoubleOptIn\EmailTemplates
 * @since   4.0.0
 */

namespace Forge12\DoubleOptIn\EmailTemplates;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class EmailHtmlGenerator
 *
 * Converts block structure to email-compatible HTML.
 */
class EmailHtmlGenerator {

	/**
	 * Default global styles.
	 *
	 * @var array
	 */
	private array $defaultStyles = [
		'fontFamily'      => 'Arial, Helvetica, sans-serif',
		'fontSize'        => '16px',
		'lineHeight'      => '1.5',
		'backgroundColor' => '#f4f4f4',
		'contentWidth'    => '600',
		'primaryColor'    => '#0073aa',
		'textColor'       => '#333333',
		'linkColor'       => '#0073aa',
	];

	/**
	 * Current global styles.
	 *
	 * @var array
	 */
	private array $globalStyles = [];

	/**
	 * Block Registry for Pro checks.
	 *
	 * @var BlockRegistry|null
	 */
	private ?BlockRegistry $blockRegistry = null;

	/**
	 * Generate email HTML from blocks.
	 *
	 * @param array $blocks       Array of block data.
	 * @param array $globalStyles Optional. Global styles.
	 * @return string Generated HTML.
	 */
	public function generate( array $blocks, array $globalStyles = [] ): string {
		$this->globalStyles = wp_parse_args( $globalStyles, $this->defaultStyles );
		$this->blockRegistry = new BlockRegistry();

		$contentWidth = (int) $this->globalStyles['contentWidth'];
		$backgroundColor = esc_attr( $this->globalStyles['backgroundColor'] );
		$fontFamily = esc_attr( $this->globalStyles['fontFamily'] );
		$textColor = esc_attr( $this->globalStyles['textColor'] );

		$blocksHtml = $this->renderBlocks( $blocks );

		return <<<HTML
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="x-apple-disable-message-reformatting">
    <title></title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
    <style type="text/css">
        body { margin: 0; padding: 0; width: 100% !important; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table { border-collapse: collapse; mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { border: 0; outline: none; text-decoration: none; -ms-interpolation-mode: bicubic; }
        a { text-decoration: none; }
        @media only screen and (max-width: 620px) {
            .container { width: 100% !important; }
            .content { padding: 10px !important; }
            .column { width: 100% !important; display: block !important; }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: {$backgroundColor}; font-family: {$fontFamily}; color: {$textColor};">
    <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: {$backgroundColor};">
        <tr>
            <td align="center" style="padding: 20px 0;">
                <!--[if mso]>
                <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="{$contentWidth}">
                <tr>
                <td>
                <![endif]-->
                <table role="presentation" class="container" border="0" cellpadding="0" cellspacing="0" width="{$contentWidth}" style="max-width: {$contentWidth}px; width: 100%;">
                    {$blocksHtml}
                </table>
                <!--[if mso]>
                </td>
                </tr>
                </table>
                <![endif]-->
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
	}

	/**
	 * Render all blocks.
	 *
	 * @param array  $blocks        Array of blocks.
	 * @param string $parentBgColor Parent background color for inheritance.
	 * @return string Rendered HTML.
	 */
	private function renderBlocks( array $blocks, string $parentBgColor = '' ): string {
		$html = '';

		foreach ( $blocks as $block ) {
			$html .= $this->renderBlock( $block, $parentBgColor );
		}

		return $html;
	}

	/**
	 * Render a single block.
	 *
	 * @param array  $block         Block data.
	 * @param string $parentBgColor Parent background color for inheritance.
	 * @return string Rendered HTML.
	 */
	private function renderBlock( array $block, string $parentBgColor = '' ): string {
		$type = $block['type'] ?? '';
		$attributes = $block['attributes'] ?? [];
		$children = $block['children'] ?? [];
		$customCss = $attributes['customCss'] ?? '';

		$html = $this->renderBlockContent( $type, $attributes, $children, $parentBgColor );

		// Inject custom CSS as inline styles (Pro only)
		if ( ! empty( $customCss ) && $this->blockRegistry && $this->blockRegistry->isProActive() ) {
			$html = $this->injectCustomCss( $html, $customCss );
		}

		return $html;
	}

	/**
	 * Render block content by type.
	 *
	 * @param string $type          Block type.
	 * @param array  $attributes    Block attributes.
	 * @param array  $children      Child blocks.
	 * @param string $parentBgColor Parent background color for inheritance.
	 * @return string Rendered HTML.
	 */
	private function renderBlockContent( string $type, array $attributes, array $children, string $parentBgColor = '' ): string {
		switch ( $type ) {
			case 'email-wrapper':
				return $this->renderEmailWrapper( $attributes, $children );

			case 'row':
				return $this->renderRow( $attributes, $children, $parentBgColor );

			case 'columns-1':
			case 'columns-2':
			case 'columns-2-sidebar':
			case 'columns-3':
				return $this->renderColumns( $type, $attributes, $children, $parentBgColor );

			case 'header':
				return $this->renderHeader( $attributes );

			case 'heading':
				return $this->renderHeading( $attributes );

			case 'text':
				return $this->renderText( $attributes );

			case 'button':
				return $this->renderButton( $attributes );

			case 'image':
				return $this->renderImage( $attributes );

			case 'divider':
				return $this->renderDivider( $attributes );

			case 'spacer':
				return $this->renderSpacer( $attributes, $parentBgColor );

			case 'social-icons':
				return $this->renderSocialIcons( $attributes );

			case 'footer':
				return $this->renderFooter( $attributes );

			case 'placeholder-confirm-link':
				return $this->renderPlaceholder( '[doubleoptinlink]', $attributes );

			case 'placeholder-optout-link':
				return $this->renderPlaceholder( '[doubleoptoutlink]', $attributes );

			case 'placeholder-date':
				return $this->renderPlaceholder( '[doubleoptin_form_date]', $attributes );

			case 'placeholder-time':
				return $this->renderPlaceholder( '[doubleoptin_form_time]', $attributes );

			case 'placeholder-url':
				return $this->renderPlaceholder( '[doubleoptin_form_url]', $attributes );

			case 'placeholder-custom':
				$fieldName = $attributes['fieldName'] ?? 'field_name';
				return $this->renderPlaceholder( "[{$fieldName}]", $attributes );

			case 'conditional-content':
				return $this->renderConditionalContent( $attributes, $children, $parentBgColor );

			default:
				return '';
		}
	}

	/**
	 * Render email wrapper.
	 */
	private function renderEmailWrapper( array $attrs, array $children ): string {
		$bgColor = esc_attr( $attrs['backgroundColor'] ?? '#ffffff' );
		$padding = esc_attr( $attrs['padding'] ?? '20px' );

		$innerHtml = $this->renderBlocks( $children, $bgColor );

		return <<<HTML
<tr>
    <td style="background-color: {$bgColor}; padding: {$padding};">
        <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
            {$innerHtml}
        </table>
    </td>
</tr>
HTML;
	}

	/**
	 * Render row.
	 */
	private function renderRow( array $attrs, array $children, string $parentBgColor = '' ): string {
		$bgColor = esc_attr( $attrs['backgroundColor'] ?? 'transparent' );
		$padding = esc_attr( $attrs['padding'] ?? '0' );

		// Pass row's own bg color if set, otherwise inherit parent's
		$childBgColor = ( $bgColor !== 'transparent' && ! empty( $bgColor ) ) ? $bgColor : $parentBgColor;
		$innerHtml = $this->renderBlocks( $children, $childBgColor );

		return <<<HTML
<tr>
    <td style="background-color: {$bgColor}; padding: {$padding};">
        <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
            {$innerHtml}
        </table>
    </td>
</tr>
HTML;
	}

	/**
	 * Render columns.
	 */
	private function renderColumns( string $type, array $attrs, array $children, string $parentBgColor = '' ): string {
		$bgColor = esc_attr( $attrs['backgroundColor'] ?? 'transparent' );

		// Determine column widths - use custom columnWidths if set
		$customWidths = $attrs['columnWidths'] ?? null;
		if ( is_array( $customWidths ) && ! empty( $customWidths ) ) {
			$widths = array_map( 'intval', $customWidths );
		} else {
			switch ( $type ) {
				case 'columns-1':
					$widths = [ 100 ];
					break;
				case 'columns-2':
					$widths = [ 50, 50 ];
					break;
				case 'columns-2-sidebar':
					$widths = ( $attrs['layout'] ?? '70-30' ) === '70-30' ? [ 70, 30 ] : [ 30, 70 ];
					break;
				case 'columns-3':
					$widths = [ 33, 34, 33 ];
					break;
				default:
					$widths = [ 100 ];
			}
		}

		$contentWidth = (int) $this->globalStyles['contentWidth'];

		$childBgColor = ( $bgColor !== 'transparent' && ! empty( $bgColor ) ) ? $bgColor : $parentBgColor;
		$columnsHtml = '';
		foreach ( $children as $index => $child ) {
			$width = $widths[ $index ] ?? $widths[0];
			$pixelWidth = (int) ( $contentWidth * $width / 100 );
			$childHtml = $this->renderBlock( $child, $childBgColor );

			$columnsHtml .= <<<HTML
<!--[if mso]>
<td valign="top" width="{$pixelWidth}">
<![endif]-->
<div class="column" style="display: inline-block; vertical-align: top; width: 100%; max-width: {$pixelWidth}px;">
    <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
        {$childHtml}
    </table>
</div>
<!--[if mso]>
</td>
<![endif]-->
HTML;
		}

		return <<<HTML
<tr>
    <td style="background-color: {$bgColor};">
        <!--[if mso]>
        <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
        <tr>
        <![endif]-->
        {$columnsHtml}
        <!--[if mso]>
        </tr>
        </table>
        <![endif]-->
    </td>
</tr>
HTML;
	}

	/**
	 * Render header block.
	 */
	private function renderHeader( array $attrs ): string {
		$bgColor = esc_attr( $attrs['backgroundColor'] ?? $this->globalStyles['primaryColor'] );
		$padding = esc_attr( $attrs['padding'] ?? '30px 20px' );
		$logoUrl = esc_url( $attrs['logoUrl'] ?? '' );
		$logoAlt = esc_attr( $attrs['logoAlt'] ?? get_bloginfo( 'name' ) );
		$logoWidth = (int) ( $attrs['logoWidth'] ?? 200 );
		$align = esc_attr( $attrs['align'] ?? 'center' );

		$logoHtml = '';
		if ( $logoUrl ) {
			$logoHtml = "<img src=\"{$logoUrl}\" alt=\"{$logoAlt}\" width=\"{$logoWidth}\" style=\"display: block; max-width: 100%; height: auto;\">";
		} else {
			$siteTitle = esc_html( get_bloginfo( 'name' ) );
			$logoHtml = "<h1 style=\"margin: 0; color: #ffffff; font-size: 24px;\">{$siteTitle}</h1>";
		}

		return <<<HTML
<tr>
    <td align="{$align}" style="background-color: {$bgColor}; padding: {$padding};">
        {$logoHtml}
    </td>
</tr>
HTML;
	}

	/**
	 * Render heading block.
	 */
	private function renderHeading( array $attrs ): string {
		$level = (int) ( $attrs['level'] ?? 2 );
		$level = max( 1, min( 6, $level ) );
		$tag = "h{$level}";

		$text = wp_kses_post( $attrs['text'] ?? '' );
		$color = esc_attr( $attrs['color'] ?? $this->globalStyles['textColor'] );
		$align = esc_attr( $attrs['align'] ?? 'left' );
		$fontSize = esc_attr( $attrs['fontSize'] ?? $this->getHeadingFontSize( $level ) );
		$padding = esc_attr( $attrs['padding'] ?? '10px 0' );

		return <<<HTML
<tr>
    <td style="padding: {$padding};">
        <{$tag} style="margin: 0; color: {$color}; text-align: {$align}; font-size: {$fontSize}; font-weight: bold;">{$text}</{$tag}>
    </td>
</tr>
HTML;
	}

	/**
	 * Get default font size for heading level.
	 */
	private function getHeadingFontSize( int $level ): string {
		$sizes = [
			1 => '32px',
			2 => '28px',
			3 => '24px',
			4 => '20px',
			5 => '18px',
			6 => '16px',
		];
		return $sizes[ $level ] ?? '16px';
	}

	/**
	 * Render text block.
	 */
	private function renderText( array $attrs ): string {
		$content = wp_kses_post( $attrs['content'] ?? '' );
		$color = esc_attr( $attrs['color'] ?? $this->globalStyles['textColor'] );
		$fontSize = esc_attr( $attrs['fontSize'] ?? $this->globalStyles['fontSize'] );
		$lineHeight = esc_attr( $attrs['lineHeight'] ?? $this->globalStyles['lineHeight'] );
		$align = esc_attr( $attrs['align'] ?? 'left' );
		$padding = esc_attr( $attrs['padding'] ?? '10px 0' );
		$fontFamily = esc_attr( $this->globalStyles['fontFamily'] );

		return <<<HTML
<tr>
    <td style="padding: {$padding};">
        <p style="margin: 0; color: {$color}; font-size: {$fontSize}; line-height: {$lineHeight}; text-align: {$align}; font-family: {$fontFamily};">{$content}</p>
    </td>
</tr>
HTML;
	}

	/**
	 * Render button block.
	 */
	private function renderButton( array $attrs ): string {
		$text = esc_html( $attrs['text'] ?? __( 'Click Here', 'double-opt-in' ) );
		$rawUrl = $attrs['url'] ?? '#';
		// Check if URL is a placeholder (contains [...]) - don't escape it to avoid adding http:// prefix
		// Placeholders will be replaced later with actual URLs that already have proper protocols
		if ( preg_match( '/\[.+\]/', $rawUrl ) ) {
			$url = $rawUrl;
		} else {
			$url = esc_url( $rawUrl );
		}
		$bgColor = esc_attr( $attrs['backgroundColor'] ?? $this->globalStyles['primaryColor'] );
		$textColor = esc_attr( $attrs['textColor'] ?? '#ffffff' );
		$borderRadius = (int) ( $attrs['borderRadius'] ?? 4 );
		$fontSize = esc_attr( $attrs['fontSize'] ?? '16px' );
		$paddingV = (int) ( $attrs['paddingV'] ?? 12 );
		$paddingH = (int) ( $attrs['paddingH'] ?? 24 );
		$align = esc_attr( $attrs['align'] ?? 'center' );
		$padding = esc_attr( $attrs['padding'] ?? '10px 0' );
		$width = esc_attr( $attrs['width'] ?? 'auto' );

		// Build width styles
		$tableWidth = ( $width !== 'auto' ) ? ' width="100%"' : '';
		$widthStyle = ( $width !== 'auto' ) ? "width: {$width}; box-sizing: border-box; text-align: center;" : '';

		return <<<HTML
<tr>
    <td align="{$align}" style="padding: {$padding};">
        <table role="presentation" border="0" cellpadding="0" cellspacing="0"{$tableWidth}>
            <tr>
                <td align="center" bgcolor="{$bgColor}" style="border-radius: {$borderRadius}px;">
                    <!--[if mso]>
                    <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="{$url}" style="height:auto;v-text-anchor:middle;width:auto;" arcsize="10%" strokecolor="{$bgColor}" fillcolor="{$bgColor}">
                    <w:anchorlock/>
                    <center style="color:{$textColor};font-family:Arial,sans-serif;font-size:{$fontSize};">
                    <![endif]-->
                    <a href="{$url}" target="_blank" style="display: inline-block; min-width: 200px; padding: {$paddingV}px {$paddingH}px; font-family: Arial, sans-serif; font-size: {$fontSize}; color: {$textColor}; text-decoration: none; background-color: {$bgColor}; border-radius: {$borderRadius}px; font-weight: bold; white-space: nowrap; text-align: center; mso-padding-alt: 0; {$widthStyle}">{$text}</a>
                    <!--[if mso]>
                    </center>
                    </v:roundrect>
                    <![endif]-->
                </td>
            </tr>
        </table>
    </td>
</tr>
HTML;
	}

	/**
	 * Render image block.
	 */
	private function renderImage( array $attrs ): string {
		$url = esc_url( $attrs['url'] ?? '' );
		$alt = esc_attr( $attrs['alt'] ?? '' );
		$width = esc_attr( $attrs['width'] ?? '100%' );
		$rawLink = $attrs['link'] ?? '';
		// Check if link is a placeholder (contains [...]) - don't escape it to avoid adding http:// prefix
		if ( ! empty( $rawLink ) && preg_match( '/\[.+\]/', $rawLink ) ) {
			$link = $rawLink;
		} else {
			$link = esc_url( $rawLink );
		}
		$align = esc_attr( $attrs['align'] ?? 'center' );
		$padding = esc_attr( $attrs['padding'] ?? '10px 0' );

		if ( empty( $url ) ) {
			return '';
		}

		$imgHtml = "<img src=\"{$url}\" alt=\"{$alt}\" width=\"{$width}\" style=\"display: block; max-width: 100%; height: auto;\">";

		if ( $link ) {
			$imgHtml = "<a href=\"{$link}\" target=\"_blank\">{$imgHtml}</a>";
		}

		return <<<HTML
<tr>
    <td align="{$align}" style="padding: {$padding};">
        {$imgHtml}
    </td>
</tr>
HTML;
	}

	/**
	 * Render divider block.
	 */
	private function renderDivider( array $attrs ): string {
		$color = esc_attr( $attrs['color'] ?? '#dddddd' );
		$thickness = (int) ( $attrs['thickness'] ?? 1 );
		$style = esc_attr( $attrs['style'] ?? 'solid' );
		$padding = esc_attr( $attrs['padding'] ?? '20px 0' );

		return <<<HTML
<tr>
    <td style="padding: {$padding};">
        <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
            <tr>
                <td style="border-top: {$thickness}px {$style} {$color};"></td>
            </tr>
        </table>
    </td>
</tr>
HTML;
	}

	/**
	 * Render spacer block.
	 */
	private function renderSpacer( array $attrs, string $parentBgColor = '' ): string {
		$height = (int) ( $attrs['height'] ?? 20 );
		$bgColor = ! empty( $attrs['backgroundColor'] ) && $attrs['backgroundColor'] !== 'transparent'
			? esc_attr( $attrs['backgroundColor'] )
			: ( ! empty( $parentBgColor ) ? esc_attr( $parentBgColor ) : 'transparent' );

		return <<<HTML
<tr>
    <td style="height: {$height}px; font-size: 0; line-height: 0; background-color: {$bgColor};">&nbsp;</td>
</tr>
HTML;
	}

	/**
	 * Render social icons block.
	 */
	private function renderSocialIcons( array $attrs ): string {
		$icons = $attrs['icons'] ?? [];
		$iconSize = (int) ( $attrs['iconSize'] ?? 32 );
		$align = esc_attr( $attrs['align'] ?? 'center' );
		$padding = esc_attr( $attrs['padding'] ?? '20px 0' );
		$spacing = (int) ( $attrs['spacing'] ?? 10 );

		$iconColors = [
			'facebook'  => '#1877f2',
			'twitter'   => '#1da1f2',
			'instagram' => '#e4405f',
			'linkedin'  => '#0a66c2',
			'youtube'   => '#ff0000',
		];

		$iconsHtml = '';
		foreach ( $icons as $icon ) {
			$network = esc_attr( $icon['network'] ?? '' );
			$url = esc_url( $icon['url'] ?? '#' );
			$color = esc_attr( $iconColors[ $network ] ?? '#666666' );
			$label = ucfirst( $network );

			// Simple text-based icons for email compatibility
			$iconsHtml .= <<<HTML
<td style="padding: 0 {$spacing}px;">
    <a href="{$url}" target="_blank" style="display: inline-block; width: {$iconSize}px; height: {$iconSize}px; background-color: {$color}; border-radius: 50%; text-align: center; line-height: {$iconSize}px; color: #ffffff; font-size: 12px; font-weight: bold; text-decoration: none;" title="{$label}">{$label[0]}</a>
</td>
HTML;
		}

		if ( empty( $iconsHtml ) ) {
			return '';
		}

		return <<<HTML
<tr>
    <td align="{$align}" style="padding: {$padding};">
        <table role="presentation" border="0" cellpadding="0" cellspacing="0">
            <tr>
                {$iconsHtml}
            </tr>
        </table>
    </td>
</tr>
HTML;
	}

	/**
	 * Render footer block.
	 */
	private function renderFooter( array $attrs ): string {
		$bgColor = esc_attr( $attrs['backgroundColor'] ?? '#f4f4f4' );
		$textColor = esc_attr( $attrs['textColor'] ?? '#666666' );
		$content = wp_kses_post( $attrs['content'] ?? '' );
		$padding = esc_attr( $attrs['padding'] ?? '30px 20px' );
		$align = esc_attr( $attrs['align'] ?? 'center' );
		$fontSize = esc_attr( $attrs['fontSize'] ?? '12px' );

		if ( empty( $content ) ) {
			$year = date( 'Y' );
			$siteName = esc_html( get_bloginfo( 'name' ) );
			$content = "&copy; {$year} {$siteName}. All rights reserved.";
		}

		return <<<HTML
<tr>
    <td style="background-color: {$bgColor}; padding: {$padding};">
        <p style="margin: 0; color: {$textColor}; font-size: {$fontSize}; text-align: {$align}; line-height: 1.5;">{$content}</p>
    </td>
</tr>
HTML;
	}

	/**
	 * Inject custom CSS as inline styles on the outermost HTML element.
	 *
	 * @param string $html      The block HTML.
	 * @param string $customCss CSS properties to inject (e.g., "background: #fff; border: 1px solid #ccc;").
	 * @return string HTML with custom CSS injected.
	 */
	private function injectCustomCss( string $html, string $customCss ): string {
		$sanitizedCss = esc_attr( trim( $customCss ) );
		if ( empty( $sanitizedCss ) ) {
			return $html;
		}

		// Wrap in a div with inline styles (email-safe approach)
		return '<div style="' . $sanitizedCss . '">' . $html . '</div>';
	}

	/**
	 * Render conditional content block.
	 *
	 * @param array $attrs    Block attributes.
	 * @param array $children Child blocks.
	 * @return string Rendered HTML with condition markers.
	 */
	private function renderConditionalContent( array $attrs, array $children, string $parentBgColor = '' ): string {
		$field    = esc_attr( $attrs['field'] ?? '' );
		$operator = esc_attr( $attrs['operator'] ?? 'not_empty' );
		$value    = esc_attr( $attrs['value'] ?? '' );
		$padding  = esc_attr( $attrs['padding'] ?? '0' );

		$condition = wp_json_encode( [
			'field'    => $field,
			'operator' => $operator,
			'value'    => $value,
		] );

		$innerHtml = $this->renderBlocks( $children, $parentBgColor );

		return '<div data-doi-condition="' . esc_attr( $condition ) . '" style="padding: ' . $padding . ';">' . $innerHtml . '</div>';
	}

	/**
	 * Render placeholder block.
	 */
	private function renderPlaceholder( string $placeholder, array $attrs ): string {
		$display = esc_attr( $attrs['display'] ?? 'inline' );
		$padding = esc_attr( $attrs['padding'] ?? '0' );

		if ( $display === 'button' ) {
			// Render as a styled button placeholder
			$bgColor = esc_attr( $attrs['backgroundColor'] ?? $this->globalStyles['primaryColor'] );
			$textColor = esc_attr( $attrs['textColor'] ?? '#ffffff' );
			$text = esc_html( $attrs['buttonText'] ?? __( 'Confirm', 'double-opt-in' ) );
			$align = esc_attr( $attrs['align'] ?? 'center' );

			return <<<HTML
<tr>
    <td align="{$align}" style="padding: {$padding};">
        <table role="presentation" border="0" cellpadding="0" cellspacing="0">
            <tr>
                <td align="center" bgcolor="{$bgColor}" style="border-radius: 4px;">
                    <a href="{$placeholder}" target="_blank" style="display: inline-block; padding: 12px 24px; font-family: Arial, sans-serif; font-size: 16px; color: {$textColor}; text-decoration: none; background-color: {$bgColor}; border-radius: 4px; font-weight: bold;">{$text}</a>
                </td>
            </tr>
        </table>
    </td>
</tr>
HTML;
		}

		// Inline display
		return $placeholder;
	}
}
