<?php
		// companion file to sig2svg.php ; from : https://www.tutorialrepublic.com/php-tutorial/php-file-download.php
        $filepath = "/var/www/website/storage/".$_GET["filename"].".pdf";
        // Process download
        if(file_exists($filepath)) {
                header('Content-Description: File Transfer');
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="'.basename($filepath).'"');
                header('Expires: 0');
                header('Cache-Control: must-revalidate');
                header('Pragma: public');
                header('Content-Length: ' . filesize($filepath));
                flush(); // Flush system output buffer
                readfile($filepath);
                die();
        }
        else {echo "Failed to find file";}
?>
