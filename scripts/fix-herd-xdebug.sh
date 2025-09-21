#!/bin/bash

# Script para configurar Xdebug en Laravel Herd
# Cambia de "always debug" a "debug on demand"

HERD_PHP_INI="/Users/abkrim/Library/Application Support/Herd/config/php/84/php.ini"

echo "🔧 Configurando Xdebug en Laravel Herd..."

# Verificar que el archivo existe
if [ ! -f "$HERD_PHP_INI" ]; then
    echo "❌ No se encontró el archivo de configuración de Herd:"
    echo "   $HERD_PHP_INI"
    echo ""
    echo "💡 Verifica que Laravel Herd esté instalado y la ruta sea correcta"
    exit 1
fi

# Hacer backup
cp "$HERD_PHP_INI" "$HERD_PHP_INI.backup.$(date +%Y%m%d_%H%M%S)"
echo "✅ Backup creado: $HERD_PHP_INI.backup.*"

# Cambiar configuración
sed -i '' 's/xdebug.start_with_request=yes/xdebug.start_with_request=trigger/' "$HERD_PHP_INI"

# Añadir coverage si no está
if ! grep -q "xdebug.mode=debug,develop,coverage" "$HERD_PHP_INI"; then
    sed -i '' 's/xdebug.mode=debug,develop/xdebug.mode=debug,develop,coverage/' "$HERD_PHP_INI"
fi

echo "✅ Configuración cambiada:"
echo "   xdebug.start_with_request=yes → trigger"
echo "   xdebug.mode=debug,develop → debug,develop,coverage"

echo ""
echo "🔄 Para aplicar los cambios, necesitas reiniciar Laravel Herd:"
echo "   1. Abrir la app Laravel Herd"
echo "   2. Quit → Laravel Herd (Cmd+Q)"
echo "   3. Volver a abrir Laravel Herd"
echo ""
echo "⏳ O puedes usar el comando killall:"
echo "   killall Herd && open -a Herd"
echo ""
read -p "⚙️ Reinicia Herd ahora y presiona ENTER para continuar..." -r

echo ""
echo "🧪 Verificando configuración..."
NEW_CONFIG=$(php -i | grep "xdebug.start_with_request")
echo "   $NEW_CONFIG"

if echo "$NEW_CONFIG" | grep -q "trigger"; then
    echo ""
    echo "🎉 ¡ÉXITO! Xdebug configurado correctamente"
    echo ""
    echo "📋 Cómo usar debugging ahora:"
    echo "   • Web: http://localhost:8000/ruta?XDEBUG_SESSION_START=1"
    echo "   • Tests: XDEBUG_SESSION=1 php artisan test"
    echo "   • Coverage: composer test-coverage-report"
    echo ""
    echo "✅ Ya no verás errores molestos de 'Could not connect'"
else
    echo ""
    echo "⚠️  Algo salió mal. Configuración actual:"
    php -i | grep -E "xdebug.(mode|start_with_request)"
    echo ""
    echo "💡 Puede que necesites reiniciar Herd manualmente"
fi