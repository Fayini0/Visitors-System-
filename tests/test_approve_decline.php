<?php
// Test approve_decline.php with a token
$visit_id = 9;
$token = hash('sha256', $visit_id . 'sophen_secret_key');
$approve_url = "http://localhost/sophen-residence-system/visitor/process/approve_decline.php?action=approve&visit_id={$visit_id}&token={$token}";

echo "Approve URL: $approve_url\n";

// Simulate a GET request to the approve URL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $approve_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $http_code\n";
echo "Response: $response\n";
?>
