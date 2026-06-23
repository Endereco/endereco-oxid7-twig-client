#!/usr/bin/env bash
set -euo pipefail

# Supported OXID versions.
# Uses indexed array (not declare -A) for bash 3.2 compatibility (macOS default).
declare -a versions=("7.1" "7.2" "7.3" "7.4" "7.5")

# Helper: check if element is in array
containsElement() {
    local match="$1"
    shift
    for e; do [[ "$e" == "$match" ]] && return 0; done
    return 1
}

# ./playground.sh stop — detect and remove running playground containers
if [[ "${1:-}" == "stop" ]]; then
    running=()
    while IFS= read -r name; do
        running+=("$name")
    done < <(docker ps --format "{{.Names}}" | grep -E "^oxid7-[0-9]+\.[0-9]+$" || true)

    if [ ${#running[@]} -eq 0 ]; then
        echo "No playground containers are currently running."
        exit 0
    fi

    if [ ${#running[@]} -eq 1 ]; then
        container="${running[0]}"
    else
        echo "Multiple playground containers are running:"
        select container in "${running[@]}"; do
            [ -n "$container" ] && break
        done
    fi

    version="${container#oxid7-}"
    echo "Stopping oxid7-${version} ..."
    docker rm -f "oxid7-${version}" "oxid7-${version}-db" 2>/dev/null || true
    docker network rm "oxid7-${version}-net" 2>/dev/null || true
    echo "Done."
    exit 0
fi

echo "Available OXID 7 versions:"
printf " - %s\n" "${versions[@]}"
echo ""

while true; do
    read -p "Enter the OXID 7 version you want to use: " version
    if containsElement "$version" "${versions[@]}"; then
        break
    fi
    echo "Invalid version. Please choose from: ${versions[*]}"
done
read -p "Force image rebuild? (y/N): " force_rebuild

# Map version to PHP version and Composer project branch
case "$version" in
    7.1) php_version="8.1"; composer_version="dev-b-7.1-ce" ;;
    7.2) php_version="8.2"; composer_version="dev-b-7.2-ce" ;;
    7.3) php_version="8.2"; composer_version="dev-b-7.3-ce" ;;
    7.4) php_version="8.2"; composer_version="dev-b-7.4-ce" ;;
    7.5) php_version="8.3"; composer_version="dev-b-7.5-ce" ;;
esac
image_tag="oxid7-playground:${version}"
container_oxid="oxid7-${version}"
container_db="oxid7-${version}-db"
network="oxid7-${version}-net"

# Check if port 80 is already in use
host_port=80
blocking_container=$(docker ps --format "{{.Names}}\t{{.Ports}}" \
    | { grep "0\.0\.0\.0:${host_port}->" || true; } | awk '{print $1}' | head -1)
if [ -n "$blocking_container" ] || lsof -iTCP:"${host_port}" -sTCP:LISTEN -t > /dev/null 2>&1; then
    echo ""
    if [ -n "$blocking_container" ]; then
        echo "Port ${host_port} is already used by Docker container: ${blocking_container}"
    else
        echo "Port ${host_port} is already in use by another process."
    fi
    echo "  1) Stop ${blocking_container:-the blocking process} and use port ${host_port}"
    echo "  2) Use a different port"
    while true; do
        read -p "Choice (1/2): " port_choice
        [[ "$port_choice" == "1" || "$port_choice" == "2" ]] && break
        echo "Please enter 1 or 2."
    done
    if [[ "$port_choice" == "1" ]]; then
        if [ -n "$blocking_container" ]; then
            echo "Stopping ${blocking_container} ..."
            docker rm -f "$blocking_container"
            blocking_db="${blocking_container}-db"
            if [ "$(docker ps -aq -f name="^${blocking_db}$")" ]; then
                docker rm -f "$blocking_db"
            fi
            blocking_net="${blocking_container}-net"
            if docker network inspect "$blocking_net" > /dev/null 2>&1; then
                docker network rm "$blocking_net"
            fi
        else
            echo "Cannot automatically stop a non-Docker process."
            echo "Please free port ${host_port} manually and restart."
            exit 1
        fi
    else
        while true; do
            read -p "Enter port to use (81-89 or 8080-8089): " host_port
            if [[ "$host_port" =~ ^8[1-9]$ ]] || \
               [[ "$host_port" =~ ^808[0-9]$ ]]; then
                break
            fi
            echo "Invalid port. Please enter a port in range 81-89 or 8080-8089."
        done
    fi
fi

if [ "$host_port" -eq 80 ]; then
    shop_url="http://localhost/"
else
    shop_url="http://localhost:${host_port}/"
fi

echo ""
echo "Preparing OXID ${version} (PHP ${php_version}) ..."

# Remove existing containers if present
for name in "$container_oxid" "$container_db"; do
    if [ "$(docker ps -aq -f name="^${name}$")" ]; then
        echo "Removing existing container: ${name}"
        docker rm -f "$name"
    fi
done

# Remove existing network if present
if docker network inspect "$network" > /dev/null 2>&1; then
    docker network rm "$network"
fi

# Build OXID image (cached by default; rebuild on request)
if [[ "$force_rebuild" =~ ^[Yy]$ ]] && docker image inspect "$image_tag" > /dev/null 2>&1; then
    echo "Removing cached image ${image_tag} for rebuild ..."
    # Force-remove any leftover containers blocking the image removal
    blocking=$(docker ps -aq --filter "ancestor=${image_tag}")
    if [ -n "$blocking" ]; then
        echo "Removing leftover containers using the image ..."
        docker rm -f $blocking
    fi
    docker rmi "$image_tag"
fi

if docker image inspect "$image_tag" > /dev/null 2>&1; then
    echo "Using cached image ${image_tag}."
else
    echo "Building image ${image_tag} — this takes a few minutes on first run ..."
    if ! docker build \
        --build-arg PHP_VERSION="$php_version" \
        --build-arg OXID_COMPOSER_VERSION="$composer_version" \
        -t "$image_tag" \
        -f docker/Dockerfile \
        .; then
        echo "Image build failed. See errors above."
        exit 1
    fi
fi

# Create isolated network
docker network create "$network"

# Start MySQL
echo "Starting MySQL ..."
docker run -d \
    --name "$container_db" \
    --network "$network" \
    --platform linux/x86_64 \
    -e MYSQL_ROOT_PASSWORD=root \
    -e MYSQL_DATABASE=db \
    -e MYSQL_USER=user \
    -e MYSQL_PASSWORD=pwd \
    mysql:8.0 \
    --character-set-server=utf8mb4 \
    --collation-server=utf8mb4_unicode_ci \
    --default-authentication-plugin=mysql_native_password \
    --skip-ssl

# Wait for MySQL to accept connections
echo "Waiting for MySQL ..."
until docker exec "$container_db" mysqladmin ping -uroot -proot --silent 2>/dev/null; do
    echo -n "."
    sleep 2
done
echo ""

# Start OXID shop
echo "Starting OXID shop ..."
docker_options=(
    -d
    --name "$container_oxid"
    --network "$network"
    -e MYSQL_HOST="$container_db"
    -e MYSQL_DATABASE=db
    -e MYSQL_USER=user
    -e MYSQL_PASSWORD=pwd
    -e SHOP_URL="${shop_url}"
    -v "$(pwd):/var/www/html/source/modules/endereco/endereco-oxid7-twig-client"
    -p "${host_port}:80"
)

if ! docker run "${docker_options[@]}" "$image_tag"; then
    echo "Failed to start OXID container. See errors above."
    exit 1
fi

# Wait for Apache to respond (any HTTP response is fine — 500/503 is normal on first
# run while the entrypoint is still running oe:database:reset and activating the module).
echo "Waiting for Apache to be ready ..."
max_wait=300
wait_count=0
until curl -s --max-time 2 -o /dev/null "${shop_url}"; do
    if [ $wait_count -ge $max_wait ]; then
        echo ""
        echo "Apache did not respond in time. Check logs with: docker logs ${container_oxid}"
        exit 1
    fi
    echo -n "."
    sleep 2
    (( wait_count += 2 ))
done
echo ""

# Stream setup progress until complete so the final message means the shop is truly ready.
echo ""
echo "Apache is up. Waiting for shop setup to complete ..."
docker logs -f "$container_oxid" 2>&1 | while IFS= read -r line; do
    [[ "$line" == *"[setup]"* ]] && echo "  $line"
    [[ "$line" == *"Setup complete"* ]] && break
done || true

echo ""
echo "OXID 7 (${version}) is ready."
echo ""
echo "  Shop:      ${shop_url}"
echo "  Admin:     ${shop_url}admin/"
echo "  AdminNeo:  ${shop_url}adminneo/?mysql=${container_db}&username=user&db=db  (pwd: pwd)"
echo ""
echo "Default admin credentials:"
echo "  E-Mail:   admin@example.com"
echo "  Passwort: admin"
echo ""
echo "To stop:   ./playground.sh stop"
