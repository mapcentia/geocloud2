#!/bin/bash
# Script to install and manage the GeoCloud2 metrics server

set -e

# Define paths
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"
SERVICE_FILE="$SCRIPT_DIR/../docker/conf/geocloud2-metrics.service"
SYSTEMD_DIR="/etc/systemd/system"

# Function to install the service
install_service() {
    echo "Installing GeoCloud2 metrics server service..."
    cp "$SERVICE_FILE" "$SYSTEMD_DIR/"
    systemctl daemon-reload
    systemctl enable geocloud2-metrics.service
    echo "Service installed. Use 'systemctl start geocloud2-metrics.service' to start it."
}

# Function to start the service
start_service() {
    echo "Starting GeoCloud2 metrics server..."
    systemctl start geocloud2-metrics.service
    systemctl status geocloud2-metrics.service
}

# Function to stop the service
stop_service() {
    echo "Stopping GeoCloud2 metrics server..."
    systemctl stop geocloud2-metrics.service
    systemctl status geocloud2-metrics.service
}

# Function to restart the service
restart_service() {
    echo "Restarting GeoCloud2 metrics server..."
    systemctl restart geocloud2-metrics.service
    systemctl status geocloud2-metrics.service
}

# Function to check service status
status_service() {
    systemctl status geocloud2-metrics.service
}

# Function to run the server directly
run_server() {
    echo "Running metrics server directly (press Ctrl+C to stop)..."
    php "$SCRIPT_DIR/metrics-server.php"
}

# Main script logic
case "$1" in
    install)
        install_service
        ;;
    start)
        start_service
        ;;
    stop)
        stop_service
        ;;
    restart)
        restart_service
        ;;
    status)
        status_service
        ;;
    run)
        run_server
        ;;
    *)
        echo "Usage: $0 {install|start|stop|restart|status|run}"
        exit 1
        ;;
esac

exit 0
