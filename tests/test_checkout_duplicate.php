<?php
// Test the checkout endpoint with the same visitor ID again (should fail)
$visitor_id = '9001015009088'; // Already checked out visitor ID

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost/sophen-residence-system/visitor/process/process_checkout.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['visitor_id' => $visitor_id]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

echo "HTTP Code: $http_code\n";
echo "Response:\n";
echo $response . "\n";

curl_close($ch);
?>
