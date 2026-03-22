<?php
/**
 * Encryption Class
 *
 * @package Ecwid_WooCommerce
 * @since   1.0.0
 */

namespace Ecwid_WooCommerce\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Handles encryption and decryption of sensitive data.
 *
 * Uses OpenSSL for AES-256-CBC encryption with WordPress salts.
 *
 * @since 1.0.0
 */
class Encryption {

    /**
     * Encryption method.
     */
    const METHOD = 'aes-256-cbc';

    /**
     * Single instance.
     *
     * @var Encryption|null
     */
    private static $instance = null;

    /**
     * Encryption key.
     *
     * @var string
     */
    private $key;

    /**
     * Get single instance.
     *
     * @return Encryption
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        $this->key = $this->get_encryption_key();
    }

    /**
     * Get the encryption key.
     *
     * Uses WordPress salts to generate a consistent key.
     *
     * @return string
     */
    private function get_encryption_key() {
        // Use WordPress salts for key derivation.
        $salt = '';

        if ( defined( 'AUTH_KEY' ) ) {
            $salt .= AUTH_KEY;
        }
        if ( defined( 'SECURE_AUTH_KEY' ) ) {
            $salt .= SECURE_AUTH_KEY;
        }
        if ( defined( 'LOGGED_IN_KEY' ) ) {
            $salt .= LOGGED_IN_KEY;
        }

        // Fallback if salts are not defined.
        if ( empty( $salt ) ) {
            $salt = 'ecwid-wc-default-salt-' . get_site_url();
        }

        // Derive a 32-byte key using SHA-256.
        return hash( 'sha256', $salt, true );
    }

    /**
     * Encrypt a string.
     *
     * @param string $data Plain text to encrypt.
     * @return string|false Encrypted data (base64 encoded) or false on failure.
     */
    public function encrypt( $data ) {
        if ( empty( $data ) ) {
            return '';
        }

        if ( ! $this->is_encryption_available() ) {
            // Fallback: return base64 encoded data with marker.
            return 'plain:' . base64_encode( $data ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
        }

        // Generate a random IV.
        $iv_length = openssl_cipher_iv_length( self::METHOD );
        $iv        = openssl_random_pseudo_bytes( $iv_length );

        if ( false === $iv ) {
            return false;
        }

        // Encrypt the data.
        $encrypted = openssl_encrypt(
            $data,
            self::METHOD,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ( false === $encrypted ) {
            return false;
        }

        // Combine IV and encrypted data, then base64 encode.
        $combined = $iv . $encrypted;

        return base64_encode( $combined ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
    }

    /**
     * Decrypt a string.
     *
     * @param string $data Encrypted data (base64 encoded).
     * @return string|false Decrypted plain text or false on failure.
     */
    public function decrypt( $data ) {
        if ( empty( $data ) ) {
            return '';
        }

        // Check for plain text fallback marker.
        if ( strpos( $data, 'plain:' ) === 0 ) {
            return base64_decode( substr( $data, 6 ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
        }

        if ( ! $this->is_encryption_available() ) {
            return false;
        }

        // Decode the base64 data.
        $combined = base64_decode( $data ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

        if ( false === $combined ) {
            return false;
        }

        // Extract IV and encrypted data.
        $iv_length = openssl_cipher_iv_length( self::METHOD );
        $iv        = substr( $combined, 0, $iv_length );
        $encrypted = substr( $combined, $iv_length );

        if ( strlen( $iv ) !== $iv_length ) {
            return false;
        }

        // Decrypt the data.
        $decrypted = openssl_decrypt(
            $encrypted,
            self::METHOD,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv
        );

        return $decrypted;
    }

    /**
     * Check if encryption is available.
     *
     * @return bool
     */
    public function is_encryption_available() {
        return extension_loaded( 'openssl' ) && in_array( self::METHOD, openssl_get_cipher_methods(), true );
    }

    /**
     * Encrypt and save an option.
     *
     * @param string $option_name Option name.
     * @param string $value       Value to encrypt and save.
     * @return bool
     */
    public function save_encrypted_option( $option_name, $value ) {
        $encrypted = $this->encrypt( $value );

        if ( false === $encrypted ) {
            return false;
        }

        return update_option( $option_name, $encrypted );
    }

    /**
     * Get and decrypt an option.
     *
     * @param string $option_name Option name.
     * @param string $default     Default value if option doesn't exist.
     * @return string|false
     */
    public function get_decrypted_option( $option_name, $default = '' ) {
        $encrypted = get_option( $option_name, '' );

        if ( empty( $encrypted ) ) {
            return $default;
        }

        $decrypted = $this->decrypt( $encrypted );

        return false === $decrypted ? $default : $decrypted;
    }

    /**
     * Mask a sensitive string for display.
     *
     * @param string $value       The value to mask.
     * @param int    $visible     Number of characters to show at end.
     * @param string $mask_char   Character to use for masking.
     * @return string
     */
    public function mask( $value, $visible = 4, $mask_char = '*' ) {
        if ( empty( $value ) ) {
            return '';
        }

        $length = strlen( $value );

        if ( $length <= $visible ) {
            return str_repeat( $mask_char, $length );
        }

        $masked_length = $length - $visible;
        $masked_part   = str_repeat( $mask_char, min( $masked_length, 20 ) );
        $visible_part  = substr( $value, -$visible );

        return $masked_part . $visible_part;
    }

    /**
     * Validate that a string looks like an encrypted value.
     *
     * @param string $value Value to check.
     * @return bool
     */
    public function is_encrypted( $value ) {
        if ( empty( $value ) ) {
            return false;
        }

        // Check for plain text fallback marker.
        if ( strpos( $value, 'plain:' ) === 0 ) {
            return true;
        }

        // Check if it's valid base64.
        $decoded = base64_decode( $value, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

        if ( false === $decoded ) {
            return false;
        }

        // Check minimum length (IV + some encrypted data).
        $iv_length = openssl_cipher_iv_length( self::METHOD );

        return strlen( $decoded ) > $iv_length;
    }

    /**
     * Re-encrypt a value with a new key.
     *
     * Useful for key rotation.
     *
     * @param string $encrypted_value Current encrypted value.
     * @param string $new_key         New encryption key.
     * @return string|false
     */
    public function re_encrypt( $encrypted_value, $new_key ) {
        $decrypted = $this->decrypt( $encrypted_value );

        if ( false === $decrypted ) {
            return false;
        }

        // Temporarily store the old key.
        $old_key = $this->key;

        // Set the new key.
        $this->key = hash( 'sha256', $new_key, true );

        // Encrypt with new key.
        $re_encrypted = $this->encrypt( $decrypted );

        // Restore the old key.
        $this->key = $old_key;

        return $re_encrypted;
    }
}
