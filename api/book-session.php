<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$configPath = dirname(__DIR__) . '/config/config.php';
if (!is_readable($configPath)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Server configuration missing.']);
    exit;
}

$config = require $configPath;

$vendorAutoload = dirname(__DIR__) . '/vendor/autoload.php';
if (!is_readable($vendorAutoload)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Server dependencies missing. Run composer install on the host.']);
    exit;
}

require $vendorAutoload;

if (!empty($_POST['website'])) {
    echo json_encode(['ok' => true]);
    exit;
}

$name = trim((string) ($_POST['name'] ?? ''));
$mobile = trim((string) ($_POST['mobile'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$description = trim((string) ($_POST['description'] ?? ''));

if ($name === '' || $mobile === '' || $email === '' || $description === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Please fill in all fields.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Please enter a valid email address.']);
    exit;
}

if (strlen($name) > 120 || strlen($mobile) > 30 || strlen($email) > 120 || strlen($description) > 5000) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'One or more fields are too long.']);
    exit;
}

$to = (string) ($config['booking_to_email'] ?? 'rawatmanali226@gmail.com');
$subject = 'New session booking — The Heal Nest';

$body = implode("\n", [
    'A new session request was submitted from the website.',
    '',
    'Name: ' . $name,
    'Mobile: ' . $mobile,
    'Email: ' . $email,
    '',
    'Message:',
    $description,
    '',
    '— Sent from The Heal Nest booking form',
]);

$result = HealNest\BookingMail::send($config, $to, $subject, $body, $name, $email);

if (!$result['ok']) {
    http_response_code(500);
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $isLocal = $host === 'localhost'
        || str_starts_with($host, 'localhost:')
        || $host === '127.0.0.1'
        || str_starts_with($host, '127.0.0.1:');
    $publicError = 'Could not send email from the server. Please email us directly at ' . $to;
    $detail = trim((string) ($result['error'] ?? ''));
    $payload = ['ok' => false, 'error' => $publicError];
    if ($isLocal && $detail !== '') {
        $payload['detail'] = $detail;
    }
    echo json_encode($payload);
    exit;
}

echo json_encode(['ok' => true, 'message' => 'Thank you. We will get back to you soon.']);
