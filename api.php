<?php
/**
 * Client Monitor API
 * Main API router for client monitoring system
 *
 * Usage:
 *   POST /client_monitor_api/api.php?action=register
 *   POST /client_monitor_api/api.php?action=heartbeat
 *   GET  /client_monitor_api/api.php?action=get_clients&status=online|offline|warning
 *   GET  /client_monitor_api/api.php?action=get_client&client_id=XXX
 *   POST /client_monitor_api/api.php?action=update_client
 *   POST /client_monitor_api/api.php?action=delete_client
 *   GET  /client_monitor_api/api.php?action=get_stats
 *   POST /client_monitor_api/api.php?action=submit_metrics
 *   GET  /client_monitor_api/api.php?action=get_metrics&client_id=XXX&hours=168
 */

require_once __DIR__ . '/src/config.php';
require_once __DIR__ . '/src/ClientMonitorService.php';

try {
    // Get database connection
    $conn = getDbConnection();

    // Create service instance
    $service = new ClientMonitorService($conn);

    // Get action from query parameter
    $action = $_GET['action'] ?? null;

    if (!$action) {
        sendError('Action parameter is required', 400);
    }

    // Route to appropriate handler
    switch ($action) {
        case 'register':
            handleRegister($service);
            break;

        case 'heartbeat':
            handleHeartbeat($service);
            break;

        case 'get_clients':
            handleGetClients($service);
            break;

        case 'get_client':
            handleGetClient($service);
            break;

        case 'update_client':
            handleUpdateClient($service);
            break;

        case 'delete_client':
            handleDeleteClient($service);
            break;

        case 'get_stats':
            handleGetStats($service);
            break;

        case 'submit_metrics':
            handleSubmitMetrics($service);
            break;

        case 'get_metrics':
            handleGetMetrics($service);
            break;

        default:
            sendError("Unknown action: $action", 400);
    }

} catch (Exception $e) {
    sendError($e->getMessage(), 500);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}

/**
 * Handle client registration
 * POST /api.php?action=register
 * Body: { client_id, client_name, description?, heartbeat_interval?, metadata? }
 */
function handleRegister($service) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendError('Method not allowed. Use POST.', 405);
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        sendError('Invalid JSON input', 400);
    }

    $result = $service->registerClient($input);
    sendSuccess($result, 201);
}

/**
 * Handle heartbeat update
 * POST /api.php?action=heartbeat
 * Body: { client_id, metadata? }
 */
function handleHeartbeat($service) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendError('Method not allowed. Use POST.', 405);
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || !isset($input['client_id'])) {
        sendError('Invalid input. client_id is required.', 400);
    }

    $clientId = $input['client_id'];
    $metadata = $input['metadata'] ?? null;
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;

    $result = $service->sendHeartbeat($clientId, $metadata, $ipAddress);
    sendSuccess($result);
}

/**
 * Handle get all clients
 * GET /api.php?action=get_clients&status=online|offline|warning
 */
function handleGetClients($service) {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        sendError('Method not allowed. Use GET.', 405);
    }

    $statusFilter = $_GET['status'] ?? null;
    $clients = $service->getClients($statusFilter);
    sendSuccess($clients);
}

/**
 * Handle get single client
 * GET /api.php?action=get_client&client_id=XXX
 */
function handleGetClient($service) {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        sendError('Method not allowed. Use GET.', 405);
    }

    $clientId = $_GET['client_id'] ?? null;

    if (!$clientId) {
        sendError('client_id parameter is required', 400);
    }

    $client = $service->getClient($clientId);
    sendSuccess($client);
}

/**
 * Handle client update
 * POST /api.php?action=update_client
 * Body: { client_id, client_name?, description?, heartbeat_interval?, metadata? }
 */
function handleUpdateClient($service) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendError('Method not allowed. Use POST.', 405);
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || !isset($input['client_id'])) {
        sendError('Invalid input. client_id is required.', 400);
    }

    $clientId = $input['client_id'];
    unset($input['client_id']); // Remove client_id from update data

    $result = $service->updateClient($clientId, $input);
    sendSuccess($result);
}

/**
 * Handle client deletion
 * POST /api.php?action=delete_client
 * Body: { client_id }
 */
function handleDeleteClient($service) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendError('Method not allowed. Use POST.', 405);
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || !isset($input['client_id'])) {
        sendError('Invalid input. client_id is required.', 400);
    }

    $result = $service->removeClient($input['client_id']);
    sendSuccess($result);
}

/**
 * Handle get statistics
 * GET /api.php?action=get_stats
 */
function handleGetStats($service) {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        sendError('Method not allowed. Use GET.', 405);
    }

    $stats = $service->getStats();
    sendSuccess($stats);
}

/**
 * Handle submit metrics
 * POST /api.php?action=submit_metrics
 * Body: { client_id, metrics: { cpu_percent, disk_usage_percent, ... } }
 */
function handleSubmitMetrics($service) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendError('Method not allowed. Use POST.', 405);
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || !isset($input['client_id']) || !isset($input['metrics'])) {
        sendError('Invalid input. client_id and metrics are required.', 400);
    }

    $result = $service->submitMetrics($input['client_id'], $input['metrics']);
    sendSuccess($result, 201);
}

/**
 * Handle get metrics
 * GET /api.php?action=get_metrics&client_id=XXX&hours=168
 */
function handleGetMetrics($service) {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        sendError('Method not allowed. Use GET.', 405);
    }

    $clientId = $_GET['client_id'] ?? null;

    if (!$clientId) {
        sendError('client_id parameter is required.', 400);
    }

    $hours = isset($_GET['hours']) ? (int)$_GET['hours'] : 168;

    $result = $service->getMetrics($clientId, $hours);
    sendSuccess($result, 200);
}
