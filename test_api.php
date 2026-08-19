<?php
/**
 * Test script for Client Monitor API
 * Run from command line: php test_api.php
 */

require_once __DIR__ . '/src/config.php';
require_once __DIR__ . '/src/ClientMonitorService.php';

try {
    echo "=== Client Monitor API Test ===\n\n";

    // Get database connection
    $conn = getDbConnection();
    echo "[OK] Database connection successful\n";

    // Create service
    $service = new ClientMonitorService($conn);
    echo "[OK] Service created\n\n";

    // Test 1: Register a client
    echo "Test 1: Register Client\n";
    $clientData = [
        'client_id' => 'test-client-' . time(),
        'client_name' => 'Test Client',
        'description' => 'Test client for verification',
        'heartbeat_interval' => 3600,
        'metadata' => [
            'test' => true,
            'version' => '1.0.0'
        ]
    ];

    $result = $service->registerClient($clientData);
    echo "[OK] Client registered: " . json_encode($result, JSON_PRETTY_PRINT) . "\n\n";

    $clientId = $clientData['client_id'];

    // Test 2: Send heartbeat
    echo "Test 2: Send Heartbeat\n";
    $heartbeatResult = $service->sendHeartbeat($clientId, ['test' => 'heartbeat'], '127.0.0.1');
    echo "[OK] Heartbeat sent: " . json_encode($heartbeatResult, JSON_PRETTY_PRINT) . "\n\n";

    // Test 3: Get all clients
    echo "Test 3: Get All Clients\n";
    $clients = $service->getClients();
    echo "[OK] Found " . count($clients) . " clients\n\n";

    // Test 4: Get single client
    echo "Test 4: Get Single Client\n";
    $client = $service->getClient($clientId);
    echo "[OK] Client details: " . json_encode($client, JSON_PRETTY_PRINT) . "\n\n";

    // Test 5: Update client
    echo "Test 5: Update Client\n";
    $updateResult = $service->updateClient($clientId, [
        'client_name' => 'Updated Test Client',
        'description' => 'Updated description'
    ]);
    echo "[OK] Client updated: " . json_encode($updateResult, JSON_PRETTY_PRINT) . "\n\n";

    // Test 6: Get stats
    echo "Test 6: Get Statistics\n";
    $stats = $service->getStats();
    echo "[OK] Stats: " . json_encode($stats, JSON_PRETTY_PRINT) . "\n\n";

    // Test 7: Delete client
    echo "Test 7: Delete Client\n";
    $deleteResult = $service->removeClient($clientId);
    echo "[OK] Client deleted: " . json_encode($deleteResult, JSON_PRETTY_PRINT) . "\n\n";

    echo "=== All Tests Passed! ===\n";

    $conn->close();

} catch (Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    exit(1);
}
