#!/bin/bash
# Closes Chrome and relaunches it with the remote debugging port enabled.
# This allows the stamping script to open new TABS in your existing Chrome
# instead of opening a separate Chromium window.
#
# Run this once at the start of your work session.
# Double-click this file from Finder to execute it.

echo "Cerrando Chrome..."
pkill -x "Google Chrome" 2>/dev/null
sleep 3

echo "Iniciando Chrome con debug port 9222..."
"/Applications/Google Chrome.app/Contents/MacOS/Google Chrome" \
  --remote-debugging-port=9222 \
  --remote-allow-origins=http://localhost:9222 \
  --user-data-dir="$HOME/.invoice-tracker-chrome" \
  --no-first-run \
  --disable-notifications \
  &>/dev/null &

# Wait until the debug port is actually accepting connections
echo "Esperando que Chrome esté listo..."
for i in $(seq 1 15); do
  sleep 1
  if nc -z localhost 9222 2>/dev/null; then
    echo "✓ Chrome listo en puerto 9222. Ahora el sistema abrirá pestañas en tu Chrome."
    echo "  Puedes cerrar esta ventana."
    exit 0
  fi
done
echo "⚠️  Chrome tardó demasiado. Intenta timbrar de todos modos."
