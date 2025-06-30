<?php
/**
 * Prometheus metrics endpoint
 * 
 * This file exposes metrics in the Prometheus format.
 * Configure Prometheus to scrape this endpoint.
 */

// Initialize the application
require_once __DIR__ . '/../app/conf/App.php';
new \app\conf\App();

use app\inc\Metrics;

// Set content type for Prometheus format
header('Content-Type: text/plain; version=0.0.4');

// Get the registry
$registry = Metrics::getRegistry();

// Output metrics in Prometheus format
echo $registry->getMetricFamilySamples();
