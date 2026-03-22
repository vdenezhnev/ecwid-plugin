<?php
/**
 * Product Mapper Class
 *
 * @package Ecwid_WooCommerce
 * @since   1.0.0
 */

namespace Ecwid_WooCommerce\Mappers;

use Ecwid_WooCommerce\Utils\Logger;

defined( 'ABSPATH' ) || exit;

/**
 * Maps product data between WooCommerce and Ecwid formats.
 *
 * @since 1.0.0
 */
class Product_Mapper {

    /**
     * Logger instance.
     *
     * @var Logger
     */
    private $logger;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->logger = Logger::get_instance();
    }

    /**
     * Convert WooCommerce product to Ecwid format.
     *
     * @param \WC_Product $product WooCommerce product.
     * @param array       $options Mapping options.
     * @return array Ecwid product data.
     */
    public function wc_to_ecwid( $product, $options = array() ) {
        $defaults = array(
            'include_images'     => true,
            'include_variations' => true,
            'include_categories' => true,
        );
        $options = wp_parse_args( $options, $defaults );

        $ecwid_data = array(
            'sku'         => $product->get_sku() ?: $this->generate_sku( $product ),
            'name'        => $product->get_name(),
            'description' => $product->get_description(),
            'enabled'     => $product->get_status() === 'publish',
        );

        // Price handling.
        $this->map_prices_to_ecwid( $product, $ecwid_data );

        // Stock handling.
        $this->map_stock_to_ecwid( $product, $ecwid_data );

        // Weight and dimensions.
        $this->map_dimensions_to_ecwid( $product, $ecwid_data );

        // Tax settings.
        $this->map_tax_to_ecwid( $product, $ecwid_data );

        // Categories.
        if ( $options['include_categories'] ) {
            $this->map_categories_to_ecwid( $product, $ecwid_data );
        }

        // Images.
        if ( $options['include_images'] ) {
            $this->map_images_to_ecwid( $product, $ecwid_data );
        }

        // Attributes/Options.
        $this->map_attributes_to_ecwid( $product, $ecwid_data );

        // Variations (for variable products).
        if ( $options['include_variations'] && $product->is_type( 'variable' ) ) {
            $this->map_variations_to_ecwid( $product, $ecwid_data );
        }

        // SEO data.
        $this->map_seo_to_ecwid( $product, $ecwid_data );

        // Related products.
        $this->map_related_to_ecwid( $product, $ecwid_data );

        // Additional meta.
        $ecwid_data['showOnFrontpage'] = $product->is_featured();

        // Allow filtering.
        $ecwid_data = apply_filters( 'ecwid_wc_product_to_ecwid', $ecwid_data, $product, $options );

        $this->logger->debug(
            sprintf( 'Mapped WC product #%d to Ecwid format', $product->get_id() ),
            array( 'sku' => $ecwid_data['sku'] ),
            'Product_Mapper'
        );

        return $ecwid_data;
    }

    /**
     * Map prices to Ecwid format.
     *
     * @param \WC_Product $product    WooCommerce product.
     * @param array       $ecwid_data Ecwid data array (by reference).
     * @return void
     */
    private function map_prices_to_ecwid( $product, &$ecwid_data ) {
        $regular_price = $product->get_regular_price();
        $sale_price    = $product->get_sale_price();

        if ( ! empty( $sale_price ) && $sale_price < $regular_price ) {
            $ecwid_data['price']          = (float) $sale_price;
            $ecwid_data['compareToPrice'] = (float) $regular_price;
        } else {
            $ecwid_data['price'] = ! empty( $regular_price ) ? (float) $regular_price : 0;
        }
    }

    /**
     * Map stock to Ecwid format.
     *
     * @param \WC_Product $product    WooCommerce product.
     * @param array       $ecwid_data Ecwid data array (by reference).
     * @return void
     */
    private function map_stock_to_ecwid( $product, &$ecwid_data ) {
        $manage_stock = $product->get_manage_stock();

        if ( $manage_stock ) {
            $ecwid_data['unlimited'] = false;
            $ecwid_data['quantity']  = (int) $product->get_stock_quantity();
        } else {
            $ecwid_data['unlimited'] = true;
        }

        $ecwid_data['inStock'] = $product->is_in_stock();
    }

    /**
     * Map dimensions to Ecwid format.
     *
     * @param \WC_Product $product    WooCommerce product.
     * @param array       $ecwid_data Ecwid data array (by reference).
     * @return void
     */
    private function map_dimensions_to_ecwid( $product, &$ecwid_data ) {
        $weight = $product->get_weight();
        if ( ! empty( $weight ) ) {
            $ecwid_data['weight'] = $this->convert_weight_to_ecwid( (float) $weight );
        }

        $length = $product->get_length();
        $width  = $product->get_width();
        $height = $product->get_height();

        if ( ! empty( $length ) || ! empty( $width ) || ! empty( $height ) ) {
            $ecwid_data['dimensions'] = array(
                'length' => ! empty( $length ) ? $this->convert_dimension_to_ecwid( (float) $length ) : 0,
                'width'  => ! empty( $width ) ? $this->convert_dimension_to_ecwid( (float) $width ) : 0,
                'height' => ! empty( $height ) ? $this->convert_dimension_to_ecwid( (float) $height ) : 0,
            );
        }
    }

    /**
     * Map tax settings to Ecwid format.
     *
     * @param \WC_Product $product    WooCommerce product.
     * @param array       $ecwid_data Ecwid data array (by reference).
     * @return void
     */
    private function map_tax_to_ecwid( $product, &$ecwid_data ) {
        $tax_status = $product->get_tax_status();
        $ecwid_data['isTaxable'] = ( 'taxable' === $tax_status );

        $tax_class = $product->get_tax_class();
        if ( ! empty( $tax_class ) ) {
            $ecwid_data['taxClassCode'] = $tax_class;
        }
    }

    /**
     * Map categories to Ecwid format.
     *
     * @param \WC_Product $product    WooCommerce product.
     * @param array       $ecwid_data Ecwid data array (by reference).
     * @return void
     */
    private function map_categories_to_ecwid( $product, &$ecwid_data ) {
        $category_ids = $product->get_category_ids();

        if ( ! empty( $category_ids ) ) {
            $ecwid_category_ids = array();

            foreach ( $category_ids as $wc_cat_id ) {
                $ecwid_cat_id = $this->get_ecwid_category_id( $wc_cat_id );
                if ( $ecwid_cat_id ) {
                    $ecwid_category_ids[] = $ecwid_cat_id;
                }
            }

            if ( ! empty( $ecwid_category_ids ) ) {
                $ecwid_data['categoryIds']      = $ecwid_category_ids;
                $ecwid_data['defaultCategoryId'] = $ecwid_category_ids[0];
            }
        }
    }

    /**
     * Map images to Ecwid format.
     *
     * @param \WC_Product $product    WooCommerce product.
     * @param array       $ecwid_data Ecwid data array (by reference).
     * @return void
     */
    private function map_images_to_ecwid( $product, &$ecwid_data ) {
        $image_id = $product->get_image_id();

        if ( $image_id ) {
            $image_url = wp_get_attachment_url( $image_id );
            if ( $image_url ) {
                $ecwid_data['imageUrl'] = $image_url;
            }
        }

        $gallery_ids = $product->get_gallery_image_ids();
        if ( ! empty( $gallery_ids ) ) {
            $gallery_images = array();
            foreach ( $gallery_ids as $gallery_id ) {
                $url = wp_get_attachment_url( $gallery_id );
                if ( $url ) {
                    $gallery_images[] = array(
                        'url' => $url,
                    );
                }
            }
            if ( ! empty( $gallery_images ) ) {
                $ecwid_data['galleryImages'] = $gallery_images;
            }
        }
    }

    /**
     * Map attributes to Ecwid options format.
     *
     * @param \WC_Product $product    WooCommerce product.
     * @param array       $ecwid_data Ecwid data array (by reference).
     * @return void
     */
    private function map_attributes_to_ecwid( $product, &$ecwid_data ) {
        $attributes = $product->get_attributes();

        if ( empty( $attributes ) ) {
            return;
        }

        $ecwid_options = array();

        foreach ( $attributes as $attribute ) {
            if ( $attribute instanceof \WC_Product_Attribute ) {
                $option = array(
                    'name'     => wc_attribute_label( $attribute->get_name() ),
                    'type'     => $attribute->get_variation() ? 'SELECT' : 'TEXTFIELD',
                    'required' => false,
                );

                $options = $attribute->get_options();
                if ( ! empty( $options ) ) {
                    $choices = array();
                    foreach ( $options as $opt ) {
                        if ( $attribute->is_taxonomy() ) {
                            $term = get_term( $opt );
                            $choice_text = $term ? $term->name : $opt;
                        } else {
                            $choice_text = $opt;
                        }
                        $choices[] = array(
                            'text'          => $choice_text,
                            'priceModifier' => 0,
                        );
                    }
                    $option['choices'] = $choices;
                }

                $ecwid_options[] = $option;
            }
        }

        if ( ! empty( $ecwid_options ) ) {
            $ecwid_data['options'] = $ecwid_options;
        }
    }

    /**
     * Map variations to Ecwid combinations format.
     *
     * @param \WC_Product_Variable $product    WooCommerce variable product.
     * @param array                $ecwid_data Ecwid data array (by reference).
     * @return void
     */
    private function map_variations_to_ecwid( $product, &$ecwid_data ) {
        $variations = $product->get_available_variations();

        if ( empty( $variations ) ) {
            return;
        }

        $combinations = array();

        foreach ( $variations as $variation_data ) {
            $variation = wc_get_product( $variation_data['variation_id'] );

            if ( ! $variation ) {
                continue;
            }

            $combination = array(
                'sku' => $variation->get_sku() ?: $this->generate_sku( $variation ),
            );

            // Price.
            $regular_price = $variation->get_regular_price();
            $sale_price    = $variation->get_sale_price();

            if ( ! empty( $sale_price ) && $sale_price < $regular_price ) {
                $combination['price']          = (float) $sale_price;
                $combination['compareToPrice'] = (float) $regular_price;
            } elseif ( ! empty( $regular_price ) ) {
                $combination['price'] = (float) $regular_price;
            }

            // Stock.
            if ( $variation->get_manage_stock() ) {
                $combination['unlimited'] = false;
                $combination['quantity']  = (int) $variation->get_stock_quantity();
            } else {
                $combination['unlimited'] = true;
            }

            // Weight.
            $weight = $variation->get_weight();
            if ( ! empty( $weight ) ) {
                $combination['weight'] = $this->convert_weight_to_ecwid( (float) $weight );
            }

            // Image.
            $image_id = $variation->get_image_id();
            if ( $image_id ) {
                $image_url = wp_get_attachment_url( $image_id );
                if ( $image_url ) {
                    $combination['imageUrl'] = $image_url;
                }
            }

            // Options (variation attributes).
            $attributes = $variation->get_attributes();
            if ( ! empty( $attributes ) ) {
                $options = array();
                foreach ( $attributes as $attr_name => $attr_value ) {
                    $options[] = array(
                        'name'  => wc_attribute_label( $attr_name ),
                        'value' => $attr_value,
                    );
                }
                $combination['options'] = $options;
            }

            $combinations[] = $combination;
        }

        if ( ! empty( $combinations ) ) {
            $ecwid_data['combinations'] = $combinations;
        }
    }

    /**
     * Map SEO data to Ecwid format.
     *
     * @param \WC_Product $product    WooCommerce product.
     * @param array       $ecwid_data Ecwid data array (by reference).
     * @return void
     */
    private function map_seo_to_ecwid( $product, &$ecwid_data ) {
        $product_id = $product->get_id();

        // Try Yoast SEO.
        $seo_title = get_post_meta( $product_id, '_yoast_wpseo_title', true );
        $seo_desc  = get_post_meta( $product_id, '_yoast_wpseo_metadesc', true );

        // Try Rank Math.
        if ( empty( $seo_title ) ) {
            $seo_title = get_post_meta( $product_id, 'rank_math_title', true );
        }
        if ( empty( $seo_desc ) ) {
            $seo_desc = get_post_meta( $product_id, 'rank_math_description', true );
        }

        if ( ! empty( $seo_title ) ) {
            $ecwid_data['seoTitle'] = $seo_title;
        }

        if ( ! empty( $seo_desc ) ) {
            $ecwid_data['seoDescription'] = $seo_desc;
        }
    }

    /**
     * Map related products to Ecwid format.
     *
     * @param \WC_Product $product    WooCommerce product.
     * @param array       $ecwid_data Ecwid data array (by reference).
     * @return void
     */
    private function map_related_to_ecwid( $product, &$ecwid_data ) {
        $upsell_ids    = $product->get_upsell_ids();
        $cross_sell_ids = $product->get_cross_sell_ids();

        $related_ecwid_ids = array();

        foreach ( array_merge( $upsell_ids, $cross_sell_ids ) as $wc_product_id ) {
            $ecwid_id = $this->get_ecwid_product_id( $wc_product_id );
            if ( $ecwid_id ) {
                $related_ecwid_ids[] = $ecwid_id;
            }
        }

        if ( ! empty( $related_ecwid_ids ) ) {
            $ecwid_data['relatedProducts'] = array(
                'productIds' => array_unique( $related_ecwid_ids ),
            );
        }
    }

    /**
     * Convert weight from WooCommerce to Ecwid units.
     *
     * Ecwid uses kg by default.
     *
     * @param float $weight Weight value.
     * @return float Converted weight.
     */
    private function convert_weight_to_ecwid( $weight ) {
        $wc_unit = get_option( 'woocommerce_weight_unit', 'kg' );

        switch ( $wc_unit ) {
            case 'g':
                return $weight / 1000;
            case 'lbs':
                return $weight * 0.453592;
            case 'oz':
                return $weight * 0.0283495;
            case 'kg':
            default:
                return $weight;
        }
    }

    /**
     * Convert dimension from WooCommerce to Ecwid units.
     *
     * Ecwid uses cm by default.
     *
     * @param float $dimension Dimension value.
     * @return float Converted dimension.
     */
    private function convert_dimension_to_ecwid( $dimension ) {
        $wc_unit = get_option( 'woocommerce_dimension_unit', 'cm' );

        switch ( $wc_unit ) {
            case 'm':
                return $dimension * 100;
            case 'mm':
                return $dimension / 10;
            case 'in':
                return $dimension * 2.54;
            case 'yd':
                return $dimension * 91.44;
            case 'cm':
            default:
                return $dimension;
        }
    }

    /**
     * Generate SKU for product without one.
     *
     * @param \WC_Product $product WooCommerce product.
     * @return string Generated SKU.
     */
    private function generate_sku( $product ) {
        return 'WC-' . $product->get_id();
    }

    /**
     * Get Ecwid category ID from WooCommerce category ID.
     *
     * @param int $wc_category_id WooCommerce category ID.
     * @return int|null Ecwid category ID or null if not found.
     */
    private function get_ecwid_category_id( $wc_category_id ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ecwid_wc_mapping';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $ecwid_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT ecwid_id FROM {$table_name} WHERE entity_type = 'category' AND wc_id = %d",
                $wc_category_id
            )
        );

        return $ecwid_id ? (int) $ecwid_id : null;
    }

    /**
     * Get Ecwid product ID from WooCommerce product ID.
     *
     * @param int $wc_product_id WooCommerce product ID.
     * @return int|null Ecwid product ID or null if not found.
     */
    private function get_ecwid_product_id( $wc_product_id ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ecwid_wc_mapping';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $ecwid_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT ecwid_id FROM {$table_name} WHERE entity_type = 'product' AND wc_id = %d",
                $wc_product_id
            )
        );

        return $ecwid_id ? (int) $ecwid_id : null;
    }

    /**
     * Validate Ecwid product data.
     *
     * @param array $data Ecwid product data.
     * @return array{valid: bool, errors: array}
     */
    public function validate_ecwid_data( $data ) {
        $errors = array();

        // Required fields.
        if ( empty( $data['name'] ) ) {
            $errors[] = __( 'Product name is required.', 'ecwid-woocommerce' );
        }

        // Price validation.
        if ( isset( $data['price'] ) && $data['price'] < 0 ) {
            $errors[] = __( 'Product price cannot be negative.', 'ecwid-woocommerce' );
        }

        // Quantity validation.
        if ( isset( $data['quantity'] ) && $data['quantity'] < 0 ) {
            $errors[] = __( 'Product quantity cannot be negative.', 'ecwid-woocommerce' );
        }

        // SKU length.
        if ( isset( $data['sku'] ) && strlen( $data['sku'] ) > 255 ) {
            $errors[] = __( 'Product SKU is too long (max 255 characters).', 'ecwid-woocommerce' );
        }

        return array(
            'valid'  => empty( $errors ),
            'errors' => $errors,
        );
    }

    /**
     * Prepare product data for Ecwid API create request.
     *
     * @param array $data Full product data.
     * @return array Cleaned data for create request.
     */
    public function prepare_for_create( $data ) {
        // Remove read-only fields.
        $readonly_fields = array( 'id', 'url', 'created', 'updated' );

        foreach ( $readonly_fields as $field ) {
            unset( $data[ $field ] );
        }

        return $data;
    }

    /**
     * Prepare product data for Ecwid API update request.
     *
     * @param array $data       Full product data.
     * @param array $changed_fields Only update these fields (optional).
     * @return array Cleaned data for update request.
     */
    public function prepare_for_update( $data, $changed_fields = array() ) {
        // Remove read-only fields.
        $data = $this->prepare_for_create( $data );

        // If specific fields requested, filter to only those.
        if ( ! empty( $changed_fields ) ) {
            $filtered = array();
            foreach ( $changed_fields as $field ) {
                if ( isset( $data[ $field ] ) ) {
                    $filtered[ $field ] = $data[ $field ];
                }
            }
            return $filtered;
        }

        return $data;
    }
}
