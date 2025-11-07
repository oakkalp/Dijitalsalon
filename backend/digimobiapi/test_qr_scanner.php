<?php
// Test QR scanner API
$url = 'https://dijitalsalon.cagapps.app/digimobiapi/qr_scanner.php';
$qr_code = 'QR_wt6d6r9le_mgxq1uo6';

$data = [
    'qr_code' => $qr_code
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIE, 'PHPSESSID=hii4hbgb86n1gk7colbefukilq'); // Session cookie

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $http_code\n";
echo "Response: $response\n";
?>
