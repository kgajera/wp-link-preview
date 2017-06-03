<?php
/**
 * Plugin Name: WP Link Preview
 * Plugin URI: http://kgajera.com
 * Version: 1.0
 * Author: Kishan Gajera
 * Author URI: http://www.kgajera.com
 * Description: Turn a URL into a Facebook like link preview
 * License: GPL2
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class WPLinkPreview {

    function __construct() {
        if ( is_admin() ) {
            add_action( 'init', array( $this, 'init' ) );
        }

        // Enqueue default styles for the link preview HTML
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
    }

    /**
    * Initialization to add the TinyMCE plugin
    */
    function init() {
        // Check if the logged in user can edit posts before registering the TinyMCE plugin
        if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'edit_pages' ) ) {
            return;
        }

        // Check if the logged in user has the Visual Editor enabled before registering the TinyMCE plugin
        if ( get_user_option( 'rich_editing' ) !== 'true' ) {
            return;
        }

        // Register the TinyMCE plugin
        add_filter( 'mce_external_plugins', array( &$this, 'mce_external_plugins' ) );
        add_filter( 'mce_buttons', array( &$this, 'mce_buttons' ) );

        // Add action for the AJAX call that is made from the TinyMCE plugin to fetch link preview
        add_action( 'wp_ajax_fetch_wplinkpreview', array( &$this, 'fetch_wplinkpreview' ) );
    }

    /**
    * Register plugin front-end stylesheet
    */
    function enqueue_scripts() {
        wp_register_style( 'wplinkpreview-style', plugins_url( '/wplinkpreview.css', __FILE__ ), array(), '20120208', 'all' );
        
        wp_enqueue_style( 'wplinkpreview-style' );
    }

    /**
    * AJAX action to fetch and output the link preview content
    */
    function fetch_wplinkpreview() {
        $url = $_GET['url'];

        // Remove all illegal characters from the URL
        $url = filter_var( $url, FILTER_SANITIZE_URL );

        // Validate to ensure we have a URL
        if ( filter_var( $url, FILTER_VALIDATE_URL ) === false ) {
            wp_die();
        }

        // Ensure we have a scheme
        $url = preg_replace( '/^(?!https?:\/\/)/', 'http://', $url );
        $parsed_url = parse_url( $url );

        // Make HTTP request and parse the response body
        $document = $this->fetch_document( $url );
        $meta = $this->get_document_meta( $document );
        $title = $this->get_document_title( $meta, $document );
        $description = $this->get_document_description( $meta, $document );
        $image_url = $this->get_document_image( $meta, $document );

        ?>
        <div class="wplinkpreview">
            <?php if ( ! empty( $image_url ) ) { ?>
                <div class="wplinkpreview-image">
                    <a href="<?php echo esc_url ( $url ); ?>" target="_blank">
                        <img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $title ); ?>" />
                    </a>
                </div>
            <?php } ?>
            <div class="wplinkpreview-title">
                <a href="<?php echo esc_url( $url ); ?>" target="_blank">
                    <?php echo esc_html( $title ); ?>
                </a>
            </div>
            <div class="wplinkpreview-description">
                <?php echo esc_html( $description ); ?>
            </div>
            <div class="wplinkpreview-source">
                <a href="<?php echo esc_url( $url ); ?>" target="_blank">
                    <?php echo esc_html( $parsed_url["host"] ); ?>
                </a>
            </div>
        </div>
        <br />
        <?php

        wp_die();
    }

    /**
    * Make HTTP GET request for the inputted URL
    *
    * @param string $url
    * @return a DOMDocument containing the body of the response returned for the given url 
    */
    function fetch_document( $url ) {
        $document = new DOMDocument();

        // Use WordPress HTTP API to make GET request for the given URL
        $response = wp_remote_get( $url, array( 'timeout' => 120 ) );

        // Parse document
        if( is_array( $response ) ) {
            @$document->loadHTML( wp_remote_retrieve_body( $response ) );
        }

        return $document;
    }

    /**
    * Return the description of the document. The following tags will be
    * used to find a description until one is found:
    *  1) open graph description meta tag
    *  2) description meta tag
    *
    * @param array $meta Parsed meta tags stored as key values
    * @param DOMDocument $document
    * @return string A description for the document
    */
    function get_document_description( $meta, $document ) {
        $description = $meta['og:description'];
    
        if ( empty( $description ) ) {
            $description = $meta['description'];
        }

        return $description;
    }

    /**
    * Return an image for the document. The following tags will be
    * used to find an image until one is found:
    *  1) open graph image meta tag
    *
    * @param array $meta Parsed meta tags stored as key values
    * @param DOMDocument $document
    * @return string A image URL
    */
    function get_document_image( $meta, $document ) {
        $image = $meta['og:image'];
    
        return $image;
    }

    /**
    * Parse all meta tags into a key value array
    *
    * @param DOMDocument $document
    * @return array Array of all meta tags in document stored as a key value pair
    */
    function get_document_meta( $document ) {
        $meta_tags = array();

        $metas = $document->getElementsByTagName('meta');
        for ($i = 0; $i < $metas->length; $i++) {
            $meta = $metas->item( $i );
            $name = $meta->getAttribute( 'name' );

            if ( empty( $name ) ) {
                $name = $meta->getAttribute( 'property' );
            }

            if ( ! empty( $name ) ) {
                $meta_tags[$name] = $meta->getAttribute( 'content' );
            }
        }

        return $meta_tags;
    }
    
    /**
    * Return a title for the document. The following tags will be
    * used to find a title until one is found:
    *  1) open graph title meta tag
    *  2) the title tag
    *  3) the first h1 tag
    *
    * @param array $meta Parsed meta tags stored as key values
    * @param DOMDocument $document
    * @return string A title
    */
    function get_document_title( $meta, $document ) {
        // 1) Use the open graph title
        $title = $meta['og:title'];

        // 2) Use the the document title tag
        if ( empty( $title ) ) {
            $nodes = $document->getElementsByTagName( 'title' );
            $title = $nodes->item(0)->nodeValue;
        }

        // 3) Use first h1 tag
        if ( empty( $title ) ) {
            $nodes = $document->getElementsByTagName( 'h1' );
            $title = $nodes->item(0)->nodeValue;
        }

        return $title;
    }

    /**
    * Adds a TinyMCE plugin compatible JS file to the TinyMCE / Visual Editor instance
    *
    * @param array $plugin_array Array of registered TinyMCE Plugins
    * @return array Modified array of registered TinyMCE Plugins
    */
    function mce_external_plugins( $plugin_array ) {
        $plugin_array['wplinkpreview_plugin'] = plugin_dir_url( __FILE__ ) . 'wplinkpreview.js';
        return $plugin_array;
    }

    /**
    * Adds a button to TinyMCE / Visual Editor which the user can click
    * to input a URL
    *
    * @param array $buttons Array of registered TinyMCE Buttons
    * @return array Modified array of registered TinyMCE Buttons
    */
    function mce_buttons( $buttons ) {
        array_push( $buttons, '|', 'wplinkpreview_plugin' );
        return $buttons;
    }

}

$wp_link_preview_class = new WPLinkPreview;
