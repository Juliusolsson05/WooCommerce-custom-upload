<?php
/**
 * Plugin Name: Nordwebb WooCommerce CSV Importer
 * Description: A plugin to help Nordwebb upload products from a CSV file to WooCommerce with logging capabilities.
 * Version: 1.3
 * Author: Nordwebb
 */

//TODO: Fix error regarding that variants do not get any attributes set correctly.

include_once 'nordwebb-csv-importer-ui.php';

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

add_action('wp_ajax_nordwebb_get_csv_data', 'nordwebb_get_csv_data');

function nordwebb_get_csv_data() {
    // Get the CSV file path from the option
    $csv_file_path = get_option('nordwebb_csv_file_path', '');
    if ($csv_file_path && file_exists($csv_file_path)) {
        // Read the CSV file and return the data
        $csv_data = file_get_contents($csv_file_path);
        echo $csv_data;
    } else {
        echo 'Error: CSV file not found.';
    }
    wp_die();
}


function nordwebb_process_csv_chunk()
{
    // Include necessary WooCommerce classes and functions
    if (!class_exists('WC_Product')) {
        include_once(WP_PLUGIN_DIR . '/woocommerce/includes/abstracts/abstract-wc-product.php');
        include_once(WP_PLUGIN_DIR . '/woocommerce/includes/class-wc-product-factory.php');
    }

    // Check if this is the first chunk, and if so, reset the ID mapping
    if ($current_chunk == 1) {
        update_option('nordwebb_id_mapping', []);
    }

    // Placeholder for mapping original variable product IDs from CSV to actual product IDs in WordPress
    $id_mapping = get_option('nordwebb_id_mapping', []);


    // Get the current chunk and total chunks from the AJAX request
    $current_chunk = isset($_POST['current_chunk']) ? intval($_POST['current_chunk']) : 0;
    // Get the CSV file path from the option
    $csv_file_path = get_option('nordwebb_csv_file_path', '');
    if ($csv_file_path) {
        // Load the CSV data from the file
        $csv_data = array_map('str_getcsv', file($csv_file_path));
    } else {
        $csv_data = [];
    }

    $header = array_shift($csv_data);

    $chunk_size = 10;

    // Convert each row of data to an associative array
    $csv_data_assoc = array_map(function ($row) use ($header) {
        return array_combine($header, $row);
    }, $csv_data);

    // Filter out variable products
    $variable_products = array_filter($csv_data_assoc, function ($row) {
        return $row['Typ'] == 'variable';
    });

    // Filter out variations
    $variations = array_filter($csv_data_assoc, function ($row) {
        return $row['Typ'] == 'variation';
    });

    $total_variable_chunks = ceil(count($variable_products) / $chunk_size);

    if ($current_chunk <= $total_variable_chunks) {
        // We're in the variable products phase
        $current_data_chunk = array_slice($variable_products, ($current_chunk - 1) * $chunk_size, $chunk_size);

        foreach ($current_data_chunk as $row) {

            $product_data = array_combine($header, $row);


            // Creating a variable product
            $product = new WC_Product_Variable();
            $product->set_name($product_data['Namn']);
            $product->set_slug($product_data['Slut']);
            $product->set_regular_price($product_data['Ordinarie pris']);
            $product->set_short_description($product_data['Kort beskrivning']);
            $product->set_description($product_data['Beskrivning']); // Full product description
            $product->set_status('publish'); // Making the product published


            // Setting attributes for the variable product
            $attributes = array();

            // Attribute 1
            if (!empty($product_data['Attribut 1 namn']) && !empty($product_data['Attribut 1 värde(n)'])) {
                $attribute_name = wc_attribute_taxonomy_name($product_data['Attribut 1 namn']);
                $attribute = new WC_Product_Attribute();
                $attribute->set_name($attribute_name);
                $attribute->set_options(explode(',', $product_data['Attribut 1 värde(n)'])); // Assuming ',' as delimiter for multiple values
                $attribute->set_position(0);
                $attribute->set_visible(true);
                $attribute->set_variation(true);
                $attributes[] = $attribute;
            }

            // Attribute 2 (similarly you can add more attributes if present)
            if (!empty($product_data['Attribut 2 namn']) && !empty($product_data['Attribut 2 värde(n)'])) {
                $attribute_name = wc_attribute_taxonomy_name($product_data['Attribut 2 namn']);
                $attribute = new WC_Product_Attribute();
                $attribute->set_name($attribute_name);
                $attribute->set_options(explode(',', $product_data['Attribut 2 värde(n)']));
                $attribute->set_position(1);
                $attribute->set_visible(true);
                $attribute->set_variation(true);
                $attributes[] = $attribute;
            }

            $product->set_attributes($attributes);
            $product_id = $product->save();


            // Store the mapping of original CSV ID to WordPress product ID
            $id_mapping[$product_data['ID']] = $product_id;
            update_option('nordwebb_id_mapping', $id_mapping);
        }
    } else {
        // We're in the variations phase
        $adjusted_chunk = $current_chunk - $total_variable_chunks;
        $current_data_chunk = array_slice($variations, ($adjusted_chunk - 1) * $chunk_size, $chunk_size);
        foreach ($current_data_chunk as $row) {

            $product_data = array_combine($header, $row);
            // Use the ID mapping to get the WooCommerce ID of the parent product
            $parent_id = $id_mapping[strval(intval(floatval($product_data['Överordnad'])))];


            // Create a new variation
            $variation = new WC_Product_Variation();
            $variation->set_parent_id($parent_id);

            // Set attributes for this variation
            $attributes = array();
            if (!empty($product_data['Attribut 1 namn']) && !empty($product_data['Attribut 1 värde(n)'])) {
                $attribute_name = wc_attribute_taxonomy_name($product_data['Attribut 1 namn']);
                $attributes[$attribute_name] = $product_data['Attribut 1 värde(n)'];
            }

            if (!empty($product_data['Attribut 2 namn']) && !empty($product_data['Attribut 2 värde(n)'])) {
                $attribute_name = wc_attribute_taxonomy_name($product_data['Attribut 2 namn']);
                $attributes[$attribute_name] = $product_data['Attribut 2 värde(n)'];
            }

            $variation->set_attributes($attributes);

            // Set other details for this variation
            $variation->set_name($product_data['Namn']);
            $variation->set_description($product_data['Beskrivning']);
            $variation->set_short_description($product_data['Kort beskrivning']);
            $variation->set_sku($product_data['Artikelnummer']);
            $variation->set_regular_price($product_data['Ordinarie pris']);
            $variation->set_sale_price($product_data['Reapris']);

            // Stock settings (assuming unlimited stock for dropshipping)
            $variation->set_manage_stock(false);
            $variation->set_stock_status('instock');

            // Set dimensions & weight
            $variation->set_weight($product_data['Vikt (kg)']);
            $variation->set_length($product_data['Längd (cm)']);
            $variation->set_width($product_data['Bredd (cm)']);
            $variation->set_height($product_data['Höjd (cm)']);

            // Save the variation
            $variation->save();

            $variation_id = $variation->get_id();


            // Assign custom meta data to this variation
            if (!empty($product_data['Meta: color_code'])) {
                update_post_meta($variation_id, 'color_code', $product_data['Meta: color_code']);
            }

            if (!empty($product_data['Meta: effekt_delta_t_50'])) {
                update_post_meta($variation_id, 'effekt_delta_t_50', $product_data['Meta: effekt_delta_t_50']);
            }

            if (!empty($product_data['Meta: effekt_delta_t_30'])) {
                update_post_meta($variation_id, 'effekt_delta_t_30', $product_data['Meta: effekt_delta_t_30']);
            }

        }
    }

    // Return the progress
    $total_rows = count($csv_data);
    $progress = ($current_chunk * $chunk_size / $total_rows) * 100;
    $logs = get_option('nordwebb_csv_logs', []);

    if ($progress > 100) {
        $progress = 100;
    }
    echo json_encode(['progress' => $progress, 'logs' => $logs]);
    wp_die();
}
?>