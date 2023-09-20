<?php
/**
 * Plugin Name: Nordwebb Woocommerce Csv Importer
 * Description: Plugin tailored for Nordwebbs needs to upload products from a specific CSV format.
 * Version: 1.0
 * Author: Your Name
 */

// Add a submenu in the WooCommerce section
add_action('admin_menu', 'my_csv_importer_menu');
function my_csv_importer_menu() {
    add_submenu_page('woocommerce', 'CSV Importer', 'CSV Importer', 'manage_options', 'my-csv-importer', 'my_csv_importer_page');
}

// Display the upload form
function my_csv_importer_page() {
    echo '<h2>WooCommerce CSV Importer</h2>';
    
    // Check if form is submitted and process the uploaded file
    if(isset($_POST['submit']) && isset($_FILES['csv'])) {
        // TODO: Handle the CSV processing here
        // Use functions like wc_get_product() and wp_insert_post() to add products
    }
    
    // Display the form
    echo '<form method="post" enctype="multipart/form-data">';
    echo '<input type="file" name="csv">';
    echo '<input type="submit" name="submit" value="Upload">';
    echo '</form>';
}

?>
