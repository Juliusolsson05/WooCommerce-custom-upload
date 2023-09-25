<?php


include_once 'nordwebb-csv-importer.php';

function nordwebb_csv_importer_page()
{
    echo '<h2>Nordwebb WooCommerce CSV Importer</h2>';
	
	// Check if form is submitted and save the uploaded CSV file path in an option
	if (isset($_POST['submit']) && isset($_FILES['csv'])) {
		// Choose a directory to store the uploaded file
		$upload_dir = wp_upload_dir();
		$uploaded_file_path = $upload_dir['path'] . '/' . basename($_FILES['csv']['name']);

		// Move the uploaded file to the chosen directory
		if (move_uploaded_file($_FILES['csv']['tmp_name'], $uploaded_file_path)) {
			// Save the file path in the option
			update_option('nordwebb_csv_file_path', $uploaded_file_path);
			update_option('nordwebb_csv_logs', []);
			nordwebb_log("Uploaded CSV to " . $uploaded_file_path);
		} else {
			nordwebb_log("Failed to move uploaded file");
		}
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

	echo '<script>
		function startProcessing() {
			// Use AJAX to load the CSV data from the server
			jQuery.post(ajaxurl, {
				action: "nordwebb_get_csv_data"
			}, function(csvData) {
				var totalRows = csvData.split("\n").length;

            // Splits the total rows into chunks, each chunk represents 10 rows.
            // This is to prevent the requests from timing out.
            var totalChunks = Math.ceil(totalRows / 10);
            processChunk(1, totalChunks);
        });
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
                alert("Processing completed!");
            }
        });
    } 
</script>';


}
?>
