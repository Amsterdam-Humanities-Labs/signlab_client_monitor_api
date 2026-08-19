<?php
/**
 * Client Monitor API - PHP Integration Example
 *
 * This script demonstrates how to integrate your PHP scripts with the
 * Client Monitor system.
 *
 * Usage:
 *   1. Copy the ClientMonitor class to your project
 *   2. Initialize with your client details
 *   3. Call sendHeartbeat() after successful execution
 *
 * Example cron job (runs every hour):
 *   0 * * * * /usr/bin/php /path/to/your-script.php
 */

/**
 * Client Monitor API wrapper for PHP scripts
 */
class ClientMonitor {
    private $apiUrl;
    private $clientId;
    private $clientName;
    private $description;
    private $heartbeatInterval;
    private $hostname;

    /**
     * Initialize the client monitor
     *
     * @param string $apiUrl Base URL of the Client Monitor API
     * @param string $clientId Unique identifier for this client
     * @param string $clientName Display name for the client
     * @param string $description Description of what this client does
     * @param int $heartbeatInterval Expected heartbeat interval in seconds (default: 3600)
     */
    public function __construct(
        string $apiUrl,
        string $clientId,
        string $clientName,
        string $description = '',
        int $heartbeatInterval = 3600
    ) {
        $this->apiUrl = $apiUrl;
        $this->clientId = $clientId;
        $this->clientName = $clientName;
        $this->description = $description;
        $this->heartbeatInterval = $heartbeatInterval;
        $this->hostname = gethostname();
    }

    /**
     * Register this client with the monitoring system
     *
     * @param array|null $metadata Optional custom metadata
     * @return array API response
     * @throws Exception If the API call fails
     */
    public function register(?array $metadata = null): array {
        $data = [
            'client_id' => $this->clientId,
            'client_name' => $this->clientName,
            'description' => $this->description,
            'heartbeat_interval' => $this->heartbeatInterval,
            'metadata' => $metadata ?? [
                'hostname' => $this->hostname,
                'php_version' => PHP_VERSION,
                'registered_at' => date('c')
            ]
        ];

        $response = $this->makeRequest('register', $data);

        if ($response['success']) {
            echo "✓ Client registered successfully: {$this->clientId}\n";
        } else {
            echo "✗ Registration failed: " . implode(', ', $response['errors']) . "\n";
        }

        return $response;
    }

    /**
     * Send a heartbeat to update the last_seen timestamp
     *
     * @param array|null $metadata Optional custom metadata to include
     * @return array API response
     * @throws Exception If the API call fails
     */
    public function sendHeartbeat(?array $metadata = null): array {
        $data = [
            'client_id' => $this->clientId,
            'metadata' => $metadata ?? [
                'last_run' => date('c'),
                'hostname' => $this->hostname
            ]
        ];

        $response = $this->makeRequest('heartbeat', $data);

        if ($response['success']) {
            $status = $response['data']['status'] ?? 'unknown';
            echo "✓ Heartbeat sent successfully - Status: {$status}\n";
        } else {
            echo "✗ Heartbeat failed: " . implode(', ', $response['errors']) . "\n";
        }

        return $response;
    }

    /**
     * Send a heartbeat with status and statistics
     *
     * @param string $status Status of the execution (e.g., 'success', 'error', 'warning')
     * @param string $message Description message
     * @param array|null $stats Optional statistics
     * @return array API response
     */
    public function sendHeartbeatWithStats(
        string $status,
        string $message,
        ?array $stats = null
    ): array {
        $metadata = [
            'last_run' => date('c'),
            'hostname' => $this->hostname,
            'status' => $status,
            'message' => $message
        ];

        if ($stats) {
            $metadata = array_merge($metadata, $stats);
        }

        return $this->sendHeartbeat($metadata);
    }

    /**
     * Make an API request
     *
     * @param string $action API action
     * @param array $data Request data
     * @return array API response
     * @throws Exception If the request fails
     */
    private function makeRequest(string $action, array $data): array {
        $url = $this->apiUrl . '?action=' . $action;
        $jsonData = json_encode($data);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("cURL error: {$error}");
        }

        if ($httpCode >= 400) {
            throw new Exception("API request failed with HTTP {$httpCode}: {$response}");
        }

        $result = json_decode($response, true);
        if ($result === null) {
            throw new Exception("Invalid JSON response: {$response}");
        }

        return $result;
    }
}

// ==============================================================================
// EXAMPLE USAGE
// ==============================================================================

/**
 * Example script showing how to use the ClientMonitor class
 */
function main() {
    // Configuration
    $apiUrl = 'http://localhost/client_monitor_api/api.php';
    $clientId = 'php-email-processor';
    $clientName = 'PHP Email Processor';
    $description = 'Processes email queue and sends notifications';
    $heartbeatInterval = 3600; // 1 hour

    // Initialize monitor
    $monitor = new ClientMonitor(
        $apiUrl,
        $clientId,
        $clientName,
        $description,
        $heartbeatInterval
    );

    echo str_repeat('=', 60) . "\n";
    echo "Starting: {$clientName}\n";
    echo "Time: " . date('Y-m-d H:i:s') . "\n";
    echo str_repeat('=', 60) . "\n";

    try {
        // YOUR SCRIPT LOGIC HERE
        // Example:

        // Step 1: Connect to database
        echo "Connecting to database...\n";

        // Step 2: Process emails
        echo "Processing emails...\n";
        $emailsProcessed = 0;
        for ($i = 0; $i < 50; $i++) {
            // Simulate processing
            $emailsProcessed++;
        }

        // Step 3: Generate reports
        echo "Generating reports...\n";
        $reportsSent = 3;

        // Send heartbeat on success
        $monitor->sendHeartbeatWithStats(
            'success',
            "Processed {$emailsProcessed} emails",
            [
                'emails_processed' => $emailsProcessed,
                'reports_sent' => $reportsSent,
                'execution_time_seconds' => 15
            ]
        );

        echo "\n✓ Script completed successfully\n";
        return 0;

    } catch (Exception $e) {
        echo "\n✗ Script failed: {$e->getMessage()}\n";

        // Send heartbeat on error
        try {
            $monitor->sendHeartbeatWithStats(
                'error',
                "Script failed: {$e->getMessage()}",
                ['error_type' => get_class($e)]
            );
        } catch (Exception $heartbeatError) {
            echo "✗ Failed to send error heartbeat: {$heartbeatError->getMessage()}\n";
        }

        return 1;
    }
}

// ==============================================================================
// USAGE EXAMPLES
// ==============================================================================

/**
 * Example 1: Simple usage with minimal configuration
 */
function exampleSimpleUsage() {
    $monitor = new ClientMonitor(
        'http://localhost/client_monitor_api/api.php',
        'simple-php-script',
        'Simple PHP Script'
    );

    // Do your work...
    echo "Doing some work...\n";

    // Send heartbeat
    $monitor->sendHeartbeat();
}

/**
 * Example 2: Usage with custom metadata
 */
function exampleWithMetadata() {
    $monitor = new ClientMonitor(
        'http://localhost/client_monitor_api/api.php',
        'metadata-php-script',
        'PHP Script with Metadata'
    );

    // Do your work...
    $recordsProcessed = 150;

    // Send heartbeat with custom metadata
    $monitor->sendHeartbeat([
        'records_processed' => $recordsProcessed,
        'database_version' => '8.0',
        'custom_field' => 'value'
    ]);
}

/**
 * Example 3: Register a new client (run once)
 */
function exampleRegistration() {
    $monitor = new ClientMonitor(
        'http://localhost/client_monitor_api/api.php',
        'new-php-client',
        'New PHP Client',
        'This is a new PHP script to monitor',
        1800  // 30 minutes
    );

    // Register the client
    $monitor->register([
        'version' => '2.0.0',
        'environment' => 'production'
    ]);
}

/**
 * Example 4: Integration with existing script
 */
function exampleIntegration() {
    // Your existing script...
    function processData() {
        // ... your logic ...
        return ['records' => 100, 'errors' => 0];
    }

    // Add monitoring
    $monitor = new ClientMonitor(
        'http://localhost/client_monitor_api/api.php',
        'existing-script',
        'Existing Script'
    );

    try {
        $result = processData();

        // Send heartbeat on success
        $monitor->sendHeartbeatWithStats(
            'success',
            "Processed {$result['records']} records with {$result['errors']} errors",
            $result
        );
    } catch (Exception $e) {
        // Send heartbeat on error
        $monitor->sendHeartbeatWithStats('error', $e->getMessage());
        throw $e;
    }
}

// Run the main example
if (php_sapi_name() === 'cli') {
    exit(main());
}
