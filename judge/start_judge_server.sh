#!/bin/bash
# Judge Server startup script for AirOJ
# Usage: sudo ./start_judge_server.sh [start|stop|restart]

cd "$(dirname "$0")"

export TOKEN=${TOKEN:-"AIR_JUDGE_TOKEN_DEV"}
export BACKEND_URL=${BACKEND_URL:-"http://localhost:8080/api/judge_server_heartbeat"}
export SERVICE_URL=${SERVICE_URL:-"http://localhost:12358"}
export JUDGER_PATH=${JUDGER_PATH:-"$(pwd)/Judger/output/libjudger.so"}
export judger_debug=1

VENV_PYTHON="$(dirname "$(pwd)")/venv/bin/python3"
SERVER_DIR="$(pwd)/JudgeServer/server"

# Ensure log directory exists
sudo mkdir -p /log

start() {
    echo "Starting Judge Server..."
    echo "TOKEN: $TOKEN"
    echo "JUDGER_PATH: $JUDGER_PATH"
    cd "$SERVER_DIR"
    exec "$VENV_PYTHON" -m gunicorn server:app \
        --workers 2 \
        --threads 4 \
        --error-logfile /log/gunicorn.log \
        --bind 0.0.0.0:12358 \
        --user root \
        --access-logfile /log/access.log
}

stop() {
    echo "Stopping Judge Server..."
    pkill -f "gunicorn.*server:app" 2>/dev/null || true
}

case "${1:-start}" in
    start)
        start
        ;;
    stop)
        stop
        ;;
    restart)
        stop
        sleep 1
        start
        ;;
    *)
        echo "Usage: $0 [start|stop|restart]"
        exit 1
esac
