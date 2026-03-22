<?php
/**
 * WordPress Mock Functions for Testing
 *
 * @package Ecwid_WooCommerce_Sync
 */

// Global variables for testing
global $wp_options, $wp_remote_responses, $wp_users, $wpdb;

$wp_options          = array();
$wp_remote_responses = array();
$wp_users            = array();

/**
 * Mock wpdb class
 */
class wpdb {
    public $prefix = 'wp_';
    public $postmeta = 'wp_postmeta';
    public $usermeta = 'wp_usermeta';
    
    private $results = array();
    
    public function prepare( $query, ...$args ) {
        return vsprintf( str_replace( '%s', "'%s'", $query ), $args );
    }
    
    public function get_var( $query ) {
        return null;
    }
    
    public function get_results( $query ) {
        return array();
    }
    
    public function insert( $table, $data, $format = null ) {
        return true;
    }
    
    public function update( $table, $data, $where, $format = null, $where_format = null ) {
        return true;
    }
    
    public function replace( $table, $data, $format = null ) {
        return true;
    }
    
    public function query( $query ) {
        return true;
    }
    
    public function get_charset_collate() {
        return 'utf8mb4_unicode_ci';
    }
}

$wpdb = new wpdb();

/**
 * Get option
 */
if ( ! function_exists( 'get_option' ) ) {
    function get_option( $option, $default = false ) {
        global $wp_options;
        return isset( $wp_options[ $option ] ) ? $wp_options[ $option ] : $default;
    }
}

/**
 * Update option
 */
if ( ! function_exists( 'update_option' ) ) {
    function update_option( $option, $value, $autoload = null ) {
        global $wp_options;
        $wp_options[ $option ] = $value;
        return true;
    }
}

/**
 * Add option
 */
if ( ! function_exists( 'add_option' ) ) {
    function add_option( $option, $value = '', $deprecated = '', $autoload = 'yes' ) {
        global $wp_options;
        if ( ! isset( $wp_options[ $option ] ) ) {
            $wp_options[ $option ] = $value;
            return true;
        }
        return false;
    }
}

/**
 * Delete option
 */
if ( ! function_exists( 'delete_option' ) ) {
    function delete_option( $option ) {
        global $wp_options;
        if ( isset( $wp_options[ $option ] ) ) {
            unset( $wp_options[ $option ] );
            return true;
        }
        return false;
    }
}

/**
 * WP Error class
 */
if ( ! class_exists( 'WP_Error' ) ) {
    class WP_Error {
        protected $errors = array();
        protected $error_data = array();
        
        public function __construct( $code = '', $message = '', $data = '' ) {
            if ( ! empty( $code ) ) {
                $this->errors[ $code ][] = $message;
                if ( ! empty( $data ) ) {
                    $this->error_data[ $code ] = $data;
                }
            }
        }
        
        public function get_error_code() {
            $codes = $this->get_error_codes();
            return $codes[0] ?? '';
        }
        
        public function get_error_codes() {
            return array_keys( $this->errors );
        }
        
        public function get_error_message( $code = '' ) {
            if ( empty( $code ) ) {
                $code = $this->get_error_code();
            }
            return $this->errors[ $code ][0] ?? '';
        }
        
        public function get_error_data( $code = '' ) {
            if ( empty( $code ) ) {
                $code = $this->get_error_code();
            }
            return $this->error_data[ $code ] ?? null;
        }
        
        public function add( $code, $message, $data = '' ) {
            $this->errors[ $code ][] = $message;
            if ( ! empty( $data ) ) {
                $this->error_data[ $code ] = $data;
            }
        }
    }
}

/**
 * Check if WP_Error
 */
if ( ! function_exists( 'is_wp_error' ) ) {
    function is_wp_error( $thing ) {
        return ( $thing instanceof WP_Error );
    }
}

/**
 * Mock wp_remote_request
 */
if ( ! function_exists( 'wp_remote_request' ) ) {
    function wp_remote_request( $url, $args = array() ) {
        global $wp_remote_responses;
        
        // Return mock response if set
        if ( isset( $wp_remote_responses[ $url ] ) ) {
            return $wp_remote_responses[ $url ];
        }
        
        // Default mock response
        return array(
            'response' => array( 'code' => 200 ),
            'body'     => '{}',
        );
    }
}

/**
 * Mock wp_remote_get
 */
if ( ! function_exists( 'wp_remote_get' ) ) {
    function wp_remote_get( $url, $args = array() ) {
        return wp_remote_request( $url, array_merge( $args, array( 'method' => 'GET' ) ) );
    }
}

/**
 * Mock wp_remote_post
 */
if ( ! function_exists( 'wp_remote_post' ) ) {
    function wp_remote_post( $url, $args = array() ) {
        return wp_remote_request( $url, array_merge( $args, array( 'method' => 'POST' ) ) );
    }
}

/**
 * Get response code
 */
if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
    function wp_remote_retrieve_response_code( $response ) {
        if ( is_wp_error( $response ) ) {
            return 0;
        }
        return $response['response']['code'] ?? 0;
    }
}

/**
 * Get response body
 */
if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
    function wp_remote_retrieve_body( $response ) {
        if ( is_wp_error( $response ) ) {
            return '';
        }
        return $response['body'] ?? '';
    }
}

/**
 * JSON encode
 */
if ( ! function_exists( 'wp_json_encode' ) ) {
    function wp_json_encode( $data, $options = 0, $depth = 512 ) {
        return json_encode( $data, $options, $depth );
    }
}

/**
 * Parse args
 */
if ( ! function_exists( 'wp_parse_args' ) ) {
    function wp_parse_args( $args, $defaults = array() ) {
        if ( is_object( $args ) ) {
            $parsed_args = get_object_vars( $args );
        } elseif ( is_array( $args ) ) {
            $parsed_args =& $args;
        } else {
            parse_str( $args, $parsed_args );
        }
        return array_merge( $defaults, $parsed_args );
    }
}

/**
 * Add query arg
 */
if ( ! function_exists( 'add_query_arg' ) ) {
    function add_query_arg( ...$args ) {
        if ( is_array( $args[0] ) ) {
            $params = $args[0];
            $url    = $args[1] ?? '';
        } else {
            $params = array( $args[0] => $args[1] );
            $url    = $args[2] ?? '';
        }
        
        $query = http_build_query( $params );
        $sep   = strpos( $url, '?' ) === false ? '?' : '&';
        
        return $url . $sep . $query;
    }
}

/**
 * Sanitize text field
 */
if ( ! function_exists( 'sanitize_text_field' ) ) {
    function sanitize_text_field( $str ) {
        return trim( strip_tags( $str ) );
    }
}

/**
 * Sanitize email
 */
if ( ! function_exists( 'sanitize_email' ) ) {
    function sanitize_email( $email ) {
        return filter_var( $email, FILTER_SANITIZE_EMAIL );
    }
}

/**
 * Sanitize user
 */
if ( ! function_exists( 'sanitize_user' ) ) {
    function sanitize_user( $username, $strict = false ) {
        return preg_replace( '/[^a-zA-Z0-9_]/', '', $username );
    }
}

/**
 * Sanitize textarea field
 */
if ( ! function_exists( 'sanitize_textarea_field' ) ) {
    function sanitize_textarea_field( $str ) {
        return trim( strip_tags( $str ) );
    }
}

/**
 * Current time
 */
if ( ! function_exists( 'current_time' ) ) {
    function current_time( $type, $gmt = 0 ) {
        if ( 'mysql' === $type ) {
            return date( 'Y-m-d H:i:s' );
        }
        return time();
    }
}

/**
 * Generate password
 */
if ( ! function_exists( 'wp_generate_password' ) ) {
    function wp_generate_password( $length = 12, $special_chars = true, $extra_special_chars = false ) {
        return substr( md5( time() . rand() ), 0, $length );
    }
}

/**
 * Apply filters
 */
if ( ! function_exists( 'apply_filters' ) ) {
    function apply_filters( $tag, $value, ...$args ) {
        return $value;
    }
}

/**
 * Do action
 */
if ( ! function_exists( 'do_action' ) ) {
    function do_action( $tag, ...$args ) {
        // No-op for testing
    }
}

/**
 * Add action
 */
if ( ! function_exists( 'add_action' ) ) {
    function add_action( $tag, $function_to_add, $priority = 10, $accepted_args = 1 ) {
        return true;
    }
}

/**
 * Add filter
 */
if ( ! function_exists( 'add_filter' ) ) {
    function add_filter( $tag, $function_to_add, $priority = 10, $accepted_args = 1 ) {
        return true;
    }
}

/**
 * Get user by
 */
if ( ! function_exists( 'get_user_by' ) ) {
    function get_user_by( $field, $value ) {
        global $wp_users;
        
        foreach ( $wp_users as $user ) {
            if ( isset( $user->$field ) && $user->$field === $value ) {
                return $user;
            }
        }
        
        return false;
    }
}

/**
 * Update user meta
 */
if ( ! function_exists( 'update_user_meta' ) ) {
    function update_user_meta( $user_id, $meta_key, $meta_value, $prev_value = '' ) {
        return true;
    }
}

/**
 * Get user meta
 */
if ( ! function_exists( 'get_user_meta' ) ) {
    function get_user_meta( $user_id, $key = '', $single = false ) {
        return $single ? '' : array();
    }
}

/**
 * Username exists
 */
if ( ! function_exists( 'username_exists' ) ) {
    function username_exists( $username ) {
        return false;
    }
}

/**
 * WP schedule event
 */
if ( ! function_exists( 'wp_schedule_event' ) ) {
    function wp_schedule_event( $timestamp, $recurrence, $hook, $args = array() ) {
        return true;
    }
}

/**
 * WP next scheduled
 */
if ( ! function_exists( 'wp_next_scheduled' ) ) {
    function wp_next_scheduled( $hook, $args = array() ) {
        return false;
    }
}

/**
 * WP unschedule event
 */
if ( ! function_exists( 'wp_unschedule_event' ) ) {
    function wp_unschedule_event( $timestamp, $hook, $args = array() ) {
        return true;
    }
}

/**
 * WP clear scheduled hook
 */
if ( ! function_exists( 'wp_clear_scheduled_hook' ) ) {
    function wp_clear_scheduled_hook( $hook, $args = array() ) {
        return 0;
    }
}

/**
 * Escape HTML
 */
if ( ! function_exists( 'esc_html' ) ) {
    function esc_html( $text ) {
        return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
    }
}

/**
 * Escape HTML translation
 */
if ( ! function_exists( 'esc_html__' ) ) {
    function esc_html__( $text, $domain = 'default' ) {
        return esc_html( $text );
    }
}

/**
 * Translation function
 */
if ( ! function_exists( '__' ) ) {
    function __( $text, $domain = 'default' ) {
        return $text;
    }
}

/**
 * Home URL
 */
if ( ! function_exists( 'home_url' ) ) {
    function home_url( $path = '' ) {
        return 'http://example.com' . $path;
    }
}

/**
 * REST URL
 */
if ( ! function_exists( 'rest_url' ) ) {
    function rest_url( $path = '' ) {
        return 'http://example.com/wp-json/' . ltrim( $path, '/' );
    }
}

/**
 * Register REST route
 */
if ( ! function_exists( 'register_rest_route' ) ) {
    function register_rest_route( $namespace, $route, $args = array(), $override = false ) {
        return true;
    }
}

/**
 * REST ensure response
 */
if ( ! function_exists( 'rest_ensure_response' ) ) {
    function rest_ensure_response( $response ) {
        return $response;
    }
}

/**
 * Add rewrite rule
 */
if ( ! function_exists( 'add_rewrite_rule' ) ) {
    function add_rewrite_rule( $regex, $query, $after = 'bottom' ) {
        return true;
    }
}

/**
 * Get query var
 */
if ( ! function_exists( 'get_query_var' ) ) {
    function get_query_var( $var, $default = '' ) {
        return $default;
    }
}

/**
 * Hash equals
 */
if ( ! function_exists( 'hash_equals' ) ) {
    function hash_equals( $known_string, $user_string ) {
        return $known_string === $user_string;
    }
}

/**
 * Current user can
 */
if ( ! function_exists( 'current_user_can' ) ) {
    function current_user_can( $capability, ...$args ) {
        return true;
    }
}

/**
 * WP date
 */
if ( ! function_exists( 'wp_date' ) ) {
    function wp_date( $format, $timestamp = null, $timezone = null ) {
        return date( $format, $timestamp ?? time() );
    }
}

/**
 * Set mock response for wp_remote_request
 */
function set_mock_response( $url, $response ) {
    global $wp_remote_responses;
    $wp_remote_responses[ $url ] = $response;
}

/**
 * Clear all mock responses
 */
function clear_mock_responses() {
    global $wp_remote_responses;
    $wp_remote_responses = array();
}

/**
 * Set mock option
 */
function set_mock_option( $option, $value ) {
    global $wp_options;
    $wp_options[ $option ] = $value;
}

/**
 * Clear all mock options
 */
function clear_mock_options() {
    global $wp_options;
    $wp_options = array();
}
