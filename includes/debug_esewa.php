<?php
// debug_esewa.php - Temporary file to see what eSewa is sending
echo "<h1>eSewa Debug Information</h1>";
echo "<h2>GET Parameters:</h2>";
echo "<pre>";
print_r($_GET);
echo "</pre>";

echo "<h2>POST Parameters:</h2>";
echo "<pre>";
print_r($_POST);
echo "</pre>";

if (isset($_GET['data'])) {
    echo "<h2>Decoded Data:</h2>";
    $decodedData = base64_decode($_GET['data']);
    echo "<p><strong>Raw decoded:</strong> " . htmlspecialchars($decodedData) . "</p>";
    
    $jsonData = json_decode($decodedData, true);
    if ($jsonData) {
        echo "<p><strong>JSON decoded:</strong></p>";
        echo "<pre>";
        print_r($jsonData);
        echo "</pre>";
    } else {
        echo "<p><strong>JSON decode failed:</strong> " . json_last_error_msg() . "</p>";
    }
}
?>