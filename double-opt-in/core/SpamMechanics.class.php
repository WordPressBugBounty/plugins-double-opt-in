<?php

namespace Forge12\ContactForm7\CF7DoubleOptIn;

use Forge12\Shared\LoggerInterface;

if (!defined('ABSPATH')) {
    exit;
}

class SpamMechanics
{
    /** @var LoggerInterface */
    private $logger;

    /**
     * Konstruktor
     *
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->initialize();
    }

    /**
     * Initialisiert die Spam-Mechanik-Deaktivierung,
     * falls ein gültiger Opt-in-Link aufgerufen wurde.
     *
     * @return void
     */
    private function initialize()
    {
        $this->logDebug('disableSpamMechanics called');

        $hash = isset($_GET['optin']) ? sanitize_text_field($_GET['optin']) : '';
        if (empty($hash)) {
            $this->logDebug('No opt-in hash found, skipping disableSpamMechanics');
            return;
        }

        $optIn = OptIn::get_by_hash($hash);
        if (!$optIn) {
            $this->logDebug('Invalid opt-in hash, skipping disableSpamMechanics');
            return;
        }

        if ($optIn->is_confirmed()) {
            $this->logDebug('Opt-in already confirmed, skipping disableSpamMechanics');
            return;
        }

        $this->disableForge12Captcha();
        $this->disableGoogleRecaptcha();

        $this->logDebug('All spam protection filters removed');
    }

    /**
     * Deaktiviert Forge12 Captcha-Integrationen für CF7, Avada und Elementor.
     *
     * @return void
     */
    private function disableForge12Captcha()
    {
        $filters = array(
            'f12_cf7_captcha_is_installed_cf7',
            'f12_cf7_captcha_is_installed_avada',
            'f12_cf7_captcha_is_installed_elementor', // Tippfehler korrigiert
        );

        foreach ($filters as $filter) {
            add_filter($filter, function ($is_active) {
                return false;
            }, 999, 1);
        }

        $this->logDebug('Forge12 Captcha filters removed');
    }

    /**
     * Entfernt Google reCAPTCHA Filter für CF7.
     *
     * @return void
     */
    private function disableGoogleRecaptcha()
    {
        remove_filter('wpcf7_spam', 'wpcf7_recaptcha_verify_response', 9);
        $this->logDebug('Google reCAPTCHA filter removed');
    }

    /**
     * Vereinheitlichter Logger für Debug-Nachrichten.
     *
     * @param string $message
     * @param array  $context
     * @return void
     */
    private function logDebug($message, $context = array())
    {
        $defaultContext = array(
            'plugin' => 'double-opt-in',
            'class'  => __CLASS__,
        );

        $context = array_merge($defaultContext, $context);

        $this->logger->debug($message, $context);
    }

    /**
     * @return LoggerInterface
     */
    public function get_logger()
    {
        return $this->logger;
    }
}