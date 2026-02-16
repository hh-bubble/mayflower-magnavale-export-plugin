<?php
/**
 * MME_SFTP_Uploader
 *
 * Handles SFTP connection and file uploads using phpseclib.
 *
 * ARCHITECTURE:
 * =============
 * - Bubble Design owns the SFTP server
 * - This plugin is the SFTP CLIENT (pushes files TO the server)
 * - Magnavale connects separately to the SFTP server (read-only) to retrieve files
 * - Credentials are stored encrypted in WordPress options (wp_options)
 *
 * CONNECTION FLOW:
 * ================
 * 1. Read SFTP credentials from plugin settings (decrypted)
 * 2. Connect via SSH2 / SFTP
 * 3. Upload both CSV files to the configured remote directory
 * 4. Verify files were uploaded successfully (check file size)
 * 5. Return success/failure result
 *
 * ERROR HANDLING:
 * ===============
 * - Connection failure → retry once, then log failure + email alert
 * - Upload failure → log failure + email alert
 * - Files are NOT deleted locally on failure (preserved for retry)
 *
 * PHPSECLIB:
 * ==========
 * Using phpseclib3 for SFTP operations. Can be loaded via Composer or
 * bundled in the vendor/ directory.
 *
 * @package MayflowerMagnavaleExport
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Load phpseclib — adjust path depending on how it's installed
// Option 1: Composer autoload
if ( file_exists( MME_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
    require_once MME_PLUGIN_DIR . 'vendor/autoload.php';
}

class MME_SFTP_Uploader {

    /**
     * SFTP connection settings.
     * Loaded from WordPress options (encrypted).
     *
     * @var array
     */
    private $settings = [];

    /**
     * Maximum number of upload retry attempts.
     *
     * @var int
     */
    private $max_retries = 1;

    /**
     * Constructor — loads SFTP settings from WordPress options.
     */
    public function __construct() {
        $this->settings = [
            'host'       => $this->decrypt( get_option( 'mme_sftp_host', '' ) ),
            'port'       => intval( get_option( 'mme_sftp_port', 22 ) ),
            'username'   => $this->decrypt( get_option( 'mme_sftp_username', '' ) ),
            'password'   => $this->decrypt( get_option( 'mme_sftp_password', '' ) ),
            'remote_dir' => get_option( 'mme_sftp_remote_dir', '/uploads/' ),
            'key_path'   => get_option( 'mme_sftp_key_path', '' ), // Optional: SSH key auth
        ];
    }

    /**
     * Upload files to the SFTP server.
     *
     * @param array $files Associative array of remote_filename => local_filepath
     * @return array {
     *     @type bool   $success Whether all files uploaded successfully
     *     @type string $error   Error message if failed (empty on success)
     *     @type array  $uploaded List of successfully uploaded filenames
     * }
     */
    public function upload( array $files ) {

        // Validate settings before attempting connection
        if ( empty( $this->settings['host'] ) || empty( $this->settings['username'] ) ) {
            return [
                'success'  => false,
                'error'    => 'SFTP credentials not configured. Check plugin settings.',
                'uploaded' => [],
            ];
        }

        // Attempt connection (with retry)
        $sftp = null;
        $attempts = 0;
        $last_error = '';

        while ( $attempts <= $this->max_retries ) {
            try {
                $sftp = $this->connect();
                break; // Connection successful
            } catch ( \Exception $e ) {
                $last_error = $e->getMessage();
                $attempts++;

                if ( $attempts <= $this->max_retries ) {
                    // Wait 5 seconds before retrying
                    sleep( 5 );
                }
            }
        }

        if ( ! $sftp ) {
            return [
                'success'  => false,
                'error'    => 'SFTP connection failed after ' . ( $this->max_retries + 1 ) . ' attempts: ' . $last_error,
                'uploaded' => [],
            ];
        }

        // Upload each file
        $uploaded = [];

        foreach ( $files as $remote_filename => $local_filepath ) {
            try {
                $remote_path = rtrim( $this->settings['remote_dir'], '/' ) . '/' . $remote_filename;

                // Read the local file
                $file_content = file_get_contents( $local_filepath );
                if ( $file_content === false ) {
                    throw new \Exception( "Cannot read local file: {$local_filepath}" );
                }

                // Upload via SFTP
                // phpseclib3 method: $sftp->put( $remote_path, $file_content )
                $result = $sftp->put( $remote_path, $file_content );

                if ( ! $result ) {
                    throw new \Exception( "Failed to upload {$remote_filename} to {$remote_path}" );
                }

                // Verify upload by checking remote file size
                $remote_size = $sftp->size( $remote_path );
                $local_size  = strlen( $file_content );

                if ( $remote_size !== $local_size ) {
                    throw new \Exception( sprintf(
                        'Size mismatch for %s: local=%d, remote=%d',
                        $remote_filename, $local_size, $remote_size
                    ) );
                }

                $uploaded[] = $remote_filename;

            } catch ( \Exception $e ) {
                // If any file fails, return failure
                // Don't mark previous files as failed though — they did upload
                return [
                    'success'  => false,
                    'error'    => $e->getMessage(),
                    'uploaded' => $uploaded,
                ];
            }
        }

        // All files uploaded successfully
        return [
            'success'  => true,
            'error'    => '',
            'uploaded' => $uploaded,
        ];
    }

    /**
     * Establish SFTP connection using phpseclib3.
     *
     * Supports both password and SSH key authentication.
     *
     * @return \phpseclib3\Net\SFTP The connected SFTP object
     * @throws \Exception On connection or authentication failure
     */
    private function connect() {
        // phpseclib3 classes
        $sftp = new \phpseclib3\Net\SFTP(
            $this->settings['host'],
            $this->settings['port'],
            30 // timeout in seconds
        );

        // Authenticate — SSH key or password
        if ( ! empty( $this->settings['key_path'] ) && file_exists( $this->settings['key_path'] ) ) {
            // SSH key authentication
            $key = \phpseclib3\Crypt\PublicKeyLoader::load(
                file_get_contents( $this->settings['key_path'] ),
                $this->settings['password'] // passphrase for the key, if any
            );

            if ( ! $sftp->login( $this->settings['username'], $key ) ) {
                throw new \Exception( 'SFTP key authentication failed for ' . $this->settings['host'] );
            }
        } else {
            // Password authentication
            if ( ! $sftp->login( $this->settings['username'], $this->settings['password'] ) ) {
                throw new \Exception( 'SFTP password authentication failed for ' . $this->settings['host'] );
            }
        }

        return $sftp;
    }

    /**
     * Test the SFTP connection without uploading anything.
     * Used by the admin settings page to verify credentials.
     *
     * @return array { 'success' => bool, 'message' => string }
     */
    public function test_connection() {
        try {
            $sftp = $this->connect();

            // Try listing the remote directory to confirm access
            $listing = $sftp->nlist( $this->settings['remote_dir'] );

            if ( $listing === false ) {
                return [
                    'success' => false,
                    'message' => 'Connected but cannot access remote directory: ' . $this->settings['remote_dir'],
                ];
            }

            return [
                'success' => true,
                'message' => sprintf(
                    'Connected successfully. Remote directory contains %d items.',
                    count( $listing )
                ),
            ];

        } catch ( \Exception $e ) {
            return [
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Encrypt a value for storage in the database.
     * Uses WordPress AUTH_KEY as the encryption key.
     *
     * @param string $value Plain text value
     * @return string Encrypted value (base64 encoded)
     */
    public static function encrypt( $value ) {
        if ( empty( $value ) ) {
            return '';
        }

        $key = defined( 'AUTH_KEY' ) ? AUTH_KEY : 'mayflower-fallback-key';

        // Use OpenSSL if available, otherwise fall back to simple obfuscation
        if ( function_exists( 'openssl_encrypt' ) ) {
            $iv     = openssl_random_pseudo_bytes( 16 );
            $cipher = openssl_encrypt( $value, 'AES-256-CBC', $key, 0, $iv );
            return base64_encode( $iv . $cipher );
        }

        // Fallback: base64 encode (not truly encrypted, but better than plain text)
        return base64_encode( $value );
    }

    /**
     * Decrypt a value from the database.
     *
     * @param string $encrypted_value Encrypted value (base64 encoded)
     * @return string Decrypted plain text value
     */
    private function decrypt( $encrypted_value ) {
        if ( empty( $encrypted_value ) ) {
            return '';
        }

        $key = defined( 'AUTH_KEY' ) ? AUTH_KEY : 'mayflower-fallback-key';

        if ( function_exists( 'openssl_decrypt' ) ) {
            $data  = base64_decode( $encrypted_value );
            $iv    = substr( $data, 0, 16 );
            $cipher = substr( $data, 16 );
            $decrypted = openssl_decrypt( $cipher, 'AES-256-CBC', $key, 0, $iv );
            return $decrypted !== false ? $decrypted : '';
        }

        // Fallback
        return base64_decode( $encrypted_value );
    }
}
