#!/usr/bin/env bash
set -euo pipefail

# linear_fetch.sh - Fetch issues from Linear and render a markdown summary
# Requirements: curl, jq, macOS Keychain (optional)
# Usage:
#   export LINEAR_API_KEY="$(security find-generic-password -a "$USER" -s LINEAR_API_KEY -w)"
#   # Modo proyectos/mapeo
#   bash scripts/linear_fetch.sh --map scripts/linear_map.json --output notes/linear_sync.md
#   # Modo issue específico (e.g., BAYT-36)
#   bash scripts/linear_fetch.sh --issue BAYT-36 --output notes/issue_BAYT-36.md
#   (lee teamId y projects del JSON y genera un resumen por proyecto/estado o muestra un issue concreto)

API_URL="https://api.linear.app/graphql"
API_KEY="${LINEAR_API_KEY:-}"

if [[ -z "${API_KEY}" ]]; then
  if command -v security >/dev/null 2>&1; then
    API_KEY="$(security find-generic-password -a "$USER" -s LINEAR_API_KEY -w 2>/dev/null || true)"
  fi
fi

if [[ -z "${API_KEY}" ]]; then
  echo "ERROR: LINEAR_API_KEY not set or not found in Keychain (service: LINEAR_API_KEY)." >&2
  exit 1
fi

MAP_FILE=""
OUTPUT="notes/linear_sync.md"
ISSUE_IDENT=""

while [[ $# -gt 0 ]]; do
  case "$1" in
    --map) MAP_FILE="$2"; shift 2 ;;
    --output) OUTPUT="$2"; shift 2 ;;
    --issue) ISSUE_IDENT="$2"; shift 2 ;;
    -h|--help)
      sed -n '1,60p' "$0"; exit 0 ;;
    *) echo "Unknown arg: $1" >&2; exit 1 ;;
  esac
done

if [[ -n "$ISSUE_IDENT" ]]; then
  : # modo issue, continúa abajo
else
  if [[ -z "$MAP_FILE" || ! -f "$MAP_FILE" ]]; then
    echo "ERROR: --map is required and must exist (or use --issue IDENT)" >&2
    exit 1
  fi
  TEAM_ID="$(jq -r '.teamId' "$MAP_FILE")"
  if [[ -z "$TEAM_ID" || "$TEAM_ID" == "null" ]]; then
    echo "ERROR: teamId missing in $MAP_FILE" >&2
    exit 1
  fi
fi

api() {
  local query="$1"; shift
  local vars="$1"; shift || true
  curl -sS "$API_URL" \
    -H 'Content-Type: application/json' \
    -H "Authorization: $API_KEY" \
    -d "{\"query\":$query,\"variables\":${vars:-null}}"
}

get_project_id_by_name() {
  local name="$1"
  local q='"query($teamId:String!){ team(id:$teamId){ projects(first:200){ nodes{ id name state } } } }"'
  local v="{\"teamId\":\"$TEAM_ID\"}"
  api "$q" "$v" | jq -r --arg n "$name" '.data.team.projects.nodes[] | select(.name==$n) | .id' | head -n1
}

fetch_issues_for_project() {
  local project_id="$1"
  local q='"query($projectId:String!){ project(id:$projectId){ name issues(first:200){ nodes{ identifier title url state{ name } priority } } } }"'
  local v="{\"projectId\":\"$project_id\"}"
  api "$q" "$v"
}

# Single issue mode
if [[ -n "$ISSUE_IDENT" ]]; then
  resp_issue="$(api '"query($q:String!){ issues(filter:{query:$q}){ nodes { identifier title url state{ name } priority project{ name } parent{ identifier } } } }"' "{\"q\":\"$ISSUE_IDENT\"}")"
  mkdir -p "$(dirname "$OUTPUT")"
  {
    echo "# Issue $ISSUE_IDENT"
    echo
  } > "$OUTPUT"
  echo "$resp_issue" | jq -r '.data.issues.nodes[0] as $i | select($i!=null) | "- **State**: " + ($i.state.name // "") + "\n- **Priority**: " + (if ($i.priority//0)==4 then "P0" elif ($i.priority//0)==3 then "P1" elif ($i.priority//0)==2 then "P2" elif ($i.priority//0)==1 then "P3" else "  " end) + "\n- **Project**: " + ($i.project.name // "") + "\n- **Parent**: " + ($i.parent.identifier // "") + "\n- **URL**: " + ($i.url // "") + "\n"' >> "$OUTPUT"
  echo "Wrote $OUTPUT"
  exit 0
fi

# Collect unique project names from mappings
readarray -t PROJECTS < <(jq -r '.mappings[].projectName' "$MAP_FILE" | sort -u)

# Start output
mkdir -p "$(dirname "$OUTPUT")"
{
  echo "# Linear Sync Snapshot"
  echo
  date -u "+Generated: %Y-%m-%dT%H:%M:%SZ"
  echo
} > "$OUTPUT"

for pname in "${PROJECTS[@]}"; do
  pid="$(get_project_id_by_name "$pname")"
  if [[ -z "$pid" ]]; then
    echo "- Skipping project '$pname' (not found)" >> "$OUTPUT"
    continue
  fi
  resp="$(fetch_issues_for_project "$pid")"
  echo "## $pname" >> "$OUTPUT"
  echo >> "$OUTPUT"
  # Group by state name
  echo "$resp" | jq -r '.data.project.issues.nodes | group_by(.state.name)[] | "### " + (.[0].state.name // "(no state)") + "\n" + ( map("- [" + (if .priority==4 then "P0" elif .priority==3 then "P1" elif .priority==2 then "P2" elif .priority==1 then "P3" else "  " end) + "] " + .identifier + " - " + .title + " (" + .url + ")") | join("\n")) + "\n"' >> "$OUTPUT"
  echo >> "$OUTPUT"
done

echo "Wrote $OUTPUT"
