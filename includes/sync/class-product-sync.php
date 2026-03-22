<?php
/**
 * Product Sync Service
 *
 * @package Ecwid_WooCommerce
 * @since   1.0.0
 */

namespace Ecwid_WooCommerce\Sync;

use Ecwid_WooCommerce\Api\Ecwid_Api;
use Ecwid_WooCommerce\Mappers\Product_Mapper;
use Ecwid_WooCommerce\Utils\Logger;
use Ecwid_WooCommerce\Utils\Mapping_Repository;

defined( 'ABSPATH' ) || exit;

/**
 * Handles product synchronization from WooCommerce to Ecwid.
 *
 * @since 1.0.0
 */
class Product_Sync {

    /**
     * API client.
     *
     * @var Ecwid_Api
     */
    private $api;

    /**
     * Product mapper.
     *
     * @var Product_Mapper
     */
    private $mapper;

    /**
     * Mapping repository.
     *
     * @var Mapping_Repository
     */
    private $mapping_repo;

    /**
     * Logger.
     *
     * @var Logger
     */
    private $logger;

    /**
     * Batch size for processing.
     *
     * @var int
     */
    private $batch_size;

    /**
     * Sync results.
     *
     * @var array
     */
    private $results = array(
        'created' => 0,
        'updated' => 0,
        'deleted' => 0,
        'skipped' => 0,
        'errors'  => 0,
    );

    /**
     * Error details.
     *
     * @var array
     */
    private $error_details = array();

    /**
     * Constructor.
     */
    public function __construct() {
        $this->api          = Ecwid_Api::get_instance();
        $this->mapper       = new Product_Mapper();
        $this->mapping_repo = Mapping_Repository::get_instance();
        $this->logger       = Logger::get_instance();
        $this->batch_size   = (int) get_option( 'ecwid_wc_sync_batch_size', 50 );
    }

    /**
     * Export a single WooCommerce product to Ecwid.
     *
     * @param int|\WC_Product $product WooCommerce product ID or object.
     * @return array{success: bool, ecwid_id: int|null, message: string}
     */
    public function export_product( $product ) {
        if ( is_numeric( $product ) ) {
            $product = wc_get_product( $product );
        }

        if ( ! $product || ! $product instanceof \WC_Product ) {
            return array(
                'success'  => false,
                'ecwid_id' => null,
                'message'  => __( 'Invalid product.', 'ecwid-woocommerce' ),
            );
        }

        $wc_id = $product->get_id();

        $this->logger->debug(
            sprintf( 'Starting export of WC product #%d', $wc_id ),
            array( 'name' => $product->get_name() ),
            'Product_Sync'
        );

        // Convert to Ecwid format.
        $ecwid_data = $this->mapper->wc_to_ecwid( $product );

        // Validate data.
        $validation = $this->mapper->validate_ecwid_data( $ecwid_data );
        if ( ! $validation['valid'] ) {
            $error_msg = implode( ' ', $validation['errors'] );
            $this->logger->warning(
                sprintf( 'Validation failed for WC product #%d: %s', $wc_id, $error_msg ),
                array(),
                'Product_Sync'
            );

            return array(
                'success'  => false,
                'ecwid_id' => null,
                'message'  => $error_msg,
            );
        }

        // Check if product already exists in Ecwid.
        $mapping = $this->mapping_repo->find_by_wc_id( 'product', $wc_id );

        if ( $mapping && ! empty( $mapping->ecwid_id ) ) {
            // Update existing product.
            return $this->update_ecwid_product( $wc_id, (int) $mapping->ecwid_id, $ecwid_data, $product );
        } else {
            // Create new product.
            return $this->create_ecwid_product( $wc_id, $ecwid_data, $product );
        }
    }

    /**
     * Create a new product in Ecwid.
     *
     * @param int         $wc_id      WooCommerce product ID.
     * @param array       $ecwid_data Ecwid product data.
     * @param \WC_Product $product    WooCommerce product object.
     * @return array{success: bool, ecwid_id: int|null, message: string}
     */
    private function create_ecwid_product( $wc_id, $ecwid_data, $product ) {
        $create_data = $this->mapper->prepare_for_create( $ecwid_data );

        // Remove image URLs - we'll upload them separately.
        $image_url      = $create_data['imageUrl'] ?? null;
        $gallery_images = $create_data['galleryImages'] ?? array();
        unset( $create_data['imageUrl'], $create_data['galleryImages'] );

        $response = $this->api->create_product( $create_data );

        if ( is_wp_error( $response ) ) {
            $this->handle_api_error( $wc_id, $response );

            return array(
                'success'  => false,
                'ecwid_id' => null,
                'message'  => $response->get_error_message(),
            );
        }

        $ecwid_id = $response['id'] ?? null;

        if ( ! $ecwid_id ) {
            return array(
                'success'  => false,
                'ecwid_id' => null,
                'message'  => __( 'Failed to get Ecwid product ID from response.', 'ecwid-woocommerce' ),
            );
        }

        // Save mapping.
        $this->mapping_repo->save( 'product', $wc_id, $ecwid_id, array(
            'sync_direction'  => 'wc_to_ecwid',
            'wc_updated_at'   => $product->get_date_modified() ? $product->get_date_modified()->format( 'Y-m-d H:i:s' ) : null,
        ) );

        // Upload images.
        $this->upload_product_images( $ecwid_id, $image_url, $gallery_images );

        $this->results['created']++;

        $this->logger->info(
            sprintf( 'Created Ecwid product #%d from WC product #%d', $ecwid_id, $wc_id ),
            array( 'sku' => $ecwid_data['sku'] ?? '' ),
            'Product_Sync'
        );

        return array(
            'success'  => true,
            'ecwid_id' => $ecwid_id,
            'message'  => sprintf( __( 'Product created in Ecwid (ID: %d)', 'ecwid-woocommerce' ), $ecwid_id ),
        );
    }

    /**
     * Update an existing product in Ecwid.
     *
     * @param int         $wc_id      WooCommerce product ID.
     * @param int         $ecwid_id   Ecwid product ID.
     * @param array       $ecwid_data Ecwid product data.
     * @param \WC_Product $product    WooCommerce product object.
     * @return array{success: bool, ecwid_id: int|null, message: string}
     */
    private function update_ecwid_product( $wc_id, $ecwid_id, $ecwid_data, $product ) {
        $update_data = $this->mapper->prepare_for_update( $ecwid_data );

        // Handle images separately.
        $image_url      = $update_data['imageUrl'] ?? null;
        $gallery_images = $update_data['galleryImages'] ?? array();
        unset( $update_data['imageUrl'], $update_data['galleryImages'] );

        $response = $this->api->update_product( $ecwid_id, $update_data );

        if ( is_wp_error( $response ) ) {
            // Check if product doesn't exist in Ecwid anymore.
            if ( strpos( $response->get_error_code(), '404' ) !== false ) {
                $this->logger->warning(
                    sprintf( 'Ecwid product #%d not found, will recreate', $ecwid_id ),
                    array(),
                    'Product_Sync'
                );

                // Delete mapping and create new product.
                $this->mapping_repo->delete_by_wc_id( 'product', $wc_id );
                return $this->create_ecwid_product( $wc_id, $ecwid_data, $product );
            }

            $this->handle_api_error( $wc_id, $response );

            return array(
                'success'  => false,
                'ecwid_id' => $ecwid_id,
                'message'  => $response->get_error_message(),
            );
        }

        // Update mapping.
        $mapping = $this->mapping_repo->find_by_wc_id( 'product', $wc_id );
        if ( $mapping ) {
            $this->mapping_repo->mark_synced( $mapping->id );
        }

        // Update images if needed.
        if ( $image_url || ! empty( $gallery_images ) ) {
            $this->upload_product_images( $ecwid_id, $image_url, $gallery_images );
        }

        $this->results['updated']++;

        $this->logger->info(
            sprintf( 'Updated Ecwid product #%d from WC product #%d', $ecwid_id, $wc_id ),
            array( 'fields' => array_keys( $update_data ) ),
            'Product_Sync'
        );

        return array(
            'success'  => true,
            'ecwid_id' => $ecwid_id,
            'message'  => sprintf( __( 'Product updated in Ecwid (ID: %d)', 'ecwid-woocommerce' ), $ecwid_id ),
        );
    }

    /**
     * Delete a product from Ecwid.
     *
     * @param int $wc_id WooCommerce product ID.
     * @return array{success: bool, message: string}
     */
    public function delete_product( $wc_id ) {
        $mapping = $this->mapping_repo->find_by_wc_id( 'product', $wc_id );

        if ( ! $mapping || empty( $mapping->ecwid_id ) ) {
            return array(
                'success' => true,
                'message' => __( 'Product not found in Ecwid mapping.', 'ecwid-woocommerce' ),
            );
        }

        $ecwid_id = (int) $mapping->ecwid_id;

        $response = $this->api->delete_product( $ecwid_id );

        if ( is_wp_error( $response ) ) {
            // If product doesn't exist, still remove mapping.
            if ( strpos( $response->get_error_code(), '404' ) !== false ) {
                $this->mapping_repo->delete( $mapping->id );
                return array(
                    'success' => true,
                    'message' => __( 'Product already deleted from Ecwid.', 'ecwid-woocommerce' ),
                );
            }

            $this->handle_api_error( $wc_id, $response );

            return array(
                'success' => false,
                'message' => $response->get_error_message(),
            );
        }

        // Remove mapping.
        $this->mapping_repo->delete( $mapping->id );

        $this->results['deleted']++;

        $this->logger->info(
            sprintf( 'Deleted Ecwid product #%d (WC product #%d)', $ecwid_id, $wc_id ),
            array(),
            'Product_Sync'
        );

        return array(
            'success' => true,
            'message' => sprintf( __( 'Product deleted from Ecwid (ID: %d)', 'ecwid-woocommerce' ), $ecwid_id ),
        );
    }

    /**
     * Upload product images to Ecwid.
     *
     * @param int         $ecwid_id       Ecwid product ID.
     * @param string|null $main_image_url Main image URL.
     * @param array       $gallery_images Gallery image URLs.
     * @return void
     */
    private function upload_product_images( $ecwid_id, $main_image_url, $gallery_images = array() ) {
        // Upload main image.
        if ( $main_image_url ) {
            $response = $this->api->upload_product_image( $ecwid_id, $main_image_url );
            if ( is_wp_error( $response ) ) {
                $this->logger->warning(
                    sprintf( 'Failed to upload main image for Ecwid product #%d: %s', $ecwid_id, $response->get_error_message() ),
                    array(),
                    'Product_Sync'
                );
            }
        }

        // Upload gallery images.
        if ( ! empty( $gallery_images ) ) {
            foreach ( $gallery_images as $image ) {
                $url = is_array( $image ) ? ( $image['url'] ?? '' ) : $image;
                if ( $url ) {
                    $response = $this->api->upload_gallery_image( $ecwid_id, $url );
                    if ( is_wp_error( $response ) ) {
                        $this->logger->warning(
                            sprintf( 'Failed to upload gallery image for Ecwid product #%d', $ecwid_id ),
                            array( 'url' => $url ),
                            'Product_Sync'
                        );
                    }
                }
            }
        }
    }

    /**
     * Export multiple products in batch.
     *
     * @param array $product_ids   Array of WooCommerce product IDs.
     * @param bool  $stop_on_error Stop processing on first error.
     * @return array{success: bool, results: array, errors: array}
     */
    public function export_batch( $product_ids, $stop_on_error = false ) {
        $this->reset_results();

        $total   = count( $product_ids );
        $current = 0;

        $this->logger->info(
            sprintf( 'Starting batch export of %d products', $total ),
            array(),
            'Product_Sync'
        );

        foreach ( $product_ids as $product_id ) {
            $current++;

            $result = $this->export_product( $product_id );

            if ( ! $result['success'] ) {
                $this->error_details[] = array(
                    'wc_id'   => $product_id,
                    'message' => $result['message'],
                );

                if ( $stop_on_error ) {
                    break;
                }
            }

            // Allow processing to be interrupted.
            if ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) {
                // Check if we're running low on time.
                if ( $this->is_time_limit_reached() ) {
                    $this->logger->warning(
                        sprintf( 'Batch export stopped due to time limit after %d/%d products', $current, $total ),
                        array(),
                        'Product_Sync'
                    );
                    break;
                }
            }
        }

        $this->logger->info(
            sprintf(
                'Batch export completed: %d created, %d updated, %d errors',
                $this->results['created'],
                $this->results['updated'],
                $this->results['errors']
            ),
            array(),
            'Product_Sync'
        );

        return array(
            'success' => empty( $this->error_details ),
            'results' => $this->results,
            'errors'  => $this->error_details,
        );
    }

    /**
     * Export all WooCommerce products to Ecwid.
     *
     * @param array $args Query arguments.
     * @return array{success: bool, results: array, errors: array}
     */
    public function export_all( $args = array() ) {
        $defaults = array(
            'status'      => 'publish',
            'limit'       => -1,
            'type'        => array( 'simple', 'variable' ),
            'order'       => 'ASC',
            'orderby'     => 'ID',
        );

        $args = wp_parse_args( $args, $defaults );

        // Get all product IDs.
        $products = wc_get_products( array_merge( $args, array(
            'return' => 'ids',
        ) ) );

        if ( empty( $products ) ) {
            return array(
                'success' => true,
                'results' => $this->results,
                'errors'  => array(),
            );
        }

        $this->logger->info(
            sprintf( 'Starting full export of %d products', count( $products ) ),
            array(),
            'Product_Sync'
        );

        // Process in batches.
        $batches = array_chunk( $products, $this->batch_size );

        foreach ( $batches as $batch ) {
            $this->export_batch( $batch );
        }

        return array(
            'success' => empty( $this->error_details ),
            'results' => $this->results,
            'errors'  => $this->error_details,
        );
    }

    /**
     * Sync product stock to Ecwid.
     *
     * @param int $wc_id    WooCommerce product ID.
     * @param int $quantity New stock quantity.
     * @return array{success: bool, message: string}
     */
    public function sync_stock( $wc_id, $quantity ) {
        $mapping = $this->mapping_repo->find_by_wc_id( 'product', $wc_id );

        if ( ! $mapping || empty( $mapping->ecwid_id ) ) {
            return array(
                'success' => false,
                'message' => __( 'Product not found in Ecwid mapping.', 'ecwid-woocommerce' ),
            );
        }

        $ecwid_id = (int) $mapping->ecwid_id;

        // Update stock via API.
        $response = $this->api->update_product( $ecwid_id, array(
            'quantity'  => $quantity,
            'unlimited' => false,
        ) );

        if ( is_wp_error( $response ) ) {
            return array(
                'success' => false,
                'message' => $response->get_error_message(),
            );
        }

        $this->logger->debug(
            sprintf( 'Synced stock for Ecwid product #%d: %d', $ecwid_id, $quantity ),
            array(),
            'Product_Sync'
        );

        return array(
            'success' => true,
            'message' => sprintf( __( 'Stock updated to %d', 'ecwid-woocommerce' ), $quantity ),
        );
    }

    /**
     * Handle API error.
     *
     * @param int       $wc_id WooCommerce product ID.
     * @param \WP_Error $error WP_Error object.
     * @return void
     */
    private function handle_api_error( $wc_id, $error ) {
        $this->results['errors']++;

        $mapping = $this->mapping_repo->find_by_wc_id( 'product', $wc_id );
        if ( $mapping ) {
            $this->mapping_repo->mark_error( $mapping->id, $error->get_error_message() );
        }

        $this->logger->error(
            sprintf( 'API error for WC product #%d: %s', $wc_id, $error->get_error_message() ),
            array( 'error_code' => $error->get_error_code() ),
            'Product_Sync'
        );
    }

    /**
     * Reset sync results.
     *
     * @return void
     */
    private function reset_results() {
        $this->results = array(
            'created' => 0,
            'updated' => 0,
            'deleted' => 0,
            'skipped' => 0,
            'errors'  => 0,
        );
        $this->error_details = array();
    }

    /**
     * Check if time limit is reached.
     *
     * @return bool
     */
    private function is_time_limit_reached() {
        $max_execution_time = (int) ini_get( 'max_execution_time' );

        if ( 0 === $max_execution_time ) {
            return false;
        }

        // Leave 10 seconds buffer.
        $time_elapsed = microtime( true ) - $_SERVER['REQUEST_TIME_FLOAT'];

        return $time_elapsed > ( $max_execution_time - 10 );
    }

    /**
     * Get sync results.
     *
     * @return array
     */
    public function get_results() {
        return $this->results;
    }

    /**
     * Get error details.
     *
     * @return array
     */
    public function get_errors() {
        return $this->error_details;
    }

    /**
     * Get batch size.
     *
     * @return int
     */
    public function get_batch_size() {
        return $this->batch_size;
    }

    /**
     * Set batch size.
     *
     * @param int $size Batch size.
     * @return void
     */
    public function set_batch_size( $size ) {
        $this->batch_size = max( 1, (int) $size );
    }
}
