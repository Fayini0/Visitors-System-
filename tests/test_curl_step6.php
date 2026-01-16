<?php
// Test the curl call from step6.php to send_verification.php
$visit_id = 14; // Use the latest visit ID

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost/sophen-residence-system/visitor/process/send_verification.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['visit_id' => $visit_id]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $http_code\n";
echo "Response: $response\n";
?>
