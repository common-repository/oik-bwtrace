<?php

/**
 * @copyright (C) Copyright Bobbing Wide 2018, 2021, 2024
 * @package oik-bwtrace
 */
 
/**
 * Manages tracing of requests
 *
 * supporting a series of methods to configure and enable/disable tracing from the command line
 * 
 * e.g. 
 * wp trace subcommand type 
 * 
 * ## OPTIONS
 *
 * <type>
 * : Transaction type to trace.
 * ---
 * default: default
 * options:
 * - default general /  browser
 * - ajax
 * - rest
 * - cli
 * 
 * <on/off>
 * 
 *
 * 
 * ## EXAMPLES
 * 		# Display trace status
 *    $wp trace status
 *    
 *    # Enable tracing for browser requests
 * 		$wp trace on browser
 * 
 *    # Disable tracing for cli
 *		$wp trace off cli
 * 
 *    # Indicate what to trace
 * 		$wp trace record
 * 
 *    # Set action tracing
 * 		$wp trace actions
 * 
 */
class trace_command extends WP_CLI_Command {

	
	private $options; // 'bw_trace_options'
	private $actions; // 'bw_trace_actions'
	
	private $type = null;
	private $suffix = null;
	private $args = array();
	private $assoc_args = array();

	/**
	 * Turns tracing on
	 *
	 * ## OPTIONS
	 *
	 * [<type>...] 
	 * : Transaction types to trace.
	 * default: browser
	 * - browser
	 * - ajax
	 * - rest
	 * - cli
	 * 
	 * [--file=<trace_file_name>]
	 * : Base name of the trace file, relative to ABSPATH
	 *
	 * [--reset]
	 * : Reset trace file for each transaction
	 * 
	 * [--limit=<limit>]
	 * : Trace file generation
	 * default: 
	 * - integer
	 * - 0
	 *
	 * ## EXAMPLES
	 * 
	 * wp trace on browser 
   */
	public function on( $args, $assoc_args) {
	
		WP_CLI::log( "Turning trace on" );
		$type = bw_array_get( $args, 0, null );
		if ( $type ) {
			$type = $this->validate_type( $type ); 
			WP_CLI::log( "Enabling trace type: $type" );
		} else { 
			$type = 'browser';
			WP_CLI::log( "Enabling default trace type: browser" );
		}
		$this->trace_on( $type, $args, $assoc_args );
	}
	
	/**
	 * Validates the trace type
	 * 
	 * @param string $type the trace type
	 * @return string|null validated type 
	 */
	private function validate_type( $type ) {
		$type = strtolower( trim( $type ) );
		$types = array( "browser", "ajax", "rest", "cli" );
		if ( in_array( $type, $types) ) {
			// $type = 
		} else {
			$type = null;
		}
		return $type;
	}
	
	private function set_type( $type ) {
		$this->type = $type;
	}
	
	/**
	 * Sets the suffix for the trace type
	 *
	 * Originally there was no suffix. It got added for AJAX, then REST and CLI 
	 * @param string $type - sanitized
	 * @return string $suffix
	 */
	private function set_type_suffix( $type ) {
		switch ( $type ) {
			case 'browser':
				$suffix = null;
				
				break;
			
			default:
				$suffix = '_' . $type;
		}
		$this->suffix = $suffix;
		return $suffix;
	}
	
	/**
	 * Sets the positional parameters
	 */
	private function set_args( $args ) {
		$this->args = $args;
	}
	
	/**
	 * Sets the named parameters
	 *
	 * @TODO We need to find out how to deal with boolean values as they need to mapped
	 * 
	 * bool  | option
	 * ----  | -------
	 * true  | "on"
	 * false | 
	 */
	private function set_assoc_args( $assoc_args ) {
		$this->assoc_args = $assoc_args;
	}
	
	/**
	 * Turns tracing on for the given type
	 * 
	 * Allows overrides to --file=, --reset/--no-reset and --limit=
	 */ 
	private function trace_on( $type, $args, $assoc_args ) {
		$this->get_options();
		
		$this->set_type( $type );
		$this->set_type_suffix( $type );
		$this->set_args( $args );
		$this->set_assoc_args( $assoc_args );
		
		// No need to get_option for trace
		$this->set_option( "trace", true, "on" );
		
		$trace_file = $this->get_option( "file", false );
		$this->set_option( "file", true, $trace_file );
		
		$reset = $this->get_option( "reset", false );
		$this->set_option( "reset", true, $reset );
		
		$limit = $this->get_option( "limit", false );
		$this->set_option( "limit", true, $limit );
		
		$this->update_options();
	}
	
	/**
	 * Turns tracing off for the given type
	 */
	private function trace_off( $type, $args, $assoc_args ) {
		$this->get_options();
		$this->set_type( $type );
		$this->set_type_suffix( $type );
		
		// No need to get_option for trace
		$this->set_option( "trace", true, "0" );
		
		$this->update_options();
	}
	
		
	
	/**
	 * Gets the option value from assoc_args
	 * 
	 * $args can potentially contain $assoc_args where the user forgot the -- prefix to the arg name
	 * should we just create assoc_args from these?
	 *
	 */
	private function get_option( $key, $use_suffix=false ) {
		$index = $key;
		if ( $use_suffix ) {
			$index .= $this->suffix;
		}
		$value = bw_array_get( $this->assoc_args, $index, null );
		return $value;
	
	}
	
	/**
	 * Sets an option value.
	 * 
	 * @param string $key - option name
	 * @param bool $use_suffix -  true when the suffix is required
	 * @param string $default - default value
	 */
	private function set_option( $key, $use_suffix=false, $value=null ) {
		$index = $key;
		if ( $use_suffix ) {
			$index .= $this->suffix;
		}
		if ( null !== $value ) {
			$this->options[ $index ] =  $value;
		} 
	}
	
	/**
	 * Turns tracing off for the given type
	 */	
	public function off( $args, $assoc_args ) {
		WP_CLI::log( "Turning trace off" );
		$type = bw_array_get( $args, 0, null );
		if ( $type ) {
			$type = $this->validate_type( $type ); 
			WP_CLI::log( "Disabling trace type: $type" );
		} else { 
			$type = 'browser';
			WP_CLI::log( "Disabling default trace type: browser" );
		}
		$this->trace_off( $type, $args, $assoc_args );
	}
	
	private function get_options() {
		$this->options = get_option('bw_trace_options');
	}
	
	private function update_options() {
		update_option( 'bw_trace_options', $this->options );
	}
	
	/**
	 * Displays an option value
	 *
	 * Should use the form necessary for updating it...
	 * 
	 */
	private function display_option( $label, $key, $type=null ) {
		$fields = array();
		$fields[] = "--" . $key . "=";
		$fields[] = bw_array_get( $this->options, $key, null );
		$fields[] = " ";
		$fields[] = $label;
		$line = implode( "", $fields );
		WP_CLI::log( $line  ); 
	}
	
	 
	/**
	 * Displays trace status.
	 *
	 * @TODO Complete the docblock
	 * @TODO and the code
	 *
	 * @TODO log or line? How to do PHP_EOL?
	 */
	public function status( $args, $assoc_args ) {
	
		$tracing = bw_trace_status();
		if ( $tracing ) {
			WP_CLI::log( "Batch tracing is on." ); 
		} else {
			WP_CLI::log( "Batch tracing is off." );
		}
		
		$this->get_options(); // = get_option('bw_trace_options');
		
		WP_CLI::log( "General browser requests" );
		$this->display_option( __( "Trace file", "oik-bwtrace" ), "file" );
		$this->display_option( __( "Trace enabled", "oik-bwtrace" ), "trace" );
		$this->display_option( __( "Reset trace file for every transaction", "oik-bwtrace" ), "reset" );
		$this->display_option( __( "Trace file generation limit", "oik-bwtrace" ), "limit" );
		
		WP_CLI::log( "Ajax requests" );
		$this->display_option( __( "Ajax trace file", "oik-bwtrace" ), "file_ajax" );
		$this->display_option( __( "Ajax trace enabled", "oik-bwtrace" ), "trace_ajax" );
		$this->display_option( __( "Reset Ajax trace file for every Ajax transaction", "oik-bwtrace" ), "reset_ajax" );
		$this->display_option( __( "Ajax trace file generation limit", "oik-bwtrace" ), "limit_ajax" );
		
		WP_CLI::log( __( "REST requests", "oik-bwtrace" ) );
		$this->display_option( __( "REST trace file", "oik-bwtrace" ), 'file_rest' );
		$this->display_option( __( "REST trace enabled", "oik-bwtrace" ), 'trace_rest' );
		$this->display_option( __( "Reset REST trace file for every REST transaction", "oik-bwtrace" ), 'reset_rest' );
		$this->display_option( __( "REST trace file generation limit", "oik-bwtrace" ), 'limit_rest' ); 
		
		
		WP_CLI::log( __( "Batch requests", "oik-bwtrace" ) );
		$this->display_option( __( "Batch trace file", "oik-bwtrace" ), 'file_cli' );
		$this->display_option( __( "Batch trace enabled", "oik-bwtrace" ), 'trace_cli' );
		$this->display_option( __( "Reset batch trace file on each invocation", "oik-bwtrace" ), 'reset_cli' );
		$this->display_option( __( "Batch trace file generation limit", "oik-bwtrace" ), 'limit_cli' );
		 
		$this->set_args( $args );
		//$this->set_assoc_args( $assoc_args );
		//print_r( $this->args );
		
		$details = bw_array_get( $this->args, 0, null );
		if ( $details ) {
			$this->display_record();
		}
	
	}
	
	/**
	 * Displays trace record options
	 * 
	 * 
	 */
	public function detail( $args, $assoc_args ) {
		
		$this->get_options(); // = get_option('bw_trace_options');
		$this->display_record();
	}
	
	private function display_record() {
		WP_CLI::log( __( "Trace records", "oik-bwtrace" ) );
		// $trace_levels = bw_list_trace_levels();
		$this->display_option( __( "Trace level", "oik-bwtrace" ), 'level' );
		$this->display_option( __( "Fully qualified file names", "oik-bwtrace" ), 'qualified' );
		$this->display_option( __( "Include trace record count", "oik-bwtrace" ), 'count' );
		$this->display_option( __( "Include timestamp", "oik-bwtrace" ), 'date' );
		$this->display_option( __( "Include current filter", "oik-bwtrace" ), 'filters' );
		$this->display_option( __( "Include number of queries", "oik-bwtrace" ), "num_queries" );
		$this->display_option( __( "Include post ID", "oik-bwtrace" ), "post_id" );
		$memory_limit = ini_get( "memory_limit" );
		$this->display_option( sprintf( __( 'Include memory/peak usage ( limit %1$s )', "oik-bwtrace" ), $memory_limit ), 'memory' );
		$this->display_option( __( "Include files loaded count", "oik-bwtrace" ), 'files' );
		$this->display_option( __( "Trace specific IP", "oik-bwtrace" ), 'ip' );
	}


}
