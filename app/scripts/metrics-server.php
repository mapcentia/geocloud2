<?php
/**
 * Standalone Prometheus metrics server
 * 
 * This script runs a lightweight web server on a dedicated port
 * to expose Prometheus metrics without affecting the main application.
 */

// Set error reporting and path
ini_set("display_errors", "no");
error_reporting(E_ERROR | E_WARNING | E_PARSE);

// Include required files
include_once(__DIR__ . '/../vendor/autoload.php');
include_once(__DIR__ . '/../conf/App.php');
include_once(__DIR__ . '/../inc/Globals.php');

use app\inc\Metrics;
use app\conf\App;

// Configuration
$metricsPort = App::$param['metricsPort'] ?? 9100; // Default port for metrics server
$metricsHost = App::$param['metricsHost'] ?? '127.0.0.1'; // Default bind to localhost

// Create a simple socket server
$server = @stream_socket_server("tcp://$metricsHost:$metricsPort", $errno, $errstr);
if (!$server) {
    error_log("Metrics server error: $errstr ($errno)");
    exit(1);
}

echo "Prometheus metrics server started on $metricsHost:$metricsPort (available at http://$metricsHost:$metricsPort/metrics)\n";

// Main server loop
while ($conn = @stream_socket_accept($server, -1)) {
    // Read the HTTP request and parse the path
    $request = '';
    $path = '';
    $firstLine = true;
    
    while ($line = fgets($conn)) {
        $request .= $line;
        
        // Extract the path from the first line (GET /path HTTP/1.1)
        if ($firstLine) {
            $parts = explode(' ', trim($line));
            if (count($parts) >= 2) {
                $path = $parts[1];
            }
            $firstLine = false;
        }
        
        if (trim($line) === '') {
            break; // End of headers
        }
    }
    
    // Only respond to /metrics path
    if ($path === '/metrics') {
        // Get metrics data
        $metrics = Metrics::getMetrics();
        
        // Send HTTP response with metrics
        $response = "HTTP/1.1 200 OK\r\n";
        $response .= "Content-Type: text/plain; version=0.0.4\r\n";
        $response .= "Content-Length: " . strlen($metrics) . "\r\n";
        $response .= "Connection: close\r\n\r\n";
        $response .= $metrics;
    } else if ($path === '/' || $path === '') {
        // For root path, provide information
        $infoMessage = "GeoCloud2 Metrics Server\n\nMetrics are available at: /metrics\n\nConfigure Prometheus to scrape: http://$metricsHost:$metricsPort/metrics";
        $response = "HTTP/1.1 200 OK\r\n";
        $response .= "Content-Type: text/plain\r\n";
        $response .= "Content-Length: " . strlen($infoMessage) . "\r\n";
        $response .= "Connection: close\r\n\r\n";
        $response .= $infoMessage;
    } else {
        // For any other path, return 404 Not Found
        $notFoundMessage = "Not Found. The metrics are available at /metrics";
        $response = "HTTP/1.1 404 Not Found\r\n";
        $response .= "Content-Type: text/plain\r\n";
        $response .= "Content-Length: " . strlen($notFoundMessage) . "\r\n";
        $response .= "Connection: close\r\n\r\n";
        $response .= $notFoundMessage;
    }
    
    fwrite($conn, $response);
    fclose($conn);
}

fclose($server);
