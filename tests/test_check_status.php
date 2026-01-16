<?php
// Test check_status.php
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost/sophen-residence-system/visitor/process/check_status.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['visit_id' => 9]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $http_code\n";
echo "Response: $response\n";
?>
