#!/usr/bin/env bash
set -euo pipefail

# linear_sync.sh - Create Linear issues quickly via API (SAFE MODE: dry-run by default)
# Requirements: curl, jq, macOS Keychain (optional)
# Usage examples:
#   export LINEAR_API_KEY="$(security find-generic-password -a "$USER" -s LINEAR_API_KEY -w)"
#   bash scripts/linear_sync.sh --team-id TEAM --project-name "Frontend Admin" \
#        --status "Next" --priority High --title "[Frontend] Layout preproducción" --description-file docs/ARCHITECTURE.md
#   bash scripts/linear_sync.sh --team-id TEAM --project-name "Frontend Admin" --status Backlog --priority Medium --from-md todo.md --section "### Dashboard Admin (NODE_ROLE=admin)"
#     (crea issues por cada linea markdown con "- [ ] " en la sección indicada)
#   Modo mapeo (todo automático):
#   bash scripts/linear_sync.sh --map scripts/linear_map.json --file todo.md
#     (lee teamId y mappings del JSON y crea issues por sección → proyecto/estado/prioridad)

API_URL="https://api.linear.app/graphql"
API_KEY="${LINEAR_API_KEY:-}"

if [[ -z "${API_KEY}" ]]; then
  # Try Keychain (service: LINEAR_API_KEY)
  if command -v security >/dev/null 2>&1; then
    API_KEY="$(security find-generic-password -a "$USER" -s LINEAR_API_KEY -w 2>/dev/null || true)"
  fi
fi

if [[ -z "${API_KEY}" ]]; then
  echo "ERROR: Set LINEAR_API_KEY env var or store it in Keychain (service: LINEAR_API_KEY)." >&2
  exit 1
fi

TEAM_ID=""
PROJECT_ID=""
PROJECT_NAME=""
STATUS_NAME="Backlog"
PRIORITY_NAME="No priority"
TITLE=""
DESCRIPTION=""
DESCRIPTION_FILE=""
FROM_MD=""
SECTION_PATTERN=""
MAP_FILE=""
DRY_RUN="true"
MD_FILE=""
# New options for stateful syncing and hierarchy
STATE_FILE="scripts/linear_state.json"
UPDATE_EXISTING="false"
USE_PARENT="false"
CHECKED_STATE_NAME="Done"
CANCELED_STATE_NAME="Canceled"
SEED_STATE="false"

while [[ $# -gt 0 ]]; do
  case "$1" in
    --team-id) TEAM_ID="$2"; shift 2 ;;
    --project-id) PROJECT_ID="$2"; shift 2 ;;
    --project-name) PROJECT_NAME="$2"; shift 2 ;;
    --status) STATUS_NAME="$2"; shift 2 ;;
    --priority) PRIORITY_NAME="$2"; shift 2 ;;
    --title) TITLE="$2"; shift 2 ;;
    --description) DESCRIPTION="$2"; shift 2 ;;
    --description-file) DESCRIPTION_FILE="$2"; shift 2 ;;
    --from-md) FROM_MD="$2"; shift 2 ;;
    --section) SECTION_PATTERN="$2"; shift 2 ;;
    --map) MAP_FILE="$2"; shift 2 ;;
    --apply) DRY_RUN="false"; shift 1 ;;
    --file) MD_FILE="$2"; shift 2 ;;
    --state-file) STATE_FILE="$2"; shift 2 ;;
    --update-existing) UPDATE_EXISTING="true"; shift 1 ;;
    --use-parent) USE_PARENT="true"; shift 1 ;;
    --seed-state) SEED_STATE="true"; shift 1 ;;
    --checked-state) CHECKED_STATE_NAME="$2"; shift 2 ;;
    --canceled-state) CANCELED_STATE_NAME="$2"; shift 2 ;;
    -h|--help)
      sed -n '1,80p' "$0"; exit 0 ;;
    *) echo "Unknown arg: $1" >&2; exit 1 ;;
  esac
done

api() {
  local query="$1"; shift
  local vars="$1"; shift || true
  curl -sS "$API_URL" \
    -H 'Content-Type: application/json' \
    -H "Authorization: $API_KEY" \
    -d "{\"query\":$query,\"variables\":${vars:-null}}"
}

get_status_id() {
  local name="$1"
  local q='"query($teamId: ID!){ workflowStates(filter:{team:{id:{eq:$teamId}}}){ nodes{ id name type } } }"'
  local v="{\"teamId\":\"$TEAM_ID\"}"
  api "$q" "$v" | jq -r --arg n "$name" '.data.workflowStates.nodes[] | select(.name==$n) | .id' | head -n1
}

get_project_id_by_name() {
  local name="$1"
  local q='"query($teamId:String!){ team(id:$teamId){ projects(first:100){ nodes{ id name state } } } }"'
  local v="{\"teamId\":\"$TEAM_ID\"}"
  api "$q" "$v" | jq -r --arg n "$name" '.data.team.projects.nodes[] | select(.name==$n) | .id' | head -n1
}

# Fetch issues by team and optional project, returning title→{id, url}
fetch_issues_map() {
  local pId="$1"; shift || true
  local after=""
  local hasNext="true"
  local acc='{}'
  while [[ "$hasNext" == "true" ]]; do
    local q vars resp chunk endCursor
    if [[ -n "$pId" ]]; then
      q='"query($teamId:String!, $projectId:String!, $after:String){\n        issues(first:50, filter:{team:{id:{eq:$teamId}}, project:{id:{eq:$projectId}}}, after:$after){\n          nodes{ id title identifier url }\n          pageInfo{ hasNextPage endCursor }\n        }\n      }"'
      if [[ -n "$after" ]]; then
        vars="$(jq -n --arg teamId "$TEAM_ID" --arg projectId "$pId" --arg after "$after" '{teamId:$teamId, projectId:$projectId, after:$after}')"
      else
        vars="$(jq -n --arg teamId "$TEAM_ID" --arg projectId "$pId" '{teamId:$teamId, projectId:$projectId}')"
      fi
    else
      q='"query($teamId:String!, $after:String){\n        issues(first:50, filter:{team:{id:{eq:$teamId}}}, after:$after){\n          nodes{ id title identifier url }\n          pageInfo{ hasNextPage endCursor }\n        }\n      }"'
      if [[ -n "$after" ]]; then
        vars="$(jq -n --arg teamId "$TEAM_ID" --arg after "$after" '{teamId:$teamId, after:$after}')"
      else
        vars="$(jq -n --arg teamId "$TEAM_ID" '{teamId:$teamId}')"
      fi
    fi
    resp="$(api "$q" "$vars")"
    chunk="$(echo "$resp" | jq -c '(.data.issues.nodes // []) | map({( .title ): {id: .id, url: .url}}) | add // {}' 2>/dev/null || echo '{}')"
    acc="$(jq -c --argjson a "$acc" --argjson b "$chunk" -n '$a + $b' 2>/dev/null || echo "$acc")"
    hasNext="$(echo "$resp" | jq -r '.data.issues.pageInfo.hasNextPage // false' 2>/dev/null || echo false)"
    endCursor="$(echo "$resp" | jq -r '.data.issues.pageInfo.endCursor // ""' 2>/dev/null || echo '')"
    after="$endCursor"
    if [[ "$hasNext" != "true" ]]; then break; fi
  done
  echo "$acc"
}

# Very simple rename detector using Jaccard token similarity
similar_enough() {
  local a="$1"; shift
  local b="$2"; shift
  # tokenize by non-alphanum
  local ta tb
  ta="$(echo "$a" | tr '[:upper:]' '[:lower:]' | tr -cs 'a-z0-9' '\n' | sort -u)"
  tb="$(echo "$b" | tr '[:upper:]' '[:lower:]' | tr -cs 'a-z0-9' '\n' | sort -u)"
  local inter union
  inter="$(comm -12 <(echo "$ta") <(echo "$tb") | wc -l | tr -d ' ')"
  union="$(comm -3 <(echo "$ta") <(echo "$tb") | wc -l | tr -d ' ')"
  local denom=$((inter + union))
  if [[ "$denom" -eq 0 ]]; then echo 0; return; fi
  # similarity = inter / (inter + diff)
  echo "scale=2; $inter / $denom" | bc -l
}

# --- State management (JSON mapping of tasks to Linear IDs) ---
ensure_state_file() {
  if [[ ! -f "$STATE_FILE" ]]; then
    echo '{"sections":{}}' > "$STATE_FILE"
  fi
}

get_section_key() {
  # Normalize section name into a key
  jq -rn --arg s "$1" '$s'
}

get_parent_id_for_section() {
  local section="$1"
  ensure_state_file
  jq -r --arg sec "$section" '.sections[$sec].parentId // ""' "$STATE_FILE"
}

set_parent_id_for_section() {
  local section="$1"; shift
  local parentId="$1"; shift
  ensure_state_file
  tmp="$(mktemp)"
  jq --arg sec "$section" --arg pid "$parentId" '.sections[$sec].parentId=$pid | .sections[$sec].items //= {}' "$STATE_FILE" > "$tmp" && mv "$tmp" "$STATE_FILE"
}

get_issue_id_for_item() {
  local section="$1"; shift
  local title="$1"; shift
  ensure_state_file
  jq -r --arg sec "$section" --arg t "$title" '.sections[$sec].items[$t] // ""' "$STATE_FILE"
}

set_issue_id_for_item() {
  local section="$1"; shift
  local title="$1"; shift
  local issueId="$1"; shift
  ensure_state_file
  tmp="$(mktemp)"
  jq --arg sec "$section" --arg t "$title" --arg iid "$issueId" '.sections[$sec].items[$t]=$iid' "$STATE_FILE" > "$tmp" && mv "$tmp" "$STATE_FILE"
}

priority_to_int() {
  case "$1" in
    Urgent|urgent|P0) echo 4 ;;
    High|high|P1) echo 3 ;;
    Medium|medium|P2) echo 2 ;;
    Low|low|P3) echo 1 ;;
    "No priority"|none|P4) echo 0 ;;
    *) echo 0 ;;
  esac
}

create_issue() {
  local title="$1"; shift
  local desc="$1"; shift
  local parent_id="${1:-}" || true
  local status_id
  status_id="$(get_status_id "$STATUS_NAME")"
  if [[ -z "$status_id" ]]; then
    echo "ERROR: Status '$STATUS_NAME' not found for team $TEAM_ID" >&2
    exit 1
  fi
  local pri
  pri="$(priority_to_int "$PRIORITY_NAME")"

  if [[ -z "$PROJECT_ID" && -n "$PROJECT_NAME" ]]; then
    PROJECT_ID="$(get_project_id_by_name "$PROJECT_NAME")"
  fi

  local input
  if [[ -n "$PROJECT_ID" ]]; then
    if [[ -n "${parent_id}" ]]; then
      input=$(jq -n --arg teamId "$TEAM_ID" --arg statusId "$status_id" --arg title "$title" --arg desc "$desc" --argjson priority "$pri" --arg projectId "$PROJECT_ID" --arg parentId "${parent_id}" '{teamId:$teamId, title:$title, description:$desc, priority:$priority, stateId:$statusId, projectId:$projectId, parentId:$parentId}')
    else
      input=$(jq -n --arg teamId "$TEAM_ID" --arg statusId "$status_id" --arg title "$title" --arg desc "$desc" --argjson priority "$pri" --arg projectId "$PROJECT_ID" '{teamId:$teamId, title:$title, description:$desc, priority:$priority, stateId:$statusId, projectId:$projectId}')
    fi
  else
    if [[ -n "${parent_id}" ]]; then
      input=$(jq -n --arg teamId "$TEAM_ID" --arg statusId "$status_id" --arg title "$title" --arg desc "$desc" --argjson priority "$pri" --arg parentId "${parent_id}" '{teamId:$teamId, title:$title, description:$desc, priority:$priority, stateId:$statusId, parentId:$parentId}')
    else
      input=$(jq -n --arg teamId "$TEAM_ID" --arg statusId "$status_id" --arg title "$title" --arg desc "$desc" --argjson priority "$pri" '{teamId:$teamId, title:$title, description:$desc, priority:$priority, stateId:$statusId}')
    fi
  fi

  local q='"mutation($input: IssueCreateInput!){ issueCreate(input:$input){ issue { id identifier url } } }"'
  local v="{\"input\":$input}"
  resp=$(api "$q" "$v"); echo "$resp" > /tmp/linear_last_response.json; echo "$resp" | jq -r '.data.issueCreate.issue | "Created: \(.identifier) \(.url)" // empty' ; echo "$resp" | jq -r '.errors // [] | .[] | ("ERROR: " + (.message // "unknown"))' >&2
}

if [[ -n "$MAP_FILE" ]]; then
  if [[ "$DRY_RUN" == "true" ]]; then
    echo "DRY-RUN: No issues will be created. Use --apply to create." >&2
  fi
  if [[ ! -f "$MAP_FILE" ]]; then echo "ERROR: Map file not found: $MAP_FILE" >&2; exit 1; fi
  if [[ -z "$MD_FILE" ]]; then echo "ERROR: --file is required with --map" >&2; exit 1; fi
  jq -c '.mappings[]' "$MAP_FILE" | while IFS= read -r row; do
    TEAM_ID="$(jq -r '.teamId // empty' <<<"$row")"
    if [[ -z "$TEAM_ID" ]]; then TEAM_ID="$(jq -r '.teamId' "$MAP_FILE")"; fi
    PROJECT_NAME="$(jq -r '.projectName' <<<"$row")"
    STATUS_NAME="$(jq -r '.status' <<<"$row")"
    PRIORITY_NAME="$(jq -r '.priority' <<<"$row")"
    SECTION_PATTERN="$(jq -r '.section' <<<"$row")"
    USE_PARENT="$(jq -r '.useParent // false' <<<"$row")"
    PARENT_TITLE="$(jq -r '.parentTitle // empty' <<<"$row")"

    # Parent management
    parent_id=""
    if [[ "$USE_PARENT" == "true" ]]; then
      # Determine parent title
      if [[ -z "$PARENT_TITLE" || "$PARENT_TITLE" == "null" ]]; then
        PARENT_TITLE="$SECTION_PATTERN"
      fi
      # Check state file
      parent_id="$(get_parent_id_for_section "$SECTION_PATTERN")"
      if [[ -z "$parent_id" ]]; then
        # Create parent issue once
        if [[ "$DRY_RUN" == "true" ]]; then
          echo "DRY-RUN parent: $PARENT_TITLE ($SECTION_PATTERN)"; parent_id="DRY_RUN";
        else
        FROM_MD="" SECTION_PATTERN="" PROJECT_ID="" PROJECT_NAME="$PROJECT_NAME" STATUS_NAME="$STATUS_NAME" PRIORITY_NAME="$PRIORITY_NAME" \
        TITLE="$PARENT_TITLE" DESCRIPTION="Auto-created parent for section: $SECTION_PATTERN" \
        bash -lc ':
        '
        # Actually create parent
        tmp_resp="$(
          STATUS_NAME="$STATUS_NAME" PRIORITY_NAME="$PRIORITY_NAME" create_issue "$PARENT_TITLE" "Parent for $SECTION_PATTERN"
        )"
        parent_id="$(cat /tmp/linear_last_response.json | jq -r '.data.issueCreate.issue.id // empty')"
        fi
        if [[ -n "$parent_id" && "$parent_id" != "DRY_RUN" ]]; then
          set_parent_id_for_section "$SECTION_PATTERN" "$parent_id"
        fi
      fi
    fi

    # Seed state from Linear if requested (no creations)
    if [[ "$SEED_STATE" == "true" ]]; then
      proj_id=""
      proj_id="$(get_project_id_by_name "$PROJECT_NAME")"
      issues_map="$(fetch_issues_map "$proj_id")"
      # Store all issues whose title matches lines in section
      awk -v pat="$SECTION_PATTERN" '
        index($0, pat) {flag=1; next}
        /^### / && flag {flag=0}
        flag && $0 ~ /^[[:space:]]*- \[ \] / {
          line=$0
          sub(/^[[:space:]]*- \[ \] [[:space:]]*/, "", line)
          print line
        }
      ' "$MD_FILE" | while IFS= read -r line; do
        id="$(echo "$issues_map" | jq -r --arg t "$line" '.[ $t ].id // empty')"
        if [[ -n "$id" ]]; then
          set_issue_id_for_item "$SECTION_PATTERN" "$line" "$id"
          echo "Seeded: $line"
        fi
      done
      continue
    fi

    # Now process items in section with optional parent_id
    awk -v pat="$SECTION_PATTERN" '
      index($0, pat) {flag=1; next}
      /^### / && flag {flag=0}
      flag && $0 ~ /^[[:space:]]*- \[[^]]+\] / {
        line=$0
        is_canceled = match(line, /^\s*- \[((c|C)|x-cancel)\] /)
        is_checked = (!is_canceled && match(line, /^\s*- \[(x|X)\] /))
        sub(/^\s*- \[[^]]+\]\s*/, "", line)
        if (is_canceled) {
          print "CANCELED::" line
        } else if (is_checked) {
          print "CHECKED::" line
        } else {
          print "UNCHECKED::" line
        }
      }
    ' "$MD_FILE" | while IFS= read -r line; do
      [[ -z "$line" ]] && continue
      state_tag="${line%%::*}"
      title_line="${line#*::}"
      existing_id="$(get_issue_id_for_item "$SECTION_PATTERN" "$title_line")"
      if [[ -n "$existing_id" && "$UPDATE_EXISTING" == "true" ]]; then
        # Update existing
        q='"mutation($id: String!, $input: IssueUpdateInput!){ issueUpdate(id:$id, input:$input){ success } }"'
        pri="$(priority_to_int "$PRIORITY_NAME")"
        # If checked/canceled, move to the provided states; else normal STATUS_NAME
        if [[ "$state_tag" == "CANCELED" ]]; then
          sid="$(get_status_id "$CANCELED_STATE_NAME")"
        elif [[ "$state_tag" == "CHECKED" ]]; then
          sid="$(get_status_id "$CHECKED_STATE_NAME")"
        else
          sid="$(get_status_id "$STATUS_NAME")"
        fi
        input=$(jq -n --arg title "$title_line" --argjson priority "$pri" --arg stateId "$sid" '{title:$title, priority:$priority, stateId:$stateId}')
        v="{\"id\":\"$existing_id\",\"input\":$input}"
        resp=$(api "$q" "$v")
        echo "$resp" > /tmp/linear_last_response.json
        if echo "$resp" | jq -e '.data.issueUpdate.success == true' >/dev/null 2>&1; then
          case "$state_tag" in
            CHECKED) echo "Updated(Done): $title_line";;
            CANCELED) echo "Updated(Canceled): $title_line";;
            *) echo "Updated: $title_line";;
          esac
        else
          echo "$resp" | jq -r '.errors // [] | .[] | ("ERROR: " + (.message // "unknown"))' >&2
        fi
      else
        # Try rename heuristic if UPDATE_EXISTING and section has other mapped titles
        if [[ "$UPDATE_EXISTING" == "true" ]]; then
          # Search for closest existing mapped title in this section
          # Load all titles in state for section
          old_titles_str="$(jq -r --arg sec "$SECTION_PATTERN" '.sections[$sec].items | keys[]?' "$STATE_FILE" 2>/dev/null || true)"
          best_sim=0
          best_title=""
          while IFS= read -r ot; do
            [[ -z "$ot" ]] && continue
            sim="$(similar_enough "$ot" "$title_line" 2>/dev/null || echo 0)"
            cmp=$(awk -v a="$sim" -v b="$best_sim" 'BEGIN{ if (a>b) print 1; else print 0 }')
            if [[ "$cmp" -eq 1 ]]; then
              best_sim="$sim"; best_title="$ot"
            fi
          done <<< "$old_titles_str"
          pass=$(awk -v s="$best_sim" 'BEGIN{ if (s+0 >= 0.8) print 1; else print 0 }')
          if [[ "$pass" -eq 1 && -n "$best_title" ]]; then
            cand_id="$(get_issue_id_for_item "$SECTION_PATTERN" "$best_title")"
            if [[ -n "$cand_id" ]]; then
              # rename title on Linear and move mapping key
              q='"mutation($id: String!, $input: IssueUpdateInput!){ issueUpdate(id:$id, input:$input){ success } }"'
              input=$(jq -n --arg title "$title_line" '{title:$title}')
              v="{\"id\":\"$cand_id\",\"input\":$input}"
              resp=$(api "$q" "$v")
              if echo "$resp" | jq -e '.data.issueUpdate.success == true' >/dev/null 2>&1; then
                # move key in state
                tmp=$(mktemp)
                jq --arg sec "$SECTION_PATTERN" --arg old "$best_title" --arg new "$title_line" '
                  .sections[$sec].items[$new]=.sections[$sec].items[$old] | del(.sections[$sec].items[$old])
                ' "$STATE_FILE" > "$tmp" && mv "$tmp" "$STATE_FILE"
                echo "RENAME: $best_title -> $title_line"
                continue
              fi
            fi
          fi
        fi
        # Create and store mapping
        if [[ "$DRY_RUN" == "true" ]]; then
          echo "DRY-RUN create: $title_line (section: $SECTION_PATTERN, project: $PROJECT_NAME)"
        else
          output="$(create_issue "$title_line" "Imported from $MD_FILE :: $SECTION_PATTERN" "$parent_id")"
          new_id="$(cat /tmp/linear_last_response.json | jq -r '.data.issueCreate.issue.id // empty')"
          if [[ -n "$new_id" ]]; then
            set_issue_id_for_item "$SECTION_PATTERN" "$title_line" "$new_id"
          fi
          echo "$output"
        fi
      fi
    done
  done
  exit 0
fi

if [[ -n "$FROM_MD" ]]; then
  if [[ ! -f "$FROM_MD" ]]; then
    echo "ERROR: File not found: $FROM_MD" >&2; exit 1
  fi

  tmpfile="/tmp/linear_titles_$$.txt"
  if [[ -n "$SECTION_PATTERN" ]]; then
    awk -v pat="$SECTION_PATTERN" '
      index($0, pat) {flag=1; next}
      /^### / && flag {flag=0}
      flag && $0 ~ /^[[:space:]]*- \[ \] / {
        line=$0
        sub(/^[[:space:]]*- \[ \] [[:space:]]*/, "", line)
        print line
      }
    ' "$FROM_MD" > "$tmpfile"
    count=$(wc -l < "$tmpfile" | tr -d ' ')
    if [[ "$count" -eq 0 ]]; then
      echo "INFO: No checklist items found in section: $SECTION_PATTERN"
    else
      echo "INFO: Creating $count issues from section: $SECTION_PATTERN"
      while IFS= read -r line; do
        [ -z "$line" ] && continue
        create_issue "$line" "Imported from $FROM_MD :: $SECTION_PATTERN"
      done < "$tmpfile"
    fi
  else
    awk '/^[[:space:]]*- \[ \] /{ line=$0; sub(/^[[:space:]]*- \[ \] [[:space:]]*/, "", line); print line }' "$FROM_MD" > "$tmpfile"
    count=$(wc -l < "$tmpfile" | tr -d ' ')
    if [[ "$count" -eq 0 ]]; then
      echo "INFO: No checklist items found in file: $FROM_MD"
    else
      echo "INFO: Creating $count issues from file: $FROM_MD"
      while IFS= read -r line; do
        [ -z "$line" ] && continue
        create_issue "$line" "Imported from $FROM_MD"
      done < "$tmpfile"
    fi
  fi
  rm -f "$tmpfile"
  exit 0
fi

if [[ -n "$DESCRIPTION_FILE" ]]; then
  if [[ ! -f "$DESCRIPTION_FILE" ]]; then
    echo "ERROR: Description file not found: $DESCRIPTION_FILE" >&2; exit 1
  fi
  DESCRIPTION="$(cat "$DESCRIPTION_FILE")"
fi

if [[ -z "$TITLE" ]]; then
  echo "ERROR: --title is required (or use --from-md)" >&2
  exit 1
fi

create_issue "$TITLE" "$DESCRIPTION"
