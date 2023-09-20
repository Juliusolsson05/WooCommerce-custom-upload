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

function nordwebb_csv_importer_page() {
    echo '<h2>Nordwebb WooCommerce CSV Importer</h2>';
    
    // Check if form is submitted and process the uploaded file
    if(isset($_POST['submit']) && isset($_FILES['csv'])) {
        // TODO: Handle the CSV processing here
        
        // For now, just display a message indicating the file was received
        echo '<p>CSV file received. Processing will be implemented here.</p>';
    }
    
    // Display the form
    echo '<form method="post" enctype="multipart/form-data">';
    echo '<input type="file" name="csv">';
    echo '<input type="submit" name="submit" value="Upload">';
    echo '</form>';
}

?>
