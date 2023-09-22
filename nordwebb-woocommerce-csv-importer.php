<?php
/**
 * Plugin Name: Nordwebb WooCommerce CSV Importer
 * Description: A plugin to help Nordwebb upload products from a CSV file to WooCommerce.
 * Version: 1.0
 * Author: Nordwebb
 */

// Add a submenu in the WooCommerce section for the importer
add_action('admin_menu', 'nordwebb_csv_importer_menu');
add_action('wp_ajax_nordwebb_process_csv_chunk', 'nordwebb_process_csv_chunk');

function nordwebb_csv_importer_menu() {
    add_submenu_page('woocommerce', 'Nordwebb CSV Importer', 'CSV Importer', 'manage_options', 'nordwebb-csv-importer', 'nordwebb_csv_importer_page');
}

function nordwebb_process_csv_chunk() {
    // Include necessary WooCommerce classes and functions
    if (!class_exists('WC_Product')) {
        include_once(WP_PLUGIN_DIR . '/woocommerce/includes/abstracts/abstract-wc-product.php');
        include_once(WP_PLUGIN_DIR . '/woocommerce/includes/class-wc-product-factory.php');
    }

    // Get the current chunk and total chunks from the AJAX request
    $current_chunk = isset($_POST['current_chunk']) ? intval($_POST['current_chunk']) : 0;
    $csv_data = get_option('nordwebb_csv_data', []);
    $chunk_size = 10;
    $current_data_chunk = array_slice($csv_data, ($current_chunk - 1) * $chunk_size, $chunk_size);
    $header = array_shift($current_data_chunk);
    
    // Process the current chunk
    foreach ($current_data_chunk as $row) {
        $product_data = array_combine($header, $row);
		// Construct attribute names based on product name
		$product_name_slug = sanitize_title($product_data['Namn']); // Convert product name to a safe slug (e.g., 'TESI 2:1' -> 'tesi-2-1')
		$size_attribute_name = $product_name_slug . '-size';
		$color_attribute_name = $product_name_slug . '-color';

        // Handle variable product
        if ($product_data['Typ'] == 'variable') {
            $product = new WC_Product_Variable();
            $product->set_name($product_data['Namn']);
            $product->set_regular_price($product_data['Ordinarie pris']);
			
			$attributes = array(
			$size_attribute_name => array(
				'name' => $product_data['Namn'] . ' Size',
				'value' => '',  // Will be set for each variation
				'position' => 1,
				'is_visible' => 1,
				'is_variation' => 1,
				'is_taxonomy' => 0  // This makes it a custom product attribute
			),
			$color_attribute_name => array(
				'name' => $product_data['Namn'] . ' Color',
				'value' => '',  // Will be set for each variation
				'position' => 2,
				'is_visible' => 1,
				'is_variation' => 1,
				'is_taxonomy' => 0  // This makes it a custom product attribute
			)
		);
		$product->set_attributes($attributes);

			
            $product_id = $product->save();
        }

        // Handle product variation
        if ($product_data['Typ'] == 'variation') {
            $variation = new WC_Product_Variation();
            $variation->set_name($product_data['Namn']);
            $variation->set_regular_price($product_data['Ordinarie pris']);
			
			$variation_attributes = array(
				$product_data['Namn'] . ' Size' => $product_data['Attribut 1 värde(n)'],
				$product_data['Namn'] . ' Color' => $product_data['Attribut 2 värde(n)']
			);
			$variation->set_attributes($variation_attributes);
			
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
    }
    
    // Return the progress
    $total_rows = count($csv_data);
    $progress = ($current_chunk * $chunk_size / $total_rows) * 100;
    if ($progress > 100) {
        $progress = 100;  // Ensure it doesn't exceed 100%
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
}

?>
