#!/bin/bash

# Script para configurar Supervisor en macOS para Baytamin
# Utiliza Laravel Herd para PHP

# Colores para output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Verifica si Supervisor está instalado
if ! command -v supervisorctl &> /dev/null; then
    echo -e "${YELLOW}Supervisor no está instalado. Intentando instalar con Homebrew...${NC}"
    brew install supervisor

    if [ $? -ne 0 ]; then
        echo -e "${RED}Error al instalar Supervisor. Por favor, instálalo manualmente.${NC}"
        exit 1
    fi
fi

# Verifica si Laravel Herd está instalado
PHP_PATH="/Users/$(whoami)/Library/Application Support/Herd/bin/php"
if [ ! -f "$PHP_PATH" ]; then
    echo -e "${RED}No se encontró Laravel Herd en la ruta esperada.${NC}"
    echo -e "${YELLOW}Por favor, instala Laravel Herd o ajusta la ruta de PHP en este script.${NC}"
    exit 1
fi

# Obtiene la ruta base del proyecto
BASE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
echo -e "${GREEN}Directorio base del proyecto: $BASE_DIR${NC}"

# Crea el directorio para la configuración de Supervisor
sudo mkdir -p /usr/local/etc/supervisor.d
echo -e "${GREEN}Directorio de configuración de Supervisor creado.${NC}"

# Verifica si el archivo supervisord.ini incluye el directorio supervisor.d
SUPERVISOR_INI="/usr/local/etc/supervisord.ini"
if [ ! -f "$SUPERVISOR_INI" ]; then
    echo -e "${RED}No se encontró el archivo $SUPERVISOR_INI${NC}"
    exit 1
fi

# Agrega la sección include si no existe
if ! grep -q "^\[include\]" "$SUPERVISOR_INI"; then
    echo -e "${YELLOW}Añadiendo sección include al archivo supervisord.ini${NC}"
    echo -e "\n[include]\nfiles = /usr/local/etc/supervisor.d/*.ini" | sudo tee -a "$SUPERVISOR_INI" > /dev/null
else
    # Verifica si el directorio ya está incluido
    if ! grep -q "files = /usr/local/etc/supervisor.d/\*\.ini" "$SUPERVISOR_INI"; then
        echo -e "${YELLOW}Añadiendo directorio supervisor.d a la sección include${NC}"
        sudo sed -i '' '/^\[include\]/a\
files = /usr/local/etc/supervisor.d/*.ini
' "$SUPERVISOR_INI"
    else
        echo -e "${GREEN}Directorio supervisor.d ya está incluido en supervisord.ini${NC}"
    fi
fi

# Crea el archivo de configuración para Baytamin
CONFIG_FILE="/usr/local/etc/supervisor.d/baytamin.ini"
cat << EOF > /tmp/baytamin_supervisor.ini
[program:baytamin-local-normal]
process_name=%(program_name)s_%(process_num)02d
command="$PHP_PATH" $BASE_DIR/artisan queue:work rabbitmq --queue=eu.normal --sleep=3 --tries=3 --backoff=60
directory=$BASE_DIR
autostart=true
autorestart=true
user=$(whoami)
numprocs=2
redirect_stderr=true
stdout_logfile=$BASE_DIR/storage/logs/supervisor-normal.log
stopwaitsecs=3600

[program:baytamin-local-priority]
process_name=%(program_name)s_%(process_num)02d
command="$PHP_PATH" $BASE_DIR/artisan queue:work rabbitmq --queue=eu.priority --sleep=3 --tries=3 --backoff=30
directory=$BASE_DIR
autostart=true
autorestart=true
user=$(whoami)
numprocs=1
redirect_stderr=true
stdout_logfile=$BASE_DIR/storage/logs/supervisor-priority.log
stopwaitsecs=3600

[program:baytamin-local-slow]
process_name=%(program_name)s_%(process_num)02d
command="$PHP_PATH" $BASE_DIR/artisan queue:work rabbitmq --queue=eu.slow --sleep=3 --tries=3 --backoff=300
directory=$BASE_DIR
autostart=true
autorestart=true
user=$(whoami)
numprocs=1
redirect_stderr=true
stdout_logfile=$BASE_DIR/storage/logs/supervisor-slow.log
stopwaitsecs=3600

[program:baytamin-local-cross]
process_name=%(program_name)s_%(process_num)02d
command="$PHP_PATH" $BASE_DIR/artisan queue:work rabbitmq --queue=eu.cross --sleep=3 --tries=3 --backoff=60
directory=$BASE_DIR
autostart=true
autorestart=true
user=$(whoami)
numprocs=1
redirect_stderr=true
stdout_logfile=$BASE_DIR/storage/logs/supervisor-cross.log
stopwaitsecs=3600

[group:baytamin]
programs=baytamin-local-normal,baytamin-local-priority,baytamin-local-slow,baytamin-local-cross
EOF

sudo cp /tmp/baytamin_supervisor.ini "$CONFIG_FILE"
rm /tmp/baytamin_supervisor.ini
echo -e "${GREEN}Archivo de configuración creado: $CONFIG_FILE${NC}"

# Crear directorio para logs
mkdir -p $BASE_DIR/storage/logs
echo -e "${GREEN}Directorio para logs creado.${NC}"

# Configurar logrotate
if command -v logrotate &> /dev/null; then
    LOGROTATE_CONFIG="/usr/local/etc/logrotate.d/baytamin"
    cat << EOF > /tmp/baytamin_logrotate
$BASE_DIR/storage/logs/supervisor-*.log {
    daily
    missingok
    rotate 7
    compress
    delaycompress
    notifempty
    create 0640 $(whoami) staff
}
EOF
    sudo cp /tmp/baytamin_logrotate "$LOGROTATE_CONFIG"
    rm /tmp/baytamin_logrotate
    echo -e "${GREEN}Configuración de logrotate creada: $LOGROTATE_CONFIG${NC}"
else
    echo -e "${YELLOW}logrotate no está instalado. Se recomienda instalarlo para la rotación de logs.${NC}"
fi

# Recargar Supervisor
echo -e "${YELLOW}Recargando configuración de Supervisor...${NC}"
supervisorctl reread
supervisorctl update

echo -e "${GREEN}Configuración de Supervisor para Baytamin completada.${NC}"
echo -e "${YELLOW}Para ver el estado de los workers ejecuta: supervisorctl status${NC}"
supervisorctl status

echo -e "\n${GREEN}Si los procesos aparecen como BACKOFF, verifica los logs:${NC}"
echo -e "${YELLOW}cat /usr/local/var/log/supervisord.log${NC}"
echo -e "${YELLOW}cat $BASE_DIR/storage/logs/supervisor-*.log${NC}"

exit 0