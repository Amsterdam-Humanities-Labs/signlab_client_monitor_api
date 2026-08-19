<?php
/**
 * Create sample data for Client Monitor Dashboard
 */

require_once __DIR__ . '/src/config.php';
require_once __DIR__ . '/src/ClientMonitorService.php';

try {
    $conn = getDbConnection();
    $service = new ClientMonitorService($conn);

    echo "Creating sample clients...\n\n";

    // Sample client 1: Online
    $client1 = $service->registerClient([
        'client_id' => 'video-transcription-cron',
        'client_name' => 'Video Transcription Job',
        'description' => 'Hourly cron job that processes video transcriptions',
        'heartbeat_interval' => 3600,
        'metadata' => [
            'server' => 'prod-01',
            'version' => '1.2.0'
        ]
    ]);
    echo "✓ Created: Video Transcription Job (Online)\n";

    // Sample client 2: Online
    $client2 = $service->registerClient([
        'client_id' => 'email-queue-processor',
        'client_name' => 'Email Queue Processor',
        'description' => 'Processes outgoing email queue every 5 minutes',
        'heartbeat_interval' => 300,
        'metadata' => [
            'server' => 'prod-02',
            'version' => '2.1.5'
        ]
    ]);
    echo "✓ Created: Email Queue Processor (Online)\n";

    // Sample client 3: Warning state (simulate)
    $client3 = $service->registerClient([
        'client_id' => 'backup-script',
        'client_name' => 'Daily Backup Script',
        'description' => 'Runs daily database backups at 2 AM',
        'heartbeat_interval' => 86400,
        'metadata' => [
            'server' => 'backup-01',
            'version' => '1.0.0'
        ]
    ]);
    // Set last_seen to warning threshold
    $conn->query("UPDATE client_monitors SET last_seen = DATE_SUB(NOW(), INTERVAL 100000 SECOND) WHERE client_id = 'backup-script'");
    echo "✓ Created: Daily Backup Script (Warning)\n";

    // Sample client 4: Offline state (simulate)
    $client4 = $service->registerClient([
        'client_id' => 'analytics-worker',
        'client_name' => 'Analytics Processing Worker',
        'description' => 'Background worker for analytics data processing',
        'heartbeat_interval' => 1800,
        'metadata' => [
            'server' => 'worker-03',
            'version' => '3.0.1'
        ]
    ]);
    // Set last_seen to offline threshold
    $conn->query("UPDATE client_monitors SET last_seen = DATE_SUB(NOW(), INTERVAL 7200 SECOND) WHERE client_id = 'analytics-worker'");
    echo "✓ Created: Analytics Processing Worker (Offline)\n";

    // Sample client 5: Online
    $client5 = $service->registerClient([
        'client_id' => 'thumbnail-generator',
        'client_name' => 'Thumbnail Generator',
        'description' => 'Generates thumbnails for uploaded images',
        'heartbeat_interval' => 600,
        'metadata' => [
            'server' => 'media-01',
            'version' => '1.5.2'
        ]
    ]);
    echo "✓ Created: Thumbnail Generator (Online)\n";

    echo "\n" . str_repeat("=", 50) . "\n";
    echo "Sample data created successfully!\n";
    echo "Access the dashboard at: http://your-server/client_monitor_dashboard/\n";
    echo str_repeat("=", 50) . "\n";

    $conn->close();

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
