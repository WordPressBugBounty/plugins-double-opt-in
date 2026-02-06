<?php

namespace forge12\contactform7\CF7DoubleOptIn;

use Forge12\Shared\Logger;
use Forge12\Shared\LoggerInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TemplateHandler {
	private static ?TemplateHandler $_instance = null;
	private LoggerInterface $logger;

	public static function getInstance() {
		if ( self::$_instance == null ) {
			self::$_instance = new self( Logger::getInstance() );
		}

		return self::$_instance;
	}

	private function __construct( LoggerInterface $logger ) {
		$this->logger = $logger;
		$this->get_logger()->info( 'TemplateHandler class instance created.', [
			'plugin' => 'double-opt-in',
		] );
	}

	public function get_logger() {
		return $this->logger;
	}

	/**
	 * Get the content of the specified template with the given attributes.
	 *
	 * @param string $template_name The name of the template file.
	 * @param array  $atts          The parameters sent to the template.
	 *
	 * @return string The content of the template.
	 */
	public function getTemplate( $template_name, $atts = array() ) {
		$this->get_logger()->info( 'Attempting to load a template file.', [
			'plugin'        => 'double-opt-in',
			'template_name' => $template_name,
			'attributes'    => array_keys($atts), // Log only keys to avoid sensitive data
		] );

		$template_path = plugin_dir_path( dirname( __FILE__ ) ) . 'templates/' . $template_name . '.php';

		if ( ! is_readable( $template_path ) ) {
			$this->get_logger()->error( 'Template file is not readable or does not exist.', [
				'plugin'  => 'double-opt-in',
				'template_name' => $template_name,
				'template_path' => $template_path,
			] );
			return '';
		}

		$this->get_logger()->debug( 'Template file found and is readable. Path: ' . $template_path, [
			'plugin'        => 'double-opt-in',
		] );

		// Extract attributes for use in the template.
		extract( $atts );

		ob_start();
		require( $template_path );
		$template_content = ob_get_clean();

		$this->get_logger()->notice( 'Template content successfully loaded and captured.', [
			'plugin' => 'double-opt-in',
			'template_name' => $template_name,
			'content_length' => strlen($template_content),
		] );

		return $template_content;
	}

	/**
	 * Renders the specified template with the given attributes.
	 *
	 * @param string $template_name The name of the template file.
	 * @param array  $atts          The parameters sent to the template.
	 */
	public function renderTemplate( $template_name, $atts = array() ) {
		$this->get_logger()->info( 'Rendering a template to the output.', [
			'plugin'        => 'double-opt-in',
			'template_name' => $template_name,
		] );

		$template = $this->getTemplate( $template_name, $atts );

		if ( empty( $template ) ) {
			$this->get_logger()->warning( 'Template content is empty. Nothing to render.', [
				'plugin'        => 'double-opt-in',
				'template_name' => $template_name,
			] );
			return;
		}

		echo $template;

		$this->get_logger()->notice( 'Template content successfully rendered.', [
			'plugin'         => 'double-opt-in',
			'template_name'  => $template_name,
			'content_length' => strlen($template),
		] );
	}

}