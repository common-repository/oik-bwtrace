<?php // (C) Copyright Bobbing Wide 2018, 2023

/**
 * @package oik-bwtrace
 * 
 * Trace files directory
 
 *
 * - We need to ensure that trace files are stored in a protected directory.
 * - If it's outside of DOCUMENT_ROOT then it's probably OK.
 * - If this path is below ABSPATH then the folder needs to have an .htaccess file that prevents unauthorized users from accessing it. 
 * - Catering for sub-directory installs in WordPress Multisite could be tricky.
 *
 * The .htaccess file would contain something like this. 
 *
 * ```
 * order deny,allow
 * deny from all
 * allow from 192.168.50.1
 * ```
 * 
 * Note: If running locally this logic doesn't matter so much. 
 * But, since we can't easily check we're running locally we'll defer to the production strength logic...
 * even though we don't expect tracing to be run in production.
 * 
 * 
 * We should also ensure that the path is not a WordPress folder.
 * 
 * 
 */

class trace_files_directory {

	/**
	 * Value entered by user. 
	 * May be set as a constant, in wp-config.php, for when tracing at startup is required.
	 */
	public $trace_files_directory; 
	public $options;
	public $valid = false;
	
	/** 
	 * Fully qualified trace files directory, with trailing slash
	 * Trace files are created in this directory, either directly or within subdirectories.
	 */
	public $fq_trace_files_directory;
	
	/**
	 * Retention period for trace files
	 */
	public $retain;
	
	/**
	 * Fully qualified prefix
	 * 
	 * Prefix for a fully qualified file name.
	 */
	private $fq_prefix;
	private $message;

	
	function __construct() {
		$this->valid = false;
		$this->default_options();
		$this->set_trace_files_directory();
		$this->set_fq_trace_files_directory();
	}
	
	/**
	 * Validates the trace files directory
	 * 
	 * Sets the FQ trace files directory if valid
	 * 
	 * Trace files can contain sensitive data so should not be accessible to the general public.
	 * This can be achieved by placing the files outside of the web root directory. 
	 * 
	 * A less secure method is to place the files in a folder protected by .htaccess
	 * 
	 * We could attempt to mess about with relative directories but it's a lot easier
	 * if we simply ensure that the trace_files_directory starts with a '/' or, in Windows, C:/
	 * 
	 * $directory | Processing
	 * ---------- | ------------
	 * null       | Don't support trace 
	 * 0          | Don't support trace. Note: empty() returns true for "0"
	 * starts /   | Treat as fully qualified
	 * starts C:/ | Treat as fully qualified - Windows only	- where C is a drive letter
	 * directory  | Prepend ABSPATH and check directory exists
	 * starts .   | ?
	 * starts ../ | ? 
	 * 
	 * 
	 * @param string $directory
	 * @return bool validity
	 */
	function validate_trace_files_directory() {
		$this->valid = false;
		$fq_directory = $this->trace_files_directory;
		if ( $this->validate_fq_prefix( $fq_directory ) ) {
			if ( file_exists( $fq_directory ) ) {
				if ( is_dir( $fq_directory ) ) {
					$this->set_fq_trace_files_directory( $fq_directory );
					$this->valid = true;
				} else {
					$this->message( "File is not a directory", $fq_directory );
				}
			} else {
				//echo "Directory does not exist";
				$this->valid = wp_mkdir_p( $fq_directory );
				if ( !$this->valid ) {
					$this->message( "Cannot create directory", $fq_directory );
				}
			}
		} else {
			// Have we already dealt with this?
		}
		return $this->valid;
	}
	
	function message( $text, $value ) {
		$this->message = $text;
		$this->message .= " ";
		$this->message .= $value;
	}
	
	function get_message() {
		return $this->message;
	}
	
	
	function default_options() {
		$this->options = array();
		$this->options[ 'trace_directory' ] = null;
		$this->options[ 'retain'] = 30;
		$this->options['performance_trace'] = '0';
	}
	
	/**
	 * Sets the trace files directory
	 */
	function set_trace_files_directory( $directory=null ) {
		$this->trace_files_directory = $directory;
	}
	
	/**
	 * Sets the retention period
	 * 
	 * @param string $retain expected to be an integer but it doesn't really matter 
	 */
	function set_retention_period( $retain=null ) {
		$this->retain = null;
		if ( is_numeric( $retain ) ) {
			$this->retain = $retain;
		} 
	}
	
	/**
	 * Returns the retention period
	 * 
	 * @returns string|null 
	 */
	function get_retention_period() {
		return $this->retain;
	}
		
	/**
	 * Sets local variables from the options array
	 */	
	function set_options( $options ) {
		$this->set_trace_files_directory( bw_array_get( $options, 'trace_directory' ) );
		$this->set_retention_period( bw_array_get( $options, 'retain' ) );
	}
	
	function is_valid() {
		return $this->valid;
	}
	
	/**
	 * 
	 * Stored without a trailing '/' - to allow for null
	 */
	function set_fq_trace_files_directory( $directory=null ) {
		$this->fq_trace_files_directory = $directory;
	}
	
	/**
	 * Returns the fully qualified trace files directory, if set
	 *
	 * @return string|null Returned with a trailing slash - like ABSPATH 
	 */
	function get_fq_trace_files_directory() {
		if ( !$this->fq_trace_files_directory ) {
			return null;
		}
		return trailingslashit( $this->fq_trace_files_directory );
	}
	
	/**
	 * Gets a sanitized version of ABSPATH
	 *
	 * If the constant is not set it determines it based on this file's location.
	 *
	 * @return string fully qualified path with trailing slash
	 */
	public function get_abspath() {
		if ( !defined('ABSPATH') ) {
			$abspath = dirname( dirname( dirname ( dirname( dirname( __FILE__ ))))) . '/';
		} else { 
			$abspath = ABSPATH;
		}
		$abspath = str_replace( "\\", "/", $abspath );
		if ( ':' === substr( $abspath, 1, 1 ) ) {
            $abspath = ucfirst( $abspath );
        }
		return $abspath;
	}
	
	/**
	 * Builds the fully qualified prefix for a file
	 *
	 * Takes the operating system into account
	 */
	function get_fq_prefix() {
		if ( PHP_OS == "WINNT" ) {
			$fq_prefix = $this->query_windows_homedrive();
			$fq_prefix .= '/';
		} else {
			$fq_prefix = '/';
		}
		return $fq_prefix;
	
	}
	
	/**
	 * Builds the external directory name
	 * 
	 * For non Windows servers (e.g. Linux) we need to find the "home" directory 
	 
	 * e.g.
	 * If [DOCUMENT_ROOT] => /home/t10scom/public_html
	 * and $dir parameter is '/zipdir/'
	 * then external_directory will become "/home/t10scom/zipdir/"
	 * 
	 * @param string - required external directory name with leading and trailing slashes
	 * @return string - external directory with "home" directory prepended
	 */
	function query_external_dir() {
		$external_dir = dirname( $_SERVER['DOCUMENT_ROOT'] );
		//print_r( $_SERVER['DOCUMENT_ROOT'] );
		//print_r( $_SERVER );
		return $external_dir;
	}
	
	/**
	 * Query Windows System Drive
	 *
	 * Obtains the drive letter to prefix a fully qualified file name in a Windows environment
	 * 
	 * Notes:	Variable settings vary depending on the invocation
	 *
	 * Value                      | CLI  | Server
	 * -------------------------- | ---- | ----
	 * $_ENV                      | null | set
	 * getenv( "HOMEDRIVE" )      | set  | null
	 * getenv( "SystemDrive" )    | set  | set
	 * $_SERVER[ 'DOCUMENT_ROOT'] | null | set
	 * 
	 */
	function query_windows_homedrive() {
		$systemdrive = getenv( "SystemDrive" );
		return $systemdrive;
	}
	
	function validate_fq_prefix( $directory ) {
		$valid = false;
		$fq_prefix = $this->get_fq_prefix();
		if ( $directory && 0 === strpos( $directory, $fq_prefix ) ) {
			$valid = true;	
		} else {
			$this->message( "Directory must be fully qualified and start with: ",  $fq_prefix );
		}
		return $valid;
			
	}

}
	
	
