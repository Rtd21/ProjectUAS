<?php
require 'vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

function supabase_request($method, $endpoint, $data = null) {
    return supabase_request_with_headers($method, $endpoint, $data, []);
}

function supabase_request_with_headers($method, $endpoint, $data = null, $extra_headers = []) {
    $url = $_ENV['SUPABASE_URL'] . $endpoint;
    
    $default_headers = [
        "apikey: " . $_ENV['SUPABASE_KEY'],
        "Authorization: Bearer " . $_ENV['SUPABASE_KEY'],
        "Content-Type: application/json",
        "Prefer: return=representation"
    ];

    $headers = array_merge($default_headers, $extra_headers);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true); // Mendapatkan header dari response
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

    if ($data !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $header_string = substr($response, 0, $header_size);
    $response_body = substr($response, $header_size);
    
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        error_log("Supabase cURL Error: " . $curl_error);
        return ['data' => null, 'error' => 'cURL Error', 'message' => $curl_error, 'count' => null];
    }

    $decoded_body = json_decode($response_body, true);
    
    $count = null;
    $headers_arr = explode("\r\n", $header_string);
    foreach ($headers_arr as $header) {
        if (stripos($header, 'Content-Range:') !== false) {
            $parts = explode('/', $header);
            $count = $parts[1] ?? null;
            if (is_numeric($count)) {
                $count = (int)$count;
            }
            break;
        }
    }
    
    if ($http_code >= 400) {
        error_log("Supabase API Error. Status: " . $http_code . ". Response: " . $response_body);
        return ['data' => null, 'error' => $decoded_body, 'count' => null];
    }

    return ['data' => $decoded_body, 'count' => $count, 'error' => null];
}