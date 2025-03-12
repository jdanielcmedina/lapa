<?php

/**
 * Send webhook notification
 * @param string $url Webhook URL
 * @param array $data Data to send
 * @param string $secret Secret key for signature (optional)
 * @return bool Success status
 */
function webhook($url, $data, $secret = null) {
    $payload = json_encode($data);
    
    $headers = [
        'Content-Type: application/json',
        'User-Agent: Lapa-Webhook/1.0'
    ];

    // Add signature if secret is provided
    if ($secret) {
        $signature = hash_hmac('sha256', $payload, $secret);
        $headers[] = 'X-Webhook-Signature: ' . $signature;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $status >= 200 && $status < 300;
}

/**
 * Verify webhook signature
 * @param string $payload Raw request body
 * @param string $signature Received signature
 * @param string $secret Secret key
 * @return bool Valid signature
 */
function verify_webhook($payload, $signature, $secret) {
    $expected = hash_hmac('sha256', $payload, $secret);
    return hash_equals($expected, $signature);
}
