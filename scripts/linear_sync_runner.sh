#!/bin/zsh
set -euo pipefail
export LINEAR_API_KEY="$(security find-generic-password -a "$USER" -s LINEAR_API_KEY -w)"
cd /Users/abkrim/SitesLR12/baytamin
bash scripts/linear_sync.sh --map scripts/linear_map.json --file todo.md
