<?php
/**
 * Plugin Name: Nordwebb WooCommerce CSV Importer
 * Description: A plugin to help Nordwebb upload products from a CSV file to WooCommerce with logging capabilities.
 * Version: 1.3
 * Author: Nordwebb
 */

// Hooks
add_action('admin_menu', 'nordwebb_csv_importer_menu');
add_action('wp_ajax_nordwebb_process_csv_chunk', 'nordwebb_process_csv_chunk');

// Logging utility
function nordwebb_log($message)
{
    $logs = get_option('nordwebb_csv_logs', []);
    $logs[] = $message;
    update_option('nordwebb_csv_logs', $logs);
}

function nordwebb_csv_importer_menu()
{
    add_submenu_page('woocommerce', 'Nordwebb CSV Importer', 'CSV Importer', 'manage_options', 'nordwebb-csv-importer', 'nordwebb_csv_importer_page');
}

function nordwebb_process_csv_chunk()
{
    // Include necessary WooCommerce classes and functions
    if (!class_exists('WC_Product')) {
        include_once(WP_PLUGIN_DIR . '/woocommerce/includes/abstracts/abstract-wc-product.php');
        include_once(WP_PLUGIN_DIR . '/woocommerce/includes/class-wc-product-factory.php');
    }

    // Placeholder for mapping original variable product IDs from CSV to actual product IDs in WordPress
    $id_mapping = get_option('nordwebb_id_mapping', []);

    // Get the current chunk and total chunks from the AJAX request
    $current_chunk = isset($_POST['current_chunk']) ? intval($_POST['current_chunk']) : 0;
    $csv_data = get_option('nordwebb_csv_data', []);
    $chunk_size = 10;

    // Determine which phase we're in: processing variable products or variations
    $variable_products = array_filter($csv_data, function ($row) {
        return $row['Typ'] == 'variable';
    });

    $variations = array_filter($csv_data, function ($row) {
        return $row['Typ'] == 'variation';
    });

    $total_variable_chunks = ceil(count($variable_products) / $chunk_size);

    if ($current_chunk <= $total_variable_chunks) {
        // We're in the variable products phase
        $current_data_chunk = array_slice($variable_products, ($current_chunk - 1) * $chunk_size, $chunk_size);
        foreach ($current_data_chunk as $row) {
            $product_data = array_combine($header, $row);
            $product = new WC_Product_Variable();
            // ... set other product attributes ...

            $product_id = $product->save();
            $id_mapping[$product_data['ID']] = $product_id;
        }

    } else {
        // We're in the variations phase
        $adjusted_chunk = $current_chunk - $total_variable_chunks;
        $current_data_chunk = array_slice($variations, ($adjusted_chunk - 1) * $chunk_size, $chunk_size);
        foreach ($current_data_chunk as $row) {
            $product_data = array_combine($header, $row);
            // Use the ID mapping to get the WooCommerce ID of the parent product
            $parent_id = $id_mapping[$product_data['Överordnad']];
            $variation = new WC_Product_Variation();
            $variation->set_parent_id($parent_id);
            // ... set other variation attributes ...
            $variation->save();
        }
    }

    // Return the progress
    $total_rows = count($csv_data);
    $progress = ($current_chunk * $chunk_size / $total_rows) * 100;
    if ($progress > 100) {
        $progress = 100;
    }
    echo json_encode(['progress' => $progress]);
    wp_die();
}
?>
