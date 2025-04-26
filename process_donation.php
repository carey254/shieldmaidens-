<?php
require 'db.php'; // Make sure db.php securely connects using PDO

// Safaricom credentials (store securely in environment variables in production)
$consumerKey = 'YOUR_CONSUMER_KEY';
$consumerSecret = 'YOUR_CONSUMER_SECRET';
$BusinessShortCode = 'YOUR_SHORTCODE';
$Passkey = 'YOUR_PASSKEY';

// 1. Get user input
$phone = $_POST['phone'] ?? '';
$amount = $_POST['amount'] ?? '';

// 2. Validate input
if (empty($phone) || empty($amount)) {
    echo json_encode(['success' => false, 'message' => 'Phone and amount are required']);
    exit;
}

// Validate phone format (expects 2547XXXXXXXX)
if (!preg_match('/^2547\d{8}$/', $phone)) {
    echo json_encode(['success' => false, 'message' => 'Invalid phone number format']);
    exit;
}

// Validate amount
if (!is_numeric($amount) || $amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid donation amount']);
    exit;
}

// 3. Generate Access Token
$credentials = base64_encode($consumerKey . ':' . $consumerSecret);
$token_url = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';

$curl = curl_init();
curl_setopt($curl, CURLOPT_URL, $token_url);
curl_setopt($curl, CURLOPT_HTTPHEADER, [
    'Authorization: Basic ' . $credentials
]);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
$token_response = curl_exec($curl);

if ($token_response === false) {
    echo json_encode(['success' => false, 'message' => 'Network error during token generation']);
    exit;
}

curl_close($curl);

$token_result = json_decode($token_response);
if (!isset($token_result->access_token)) {
    echo json_encode(['success' => false, 'message' => 'Failed to generate access token']);
    exit;
}

$access_token = $token_result->access_token;

// 4. Initiate STK Push
$timestamp = date('YmdHis');
$password = base64_encode($BusinessShortCode . $Passkey . $timestamp);

$stkPushUrl = 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';
$callbackURL = 'https://yourdomain.com/callback.php'; // Update this to your real callback URL

$stkPushData = [
    'BusinessShortCode' => $BusinessShortCode,
    'Password' => $password,
    'Timestamp' => $timestamp,
    'TransactionType' => 'CustomerPayBillOnline',
    'Amount' => $amount,
    'PartyA' => $phone,
    'PartyB' => $BusinessShortCode,
    'PhoneNumber' => $phone,
    'CallBackURL' => $callbackURL,
    'AccountReference' => 'Donation',
    'TransactionDesc' => 'Donation Payment'
];

$curl = curl_init();
curl_setopt($curl, CURLOPT_URL, $stkPushUrl);
curl_setopt($curl, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $access_token
]);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_POST, true);
curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($stkPushData));

$stk_response = curl_exec($curl);

if ($stk_response === false) {
    echo json_encode(['success' => false, 'message' => 'Network error during STK Push']);
    exit;
}

curl_close($curl);

$stk_result = json_decode($stk_response, true);

// 5. Save transaction attempt
if (isset($stk_result['CheckoutRequestID'])) {
    $checkoutRequestID = $stk_result['CheckoutRequestID'];

    $stmt = $pdo->prepare("INSERT INTO donations (phone, amount, checkout_request_id, status) VALUES (?, ?, ?, ?)");
    $stmt->execute([$phone, $amount, $checkoutRequestID, 'PENDING']);

    echo json_encode([
        'success' => true,
        'message' => 'STK Push initiated. Awaiting customer confirmation.',
        'CheckoutRequestID' => $checkoutRequestID
    ]);
} else {
    $errorMessage = $stk_result['errorMessage'] ?? 'Failed to initiate STK Push';
    echo json_encode([
        'success' => false,
        'message' => $errorMessage
    ]);
}
?>
