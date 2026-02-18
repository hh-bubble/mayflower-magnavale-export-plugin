<?php
/**
 * MME_SFTP_Uploader
 *
 * Handles FTPS (FTP over explicit TLS) connection and file uploads
 * using PHP's built-in FTP extension.
 *
 * ARCHITECTURE:
 * =============
 * - Bubble Design owns the FTPS server
 * - This plugin is the FTPS CLIENT (pushes files TO the server)
 * - Magnavale connects separately to the server (read-only) to retrieve files
 * - Credentials are stored encrypted in WordPress options (wp_options)
 * - The FTP user is chrooted to /www/www/Magnavale on the server,
 *   so '/' from the FTP perspective is the correct upload target
 *
 * CONNECTION FLOW:
 * ================
 * 1. Read FTPS credentials from plugin settings (decrypted)
 * 2. Connect via ftp_ssl_connect (explicit TLS on port 21)
 * 3. Authenticate with username + password
 * 4. Enable passive mode
 * 5. Upload both CSV files to the configured remote directory
 * 6. Verify files were uploaded successfully (check file size)
 * 7. Close connection and return success/failure result
 *
 * ERROR HANDLING:
 * ===============
 * - Connection failure → retry once after 5 seconds, then log failure + email alert
 * - Auth failure → return immediately (no retry)
 * - Upload failure → log failure + email alert
 * - Files are NOT deleted locally on failure (preserved for retry)
 * - Connection is always closed in a finally block
 *
 * @package MayflowerMagnavaleExport
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MME_SFTP_Uploader {

	/**
	 * FTPS connection settings.
	 * Loaded from WordPress options (encrypted).
	 *
	 * @var array
	 */
	private $settings = [];

	/**
	 * Maximum number of connection retry attempts.
	 *
	 * @var int
	 */
	private $max_retries = 1;

	/**
	 * Constructor — loads FTPS settings from WordPress options.
	 */
	public function __construct() {
		$this->settings = [
			'host'       => $this->decrypt( get_option( 'mme_sftp_host', '' ) ),
			'port'       => intval( get_option( 'mme_sftp_port', 21 ) ),
			'username'   => $this->decrypt( get_option( 'mme_sftp_username', '' ) ),
			'password'   => $this->decrypt( get_option( 'mme_sftp_password', '' ) ),
			'remote_dir' => get_option( 'mme_sftp_remote_dir', '/' ),
		];
	}

	/**
	 * Upload files to the FTPS server.
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
				'error'    => 'FTPS credentials not configured. Check plugin settings.',
				'uploaded' => [],
			];
		}

		// Attempt connection (with retry)
		$conn       = false;
		$attempts   = 0;
		$last_error = '';

		while ( $attempts <= $this->max_retries ) {
			try {
				$conn = $this->connect();
				break; // Connection successful
			} catch ( \Exception $e ) {
				$last_error = $e->getMessage();
				$attempts++;

				// Don't retry auth failures — wrong credentials won't fix themselves
				if ( strpos( $last_error, 'authentication failed' ) !== false ) {
					break;
				}

				if ( $attempts <= $this->max_retries ) {
					sleep( 5 );
				}
			}
		}

		if ( ! $conn ) {
			return [
				'success'  => false,
				'error'    => 'FTPS connection failed after ' . ( $attempts ) . ' attempt(s): ' . $last_error,
				'uploaded' => [],
			];
		}

		// Upload each file
		$uploaded = [];

		try {
			foreach ( $files as $remote_filename => $local_filepath ) {
				$remote_path = rtrim( $this->settings['remote_dir'], '/' ) . '/' . $remote_filename;

				// Verify local file exists and is readable
				if ( ! is_readable( $local_filepath ) ) {
					throw new \Exception( "Cannot read local file: {$local_filepath}" );
				}

				// Upload via FTPS (binary mode, takes file path directly)
				$result = ftp_put( $conn, $remote_path, $local_filepath, FTP_BINARY );

				if ( ! $result ) {
					throw new \Exception( "Failed to upload {$remote_filename} to {$remote_path}" );
				}

				// Verify upload by checking remote file size
				$remote_size = ftp_size( $conn, $remote_path );
				$local_size  = filesize( $local_filepath );

				if ( $remote_size === -1 ) {
					throw new \Exception( "Cannot verify remote file size for {$remote_filename}" );
				}

				if ( $remote_size !== $local_size ) {
					throw new \Exception( sprintf(
						'Size mismatch for %s: local=%d, remote=%d',
						$remote_filename, $local_size, $remote_size
					) );
				}

				$uploaded[] = $remote_filename;
			}
		} catch ( \Exception $e ) {
			ftp_close( $conn );
			return [
				'success'  => false,
				'error'    => $e->getMessage(),
				'uploaded' => $uploaded,
			];
		}

		// Close connection
		ftp_close( $conn );

		// All files uploaded successfully
		return [
			'success'  => true,
			'error'    => '',
			'uploaded' => $uploaded,
		];
	}

	/**
	 * Establish FTPS connection using PHP's native FTP functions.
	 *
	 * Uses explicit TLS (ftp_ssl_connect) with passive mode.
	 *
	 * @return resource|FTP\Connection The connected FTP resource
	 * @throws \Exception On connection, authentication, or passive mode failure
	 */
	private function connect() {
		// Check that the FTP SSL extension is available
		if ( ! function_exists( 'ftp_ssl_connect' ) ) {
			throw new \Exception( 'PHP FTP SSL extension is not available. Contact your hosting provider.' );
		}

		// Connect with explicit TLS
		$conn = ftp_ssl_connect( $this->settings['host'], $this->settings['port'], 30 );

		if ( ! $conn ) {
			throw new \Exception( 'FTPS connection failed to ' . $this->settings['host'] . ':' . $this->settings['port'] );
		}

		// Authenticate
		$login = ftp_login( $conn, $this->settings['username'], $this->settings['password'] );

		if ( ! $login ) {
			ftp_close( $conn );
			throw new \Exception( 'FTPS authentication failed for ' . $this->settings['host'] );
		}

		// Enable passive mode (essential for most hosting environments)
		if ( ! ftp_pasv( $conn, true ) ) {
			ftp_close( $conn );
			throw new \Exception( 'Failed to enable passive mode on ' . $this->settings['host'] );
		}

		return $conn;
	}

	/**
	 * Test the FTPS connection without uploading anything.
	 * Used by the admin settings page to verify credentials.
	 *
	 * @return array { 'success' => bool, 'message' => string }
	 */
	public function test_connection() {
		try {
			$conn = $this->connect();

			// Try listing the remote directory to confirm access
			$listing = ftp_nlist( $conn, $this->settings['remote_dir'] );

			ftp_close( $conn );

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

		if ( ! function_exists( 'openssl_encrypt' ) ) {
			error_log( '[Mayflower Export] FATAL: OpenSSL extension is required for credential encryption.' );
			return '';
		}

		$key    = defined( 'AUTH_KEY' ) ? AUTH_KEY : wp_salt( 'auth' );
		$iv     = random_bytes( 16 );
		$cipher = openssl_encrypt( $value, 'AES-256-CBC', $key, 0, $iv );

		if ( $cipher === false ) {
			error_log( '[Mayflower Export] Encryption failed.' );
			return '';
		}

		return base64_encode( $iv . $cipher );
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

		if ( ! function_exists( 'openssl_decrypt' ) ) {
			error_log( '[Mayflower Export] FATAL: OpenSSL extension is required for credential decryption.' );
			return '';
		}

		$key  = defined( 'AUTH_KEY' ) ? AUTH_KEY : wp_salt( 'auth' );
		$data = base64_decode( $encrypted_value, true );

		// Validate: data must be at least 17 bytes (16-byte IV + 1 byte cipher minimum)
		if ( $data === false || strlen( $data ) <= 16 ) {
			return '';
		}

		$iv        = substr( $data, 0, 16 );
		$cipher    = substr( $data, 16 );
		$decrypted = openssl_decrypt( $cipher, 'AES-256-CBC', $key, 0, $iv );

		return $decrypted !== false ? $decrypted : '';
	}
}
