#!/bin/bash

# Zabbix-add - PHP Syntax Checker
# Script para verificar sintaxis PHP localmente antes de push

echo "🔍 Checking PHP syntax in project..."
SYNTAX_ERRORS=0
TOTAL_FILES=0

# Check all PHP files in app directory
while IFS= read -r -d '' file; do
    TOTAL_FILES=$((TOTAL_FILES + 1))
    echo "Checking: $file"

    if ! php -l "$file" > /dev/null 2>&1; then
        echo "❌ Syntax error found in: $file"
        php -l "$file"
        SYNTAX_ERRORS=1
    fi
done < <(find app -name "*.php" -print0)

# Also check other PHP files
for dir in config database/migrations routes tests; do
    if [ -d "$dir" ]; then
        while IFS= read -r -d '' file; do
            TOTAL_FILES=$((TOTAL_FILES + 1))
            echo "Checking: $file"

            if ! php -l "$file" > /dev/null 2>&1; then
                echo "❌ Syntax error found in: $file"
                php -l "$file"
                SYNTAX_ERRORS=1
            fi
        done < <(find "$dir" -name "*.php" -print0)
    fi
done

echo ""
echo "📊 Summary:"
echo "   Total files checked: $TOTAL_FILES"

if [ $SYNTAX_ERRORS -eq 0 ]; then
    echo "   ✅ No syntax errors found!"
    echo "   🚀 Ready to push!"
    exit 0
else
    echo "   ❌ Syntax errors detected!"
    echo "   🛠️  Please fix errors before committing"
    exit 1
fi
