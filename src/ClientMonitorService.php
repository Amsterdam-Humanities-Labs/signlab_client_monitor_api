<?php
/**
 * Client Monitor Service
 * Handles all business logic for client monitoring system
 */

class ClientMonitorService {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    /**
     * Register a new client for monitoring
     */
    public function registerClient($clientData) {
        $clientId = $clientData['client_id'] ?? null;
        $clientName = $clientData['client_name'] ?? null;
        $description = $clientData['description'] ?? null;
        $heartbeatInterval = $clientData['heartbeat_interval'] ?? 3600;
        $metadata = isset($clientData['metadata']) ? json_encode($clientData['metadata']) : null;
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;

        // Validate required fields
        if (!$clientId || !$clientName) {
            throw new Exception('client_id and client_name are required');
        }

        // Check if client already exists
        $stmt = $this->conn->prepare("SELECT id FROM client_monitors WHERE client_id = ?");
        $stmt->bind_param("s", $clientId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            throw new Exception('Client with this client_id already exists');
        }

        // Insert new client
        $stmt = $this->conn->prepare(
            "INSERT INTO client_monitors (client_id, client_name, description, heartbeat_interval, metadata, ip_address, last_seen, status)
             VALUES (?, ?, ?, ?, ?, ?, NOW(), 'online')"
        );
        $stmt->bind_param("sssiss", $clientId, $clientName, $description, $heartbeatInterval, $metadata, $ipAddress);

        if (!$stmt->execute()) {
            throw new Exception('Failed to register client: ' . $stmt->error);
        }

        return [
            'id' => $this->conn->insert_id,
            'client_id' => $clientId,
            'status' => 'online',
            'created_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Send heartbeat for a client (update last_seen)
     */
    public function sendHeartbeat($clientId, $metadata = null, $ipAddress = null) {
        if (!$clientId) {
            throw new Exception('client_id is required');
        }

        // Check if client exists
        $stmt = $this->conn->prepare("SELECT id FROM client_monitors WHERE client_id = ?");
        $stmt->bind_param("s", $clientId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            throw new Exception('Client not found. Please register first.');
        }

        // Update last_seen and optionally metadata
        if ($metadata !== null) {
            $metadataJson = json_encode($metadata);
            $stmt = $this->conn->prepare(
                "UPDATE client_monitors SET last_seen = NOW(), metadata = ?, ip_address = ? WHERE client_id = ?"
            );
            $stmt->bind_param("sss", $metadataJson, $ipAddress, $clientId);
        } else {
            $stmt = $this->conn->prepare(
                "UPDATE client_monitors SET last_seen = NOW(), ip_address = ? WHERE client_id = ?"
            );
            $stmt->bind_param("ss", $ipAddress, $clientId);
        }

        if (!$stmt->execute()) {
            throw new Exception('Failed to send heartbeat: ' . $stmt->error);
        }

        // Get updated client data
        $client = $this->getClient($clientId);

        return [
            'status' => $client['status'],
            'last_seen' => $client['last_seen']
        ];
    }

    /**
     * Get all clients with optional status filter
     */
    public function getClients($statusFilter = null) {
        $query = "SELECT * FROM client_monitors";
        $params = [];
        $types = "";

        if ($statusFilter && in_array($statusFilter, ['online', 'offline', 'warning'])) {
            $query .= " WHERE status = ?";
            $params[] = $statusFilter;
            $types = "s";
        }

        $query .= " ORDER BY client_name ASC";

        if ($types) {
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $this->conn->query($query);
        }

        $clients = [];
        while ($row = $result->fetch_assoc()) {
            $clients[] = $this->formatClient($row);
        }

        return $clients;
    }

    /**
     * Get single client by client_id
     */
    public function getClient($clientId) {
        $stmt = $this->conn->prepare("SELECT * FROM client_monitors WHERE client_id = ?");
        $stmt->bind_param("s", $clientId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            throw new Exception('Client not found');
        }

        $client = $result->fetch_assoc();
        return $this->formatClient($client);
    }

    /**
     * Update client information
     */
    public function updateClient($clientId, $updateData) {
        if (!$clientId) {
            throw new Exception('client_id is required');
        }

        // Check if client exists
        $stmt = $this->conn->prepare("SELECT id FROM client_monitors WHERE client_id = ?");
        $stmt->bind_param("s", $clientId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            throw new Exception('Client not found');
        }

        // Build update query dynamically
        $updates = [];
        $params = [];
        $types = "";

        if (isset($updateData['client_name'])) {
            $updates[] = "client_name = ?";
            $params[] = $updateData['client_name'];
            $types .= "s";
        }

        if (isset($updateData['description'])) {
            $updates[] = "description = ?";
            $params[] = $updateData['description'];
            $types .= "s";
        }

        if (isset($updateData['heartbeat_interval'])) {
            $updates[] = "heartbeat_interval = ?";
            $params[] = $updateData['heartbeat_interval'];
            $types .= "i";
        }

        if (isset($updateData['metadata'])) {
            $updates[] = "metadata = ?";
            $params[] = json_encode($updateData['metadata']);
            $types .= "s";
        }

        if (empty($updates)) {
            throw new Exception('No fields to update');
        }

        $params[] = $clientId;
        $types .= "s";

        $query = "UPDATE client_monitors SET " . implode(", ", $updates) . " WHERE client_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param($types, ...$params);

        if (!$stmt->execute()) {
            throw new Exception('Failed to update client: ' . $stmt->error);
        }

        return ['updated' => true];
    }

    /**
     * Remove a client from monitoring
     */
    public function removeClient($clientId) {
        if (!$clientId) {
            throw new Exception('client_id is required');
        }

        $stmt = $this->conn->prepare("DELETE FROM client_monitors WHERE client_id = ?");
        $stmt->bind_param("s", $clientId);

        if (!$stmt->execute()) {
            throw new Exception('Failed to delete client: ' . $stmt->error);
        }

        if ($stmt->affected_rows === 0) {
            throw new Exception('Client not found');
        }

        return ['deleted' => true];
    }

    /**
     * Get summary statistics
     */
    public function getStats() {
        $result = $this->conn->query("SELECT
            COUNT(*) as total,
            SUM(CASE WHEN status = 'online' THEN 1 ELSE 0 END) as online,
            SUM(CASE WHEN status = 'offline' THEN 1 ELSE 0 END) as offline,
            SUM(CASE WHEN status = 'warning' THEN 1 ELSE 0 END) as warning
            FROM client_monitors"
        );

        $stats = $result->fetch_assoc();

        return [
            'total' => (int)$stats['total'],
            'online' => (int)$stats['online'],
            'offline' => (int)$stats['offline'],
            'warning' => (int)$stats['warning']
        ];
    }

    /**
     * Submit system metrics for a client
     *
     * @param string $clientId Client identifier
     * @param array $metrics Array containing cpu_percent, disk_usage_percent, etc.
     * @return array ['metrics_id' => int, 'timestamp' => string]
     * @throws Exception if client not found or validation fails
     */
    public function submitMetrics($clientId, $metrics) {
        if (!$clientId) {
            throw new Exception('client_id is required');
        }

        // Validate client exists
        $stmt = $this->conn->prepare("SELECT id FROM client_monitors WHERE client_id = ?");
        $stmt->bind_param("s", $clientId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            throw new Exception('Client not found. Please register first.');
        }

        // Validate required fields
        if (!isset($metrics['cpu_percent'])) {
            throw new Exception('cpu_percent is required');
        }
        if (!isset($metrics['disk_usage_percent'])) {
            throw new Exception('disk_usage_percent is required');
        }

        // Validate ranges for required fields
        if ($metrics['cpu_percent'] < 0 || $metrics['cpu_percent'] > 100) {
            throw new Exception('cpu_percent must be between 0 and 100');
        }
        if ($metrics['disk_usage_percent'] < 0 || $metrics['disk_usage_percent'] > 100) {
            throw new Exception('disk_usage_percent must be between 0 and 100');
        }

        // Validate optional fields if present
        if (isset($metrics['cpu_wait_percent']) && ($metrics['cpu_wait_percent'] < 0 || $metrics['cpu_wait_percent'] > 100)) {
            throw new Exception('cpu_wait_percent must be between 0 and 100');
        }
        if (isset($metrics['memory_percent']) && ($metrics['memory_percent'] < 0 || $metrics['memory_percent'] > 100)) {
            throw new Exception('memory_percent must be between 0 and 100');
        }

        // Prepare values
        $cpuPercent = $metrics['cpu_percent'];
        $cpuWaitPercent = $metrics['cpu_wait_percent'] ?? null;
        $diskUsagePercent = $metrics['disk_usage_percent'];
        $diskTotalGb = $metrics['disk_total_gb'] ?? null;
        $diskUsedGb = $metrics['disk_used_gb'] ?? null;
        $diskFreeGb = $metrics['disk_free_gb'] ?? null;
        $memoryPercent = $metrics['memory_percent'] ?? null;

        // Insert metrics
        $stmt = $this->conn->prepare(
            "INSERT INTO client_metrics (
                client_id, cpu_percent, cpu_wait_percent, disk_usage_percent,
                disk_total_gb, disk_used_gb, disk_free_gb, memory_percent
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param(
            "sddddddd",
            $clientId,
            $cpuPercent,
            $cpuWaitPercent,
            $diskUsagePercent,
            $diskTotalGb,
            $diskUsedGb,
            $diskFreeGb,
            $memoryPercent
        );

        if (!$stmt->execute()) {
            throw new Exception('Failed to store metrics: ' . $stmt->error);
        }

        $metricsId = $this->conn->insert_id;

        // Get the timestamp
        $stmt = $this->conn->prepare("SELECT timestamp FROM client_metrics WHERE id = ?");
        $stmt->bind_param("i", $metricsId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        return [
            'metrics_id' => $metricsId,
            'client_id' => $clientId,
            'timestamp' => $row['timestamp']
        ];
    }

    /**
     * Get metrics history for a client
     *
     * @param string $clientId Client identifier
     * @param int $hours Number of hours to retrieve (default: 168 = 7 days)
     * @return array ['metrics' => array, 'summary' => array, 'period' => array]
     * @throws Exception if client not found
     */
    public function getMetrics($clientId, $hours = 168) {
        if (!$clientId) {
            throw new Exception('client_id is required');
        }

        // Validate client exists
        $stmt = $this->conn->prepare("SELECT id FROM client_monitors WHERE client_id = ?");
        $stmt->bind_param("s", $clientId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            // Return empty metrics instead of throwing error for unregistered clients
            return [
                'client_id' => $clientId,
                'period' => [
                    'start' => date('Y-m-d H:i:s', strtotime("-$hours hours")),
                    'end' => date('Y-m-d H:i:s'),
                    'hours' => $hours
                ],
                'metrics' => [],
                'summary' => [
                    'avg_cpu' => 0,
                    'max_cpu' => 0,
                    'avg_disk_usage' => 0,
                    'avg_cpu_wait' => 0
                ]
            ];
        }

        // Query metrics (limit to 200 most recent within time range)
        $stmt = $this->conn->prepare(
            "SELECT timestamp, cpu_percent, cpu_wait_percent, disk_usage_percent,
                    disk_free_gb, memory_percent
             FROM client_metrics
             WHERE client_id = ? AND timestamp >= DATE_SUB(NOW(), INTERVAL ? HOUR)
             ORDER BY timestamp DESC
             LIMIT 200"
        );
        $stmt->bind_param("si", $clientId, $hours);
        $stmt->execute();
        $result = $stmt->get_result();

        $metrics = [];
        while ($row = $result->fetch_assoc()) {
            $metrics[] = [
                'timestamp' => $row['timestamp'],
                'cpu_percent' => (float)$row['cpu_percent'],
                'cpu_wait_percent' => $row['cpu_wait_percent'] ? (float)$row['cpu_wait_percent'] : null,
                'disk_usage_percent' => (float)$row['disk_usage_percent'],
                'disk_free_gb' => $row['disk_free_gb'] ? (float)$row['disk_free_gb'] : null,
                'memory_percent' => $row['memory_percent'] ? (float)$row['memory_percent'] : null
            ];
        }

        // Calculate summary statistics
        $summary = [
            'avg_cpu' => 0,
            'max_cpu' => 0,
            'avg_disk_usage' => 0,
            'avg_cpu_wait' => 0
        ];

        if (count($metrics) > 0) {
            $totalCpu = 0;
            $totalDisk = 0;
            $totalWait = 0;
            $waitCount = 0;
            $maxCpu = 0;

            foreach ($metrics as $m) {
                $totalCpu += $m['cpu_percent'];
                $totalDisk += $m['disk_usage_percent'];
                if ($m['cpu_wait_percent'] !== null) {
                    $totalWait += $m['cpu_wait_percent'];
                    $waitCount++;
                }
                if ($m['cpu_percent'] > $maxCpu) {
                    $maxCpu = $m['cpu_percent'];
                }
            }

            $count = count($metrics);
            $summary['avg_cpu'] = round($totalCpu / $count, 1);
            $summary['max_cpu'] = round($maxCpu, 1);
            $summary['avg_disk_usage'] = round($totalDisk / $count, 1);
            $summary['avg_cpu_wait'] = $waitCount > 0 ? round($totalWait / $waitCount, 1) : 0;
        }

        // Calculate period info
        $endTime = date('Y-m-d H:i:s');
        $startTime = date('Y-m-d H:i:s', strtotime("-$hours hours"));

        return [
            'client_id' => $clientId,
            'period' => [
                'start' => $startTime,
                'end' => $endTime,
                'hours' => $hours
            ],
            'metrics' => $metrics,
            'summary' => $summary
        ];
    }

    /**
     * Calculate client status based on last_seen timestamp
     */
    private function calculateStatus($lastSeen, $heartbeatInterval, $warningThreshold, $offlineThreshold) {
        if (!$lastSeen) {
            return 'offline';
        }

        $lastSeenTime = strtotime($lastSeen);
        $currentTime = time();
        $timeSinceLastSeen = $currentTime - $lastSeenTime;

        $warningSeconds = $heartbeatInterval * $warningThreshold;
        $offlineSeconds = $heartbeatInterval * $offlineThreshold;

        if ($timeSinceLastSeen > $offlineSeconds) {
            return 'offline';
        } elseif ($timeSinceLastSeen > $warningSeconds) {
            return 'warning';
        } else {
            return 'online';
        }
    }

    /**
     * Format client data with calculated status
     */
    private function formatClient($row) {
        // Calculate current status
        $calculatedStatus = $this->calculateStatus(
            $row['last_seen'],
            $row['heartbeat_interval'],
            $row['warning_threshold'],
            $row['offline_threshold']
        );

        // Update status in database if it changed
        if ($calculatedStatus !== $row['status']) {
            $stmt = $this->conn->prepare("UPDATE client_monitors SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $calculatedStatus, $row['id']);
            $stmt->execute();
            $row['status'] = $calculatedStatus;
        }

        return [
            'id' => (int)$row['id'],
            'client_id' => $row['client_id'],
            'client_name' => $row['client_name'],
            'description' => $row['description'],
            'status' => $row['status'],
            'last_seen' => $row['last_seen'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
            'ip_address' => $row['ip_address'],
            'metadata' => $row['metadata'] ? json_decode($row['metadata'], true) : null,
            'heartbeat_interval' => (int)$row['heartbeat_interval'],
            'warning_threshold' => (float)$row['warning_threshold'],
            'offline_threshold' => (float)$row['offline_threshold']
        ];
    }
}
