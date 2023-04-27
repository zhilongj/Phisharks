<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

$api_key = '968a6dd4df7e3e6d75b236093de2908c9faf2f8cd39c0a18e8399533708e26f0';
$base_url = 'https://www.virustotal.com/api/v3/';

$url = isset($_GET['url']) ? $_GET['url'] : '';

$headers = array(
    'x-apikey: ' . $api_key
);

// Scan the URL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $base_url . 'urls');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, 'url=' . urlencode($url));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
$scan_response = curl_exec($ch);
curl_close($ch);

$scan_response_data = json_decode($scan_response, true);
$scan_id = $scan_response_data['data']['id'];

file_put_contents('error_log.txt', "Scan Response: \n" . print_r($scan_response_data, true) . "\n\n", FILE_APPEND);

// Wait for 5 seconds before getting the URL analysis report
sleep(5);

// Get the URL analysis report
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $base_url . 'analyses/' . $scan_id);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
$report_response = curl_exec($ch);
curl_close($ch);

file_put_contents('error_log.txt', "Report Response: \n" . print_r(json_decode($report_response, true), true) . "\n\n", FILE_APPEND);

echo json_encode(json_decode($report_response, true));
