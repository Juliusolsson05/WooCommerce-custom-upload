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
function nordwebb_log($message) {
    $logs = get_option('nordwebb_csv_logs', []);
    $logs[] = $message;
    update_option('nordwebb_csv_logs', $logs);
}

function nordwebb_csv_importer_menu() {
    add_submenu_page('woocommerce', 'Nordwebb CSV Importer', 'CSV Importer', 'manage_options', 'nordwebb-csv-importer', 'nordwebb_csv_importer_page');
}

function nordwebb_process_csv_chunk() {
    // Include necessary WooCommerce classes and functions
    if (!class_exists('WC_Product')) {
        include_once(WP_PLUGIN_DIR . '/woocommerce/includes/abstracts/abstract-wc-product.php');
        include_once(WP_PLUGIN_DIR . '/woocommerce/includes/class-wc-product-factory.php');
    }

    // Placeholder for mapping original variable product IDs from CSV to actual product IDs in WordPress
    $id_mapping = [];

    // Get the current chunk and total chunks from the AJAX request
    $current_chunk = isset($_POST['current_chunk']) ? intval($_POST['current_chunk']) : 0;
    $csv_data = get_option('nordwebb_csv_data', []);
    $chunk_size = 10;
    $current_data_chunk = array_slice($csv_data, ($current_chunk - 1) * $chunk_size, $chunk_size);
    $header = array_shift($current_data_chunk);

    nordwebb_log("Processing chunk {$current_chunk}");

    // Process the current chunk
    foreach ($current_data_chunk as $row) {
        $product_data = array_combine($header, $row);

        // If this is a variable product
        if ($product_data['Typ'] == 'variable') {
            $product = new WC_Product_Variable();
            $product->set_name($product_data['Namn']);
            $product->set_regular_price($product_data['Ordinarie pris']);
            
            $product_id = $product->save();
            // Store the mapping for this product
            $id_mapping[$product_data['ID']] = $product_id;
            nordwebb_log("Created variable product with ID {$product_id} using CSV ID {$product_data['ID']}");
        }

        // If this is a variation
        if ($product_data['Typ'] == 'variation') {
            // Creating the product variation
            $variation_post = array(
                'post_title' => 'Variation of ' . $product_data['Namn'],
                'post_name' => 'product-' . $id_mapping[$product_data['Överordnad']] . '-variation',
                'post_status' => 'publish',
                'post_parent' => $id_mapping[$product_data['Överordnad']],
                'post_type' => 'product_variation',
                'guid' => home_url('/product-variation/')
            );
            $variation_id = wp_insert_post($variation_post);
            $variation = new WC_Product_Variation($variation_id);

            // Iterating through the variations attributes
            foreach (['size', 'color'] as $attribute) {
                $taxonomy = 'pa_' . $attribute; // The attribute taxonomy

                // If taxonomy doesn't exist, create it
                if (!taxonomy_exists($taxonomy)) {
                    register_taxonomy(
                        $taxonomy,
                        'product_variation',
                        array(
                            'hierarchical' => false,
                            'label' => ucfirst($attribute),
                            'query_var' => true,
                            'rewrite' => array('slug' => sanitize_title($attribute))
                        )
                    );
                    nordwebb_log("Registered taxonomy {$taxonomy}");
                }

                // Check if the Term name exists and if not, create it
                $term_name = $product_data['Attribut ' . ($attribute == 'size' ? '1' : '2') . ' värde(n)'];
                if (!term_exists($term_name, $taxonomy)) {
                    wp_insert_term($term_name, $taxonomy);
                    nordwebb_log("Inserted term {$term_name} into taxonomy {$taxonomy}");
                }

                $term_slug = get_term_by('name', $term_name, $taxonomy)->slug;

                // Set/save the attribute data in the product variation
                update_post_meta($variation_id, 'attribute_' . $taxonomy, $term_slug);
                nordwebb_log("Updated variation {$variation_id} with attribute {$taxonomy} and value {$term_slug}");
            }

            $variation->set_regular_price($product_data['Ordinarie pris']);
            $variation->save();
            nordwebb_log("Saved variation with ID {$variation_id}");
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

function nordwebb_csv_importer_page() {
    echo '<h2>Nordwebb WooCommerce CSV Importer</h2>';

    // Check if form is submitted and save the uploaded CSV data in an option
    if(isset($_POST['submit']) && isset($_FILES['csv'])) {
        $uploaded_file_path = $_FILES['csv']['tmp_name'];
        $csv_data = array_map('str_getcsv', file($uploaded_file_path));
        update_option('nordwebb_csv_data', $csv_data);
        nordwebb_log("Uploaded CSV with " . count($csv_data) . " rows");
    }

    // Display the form
    echo '<form method="post" enctype="multipart/form-data">';
    echo '<input type="file" name="csv">';
    echo '<input type="submit" name="submit" value="Upload">';
    echo '</form>';

    // Display the progress bar and button to start processing
    echo '<div id="progress-bar" style="width: 100%; background-color: #ddd; margin-top: 20px;">';
    echo '<div id="progress" style="width: 0; height: 30px; background-color: #4CAF50;"></div>';
    echo '</div>';
    echo '<button onclick="startProcessing()">Start Processing</button>';
    
    // Include the JavaScript for AJAX processing
    echo '<script>
        function startProcessing() {
            var totalChunks = Math.ceil(' . count(get_option('nordwebb_csv_data', [])) . ' / 10);
            processChunk(1, totalChunks);
        }
        
        function processChunk(currentChunk, totalChunks) {
            jQuery.post(ajaxurl, {
                action: "nordwebb_process_csv_chunk",
                current_chunk: currentChunk,
                total_chunks: totalChunks
            }, function(response) {
                var data = JSON.parse(response);  // Parse the JSON response
                var progress = data.progress;
                var progressBar = document.getElementById("progress");
                progressBar.style.width = progress + "%";
                if (currentChunk < totalChunks) {
                    processChunk(currentChunk + 1, totalChunks);
                } else {
                    alert("Upload completed!");
                }
            });
        }
    </script>';

    // Display logs
    $logs = get_option('nordwebb_csv_logs', []);
    if (!empty($logs)) {
        echo '<h3>Logs:</h3>';
        echo '<ul>';
        foreach ($logs as $log) {
            echo "<li>{$log}</li>";
        }
        echo '</ul>';
    }
}

?>
