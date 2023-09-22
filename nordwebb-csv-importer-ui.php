<?php


include_once 'nordwebb-csv-importer.php';

function nordwebb_csv_importer_page()
{
    echo '<h2>Nordwebb WooCommerce CSV Importer</h2>';

    // Check if form is submitted and save the uploaded CSV data in an option
    if (isset($_POST['submit']) && isset($_FILES['csv'])) {
        $uploaded_file_path = $_FILES['csv']['tmp_name'];
        $csv_data = array_map('str_getcsv', file($uploaded_file_path));
        update_option('nordwebb_csv_data', $csv_data);
		update_option('nordwebb_csv_logs', []);
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

    echo '<h3>Logs:</h3>';
    echo '<ul id="nordwebb-logs"></ul>';

    // Include the JavaScript for AJAX processing
    echo '<script>
        function startProcessing() {

            //splits the total rows in to chunks, each chunks represents 10 rows.
            // This is to prevent the requests from timing out.

            var totalChunks = Math.ceil(' . count(get_option('nordwebb_csv_data', [])) . ' / 10);
            processChunk(1, totalChunks);
        }
        
        function processChunk(currentChunk, totalChunks) {
            jQuery.post(ajaxurl, {
                action: "nordwebb_process_csv_chunk",
                current_chunk: currentChunk,
                total_chunks: totalChunks
            }, function(response) {
                var data = JSON.parse(response);
                var progress = data.progress;
                var progressBar = document.getElementById("progress");
                progressBar.style.width = progress + "%";
                
                // Display the logs
                var logList = "";
                for (var i = 0; i < data.logs.length; i++) {
                    logList += "<li>" + data.logs[i] + "</li>";
                }
                jQuery("#nordwebb-logs").html(logList);  // Assuming that you have only one <ul> element for logs. If not, provide a specific ID or class.
        
                if (currentChunk < totalChunks) {
                    processChunk(currentChunk + 1, totalChunks);
                } else {
                    alert("Upload completed!");
                }
            });
        } </script>';

}
?>
