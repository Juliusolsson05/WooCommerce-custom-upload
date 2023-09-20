<?php
/**
 * Plugin Name: Nordwebb WooCommerce CSV Importer
 * Description: A plugin to help Nordwebb upload products from a CSV file to WooCommerce.
 * Version: 1.0
 * Author: Nordwebb
 */

// Add a submenu in the WooCommerce section for the importer
add_action('admin_menu', 'nordwebb_csv_importer_menu');

function nordwebb_csv_importer_menu() {
    add_submenu_page('woocommerce', 'Nordwebb CSV Importer', 'CSV Importer', 'manage_options', 'nordwebb-csv-importer', 'nordwebb_csv_importer_page');
}

function nordwebb_process_csv($csv_file_path) {
    // Include necessary WooCommerce classes and functions
    if (!class_exists('WC_Product')) {
        include_once(WP_PLUGIN_DIR . '/woocommerce/includes/abstracts/abstract-wc-product.php');
        include_once(WP_PLUGIN_DIR . '/woocommerce/includes/class-wc-product-factory.php');
    }
    
    // Load CSV
    $csv_rows = array_map('str_getcsv', file($csv_file_path));
    $header = array_shift($csv_rows);
    $messages = [];

    // Process each row
    foreach ($csv_rows as $row) {
        $product_data = array_combine($header, $row);

        try {
            // Handle variable product
            if ($product_data['Typ'] == 'variable') {
                $product = new WC_Product_Variable();
                $product->set_name($product_data['Namn']);
                $product->set_regular_price($product_data['Ordinarie pris']);
                $product_id = $product->save();
            }

            // Handle product variation
            if ($product_data['Typ'] == 'variation') {
                $variation = new WC_Product_Variation();
                $variation->set_name($product_data['Namn']);
                $variation->set_regular_price($product_data['Ordinarie pris']);
                $variation->set_weight($product_data['Vikt (kg)']);
                $variation->set_length($product_data['Längd (cm)']);
                $variation->set_height($product_data['Höjd (cm)']);
                $variation->set_parent_id($product_data['Överordnad']);

                // Add custom meta data
                $variation->add_meta_data('effekt_delta_t_50', $product_data['Meta: effekt_delta_t_50'], true);
                $variation->add_meta_data('effekt_delta_t_30', $product_data['Meta: effekt_delta_t_30'], true);
                $variation->add_meta_data('color_code', $product_data['Meta: color_code'], true);

                $variation_id = $variation->save();
            }
        } catch (Exception $e) {
            $messages[] = "Error processing product '{$product_data['Namn']}': " . $e->getMessage();
        }
    }
    
    if (empty($messages)) {
        return "Products successfully imported/updated.";
    } else {
        return implode("<br>", $messages);
    }
}

function nordwebb_csv_importer_page() {
    echo '<h2>Nordwebb WooCommerce CSV Importer</h2>';
    
    // Check if form is submitted and process the uploaded file
    if(isset($_POST['submit']) && isset($_FILES['csv'])) {
        $uploaded_file_path = $_FILES['csv']['tmp_name'];
        $result = nordwebb_process_csv($uploaded_file_path);
        echo "<p>{$result}</p>";
    }
    
    // Display the form
    echo '<form method="post" enctype="multipart/form-data">';
    echo '<input type="file" name="csv">';
    echo '<input type="submit" name="submit" value="Upload">';
    echo '</form>';
}

?>
