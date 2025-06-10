<?php
header('Content-Type: application/json');

$mcxUrl = "https://www.mcxindia.com/en/market-data/get-quote/FUTCOM/GOLD/";
$mcxRate = 0; // Default to 0 if fetching fails

// Attempt to fetch content from MCX
$contextOptions = [
    "ssl" => [
        "verify_peer" => false,
        "verify_peer_name" => false,
    ],
    "http" => [
        "header" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36\r\n"
    ]
];
$context = stream_context_create($contextOptions);

$html = @file_get_contents($mcxUrl, false, $context);

if ($html === FALSE) {
    echo json_encode(['success' => false, 'message' => 'Could not fetch data from MCX.']);
    exit;
}

// Use regex to find the price. This is fragile and depends on MCX website structure.
// Looking for a large number that looks like a price.
// A more robust solution would involve a DOM parser (e.g., Simple HTML DOM Parser library).
if (preg_match('/<span class="value"\s*data-value="([0-9]+\.[0-9]{2})">/', $html, $matches)) {
    // This regex looks for a span with class "value" and a data-value attribute, common in dynamic content.
    // If this fails, a more generic number extraction might be needed.
    $mcxRate = (float)$matches[1];
} elseif (preg_match('/>([0-9]{5,}\.[0-9]{2})</', $html, $matches)) {
    // Fallback regex to find a large number with two decimal places
    $mcxRate = (float)$matches[1];
}

if ($mcxRate > 0) {
    echo json_encode(['success' => true, 'rate' => $mcxRate, 'source' => 'MCX Live']);
} else {
    echo json_encode(['success' => false, 'message' => 'MCX rate not found on the page.', 'rate' => 0]);
}
?> 