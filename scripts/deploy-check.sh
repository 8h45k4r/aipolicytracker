#!/usr/bin/env bash
#
# Which of the features on main is the live site actually serving?
#
# The site does not publish the commit it runs, so this asks it directly: one
# request per shipped feature, each looking for something only that feature
# produces. A row that fails means the deploy that carries it has not happened
# (or the feature broke); the commit column says which commit introduced it.
#
#   scripts/deploy-check.sh https://aipolicytracker.org
#   scripts/deploy-check.sh http://localhost:8000      # the same checks against a local copy of main
#
# Exits 1 when any check fails. Run from the Actions tab as "Deployment check".

set -uo pipefail

BASE="${1:-https://aipolicytracker.org}"
BASE="${BASE%/}"
UA="aipolicytracker-deploy-check"
pass=0; fail=0; rows=()

fetch() { # path -> sets STATUS, LOCATION, HEADERS, BODY
    local tmp; tmp="$(mktemp)"
    HEADERS="$(curl -sS -m 30 -A "$UA" -D - -o "$tmp" "$BASE$1" 2>/dev/null | tr -d '\r')"
    STATUS="$(printf '%s\n' "$HEADERS" | awk 'toupper($1) ~ /^HTTP/ {code=$2} END {print code+0}')"
    LOCATION="$(printf '%s\n' "$HEADERS" | awk 'tolower($1)=="location:" {print $2}' | tail -1)"
    BODY="$(cat "$tmp")"; rm -f "$tmp"
}

# check <feature> <commit> <path> <kind> [<expected>]
#   kind: status=NNN | contains | absent | header | redirect
check() {
    local feature="$1" commit="$2" path="$3" kind="$4" want="${5:-}" ok=0 got=""
    fetch "$path"
    case "$kind" in
        status=*)  [ "$STATUS" = "${kind#status=}" ] && ok=1; got="HTTP $STATUS" ;;
        contains)  [ "$STATUS" = 200 ] && [[ "$BODY" == *"$want"* ]] && ok=1; got="HTTP $STATUS" ;;
        absent)    [ "$STATUS" = 200 ] && [[ "$BODY" != *"$want"* ]] && ok=1; got="HTTP $STATUS" ;;
        header)    printf '%s\n' "$HEADERS" | grep -qi "^$want:" && ok=1; got="HTTP $STATUS" ;;
        redirect)  [ "$STATUS" = 301 ] && [[ "$LOCATION" == *"$want"* ]] && ok=1; got="HTTP $STATUS ${LOCATION:+→ ${LOCATION#"$BASE"}}" ;;
    esac
    if [ $ok = 1 ]; then pass=$((pass + 1)); mark="live"; else fail=$((fail + 1)); mark="NOT LIVE"; fi
    rows+=("| $mark | $feature | \`$commit\` | \`$path\` | $got |")
}

# ---- P1 titles and addresses ------------------------------------------------------
check "P1 · central title pattern"                    "P1"      "/policies/eu-ai-act"                     contains "<title>EU AI Act (2024): Status, Duties"
check "P1 · incident ID URL 301s to its slug"         "P1"      "/ai-risk/incidents/55"                   redirect "/ai-risk/incidents/ai-incident-"
# ---- P2 updates -------------------------------------------------------------------
check "P2 · updates hub"                              "P2"      "/updates"                                contains "AI Policy Updates"
check "P2 · Google News sitemap"                      "P2"      "/sitemap-news.xml"                       contains "<urlset"
check "P2 · per-jurisdiction RSS"                     "P2"      "/updates/eu/feed"                        contains "<rss"
check "P2 · newsletter archive"                       "P2"      "/newsletter"                             status=200
# ---- P3 answer-first pages ----------------------------------------------------------
check "P3 · key-facts table"                          "P3"      "/policies/eu-ai-act"                     contains "data-key-facts"
check "P3 · FAQPage structured data"                  "P3"      "/policies/eu-ai-act"                     contains '"@type":"FAQPage"'
# ---- P4 incident brand safety ------------------------------------------------------
check "P4 · sensitive incident is noindex"            "P4"      "/ai-risk/incidents/ai-incident-sexual-content-amazon-dec-2016" contains 'name="robots" content="noindex'
# ---- P5 templates -------------------------------------------------------------------
check "P5 · templates library"                        "P5"      "/templates"                              contains "/templates/ai-risk-register"
check "P5 · templates API"                            "P5"      "/api/v1/templates"                       contains '"data"'
# ---- P6 hubs ----------------------------------------------------------------------
check "P6 · country hub"                              "P6"      "/ai-regulation-japan"                    status=200
check "P6 · regional hub"                             "P6"      "/ai-regulation-asia"                     status=200
check "P6 · compare pair"                             "P6"      "/compare/eu-vs-japan"                    status=200
# ---- P7 deadline engine ---------------------------------------------------------------
check "P7 · deadline engine"                          "P7"      "/deadlines/which-date-applies"           status=200
check "P7 · deadline calendar export"                 "P7"      "/deadlines/which-date-applies.ics?jurisdictions%5B%5D=eu" contains "BEGIN:VCALENDAR"
# ---- P8 watches and register ----------------------------------------------------------
check "P8 · watches API in OpenAPI"                   "P8"      "/openapi.json"                           contains "bearerAuth"
# ---- P9 economic transition -----------------------------------------------------------
check "P9 · transition tracker"                       "P9"      "/ai-economic-transition"                 status=200
check "P9 · transition API"                           "P9"      "/api/v1/transition/measures"             contains '"data"'
# ---- P10 authority ------------------------------------------------------------------
check "P10 · State of AI Regulation report"           "P10"     "/state-of-ai-regulation"                 status=200
check "P10 · embed script"                            "P10"     "/embed.js"                               status=200
check "P10 · localised hub (Spanish)"                 "P10"     "/es/ai-regulation-japan"                 status=200
check "P10 · llms.txt lists the new surfaces"         "P10"     "/llms.txt"                               contains "/templates"
# ---- after the roadmap ----------------------------------------------------------------
check "Editorial desk on the reviewer roster"         "7f7f933" "/reviewers/ai-policy-tracker"            status=200
check "Reviewer pages no longer 500"                  "8e02fc1" "/reviewers/bhaskar-bhatt"                status=200
check "Request ID on every response"                  "53b5f25" "/up"                                     header "x-request-id"
check "EU AI Act follows the Digital Omnibus dates"   "d84f223" "/policies/eu-ai-act"                     contains "2 December 2027"
check "Colorado SB 26-189 record"                     "d84f223" "/policies/us-colorado-automated-decision-making-technology-act" status=200
check "H.R. 10044 recorded in the transition tracker" "d84f223" "/ai-economic-transition/measures/us-ai-excise-tax-bill" contains "10044"
check "Public-repository cleanup (footer credit)"    "c67f183" "/"                                       absent ">Dignep Group Pvt. Ltd.</a>"

{
    echo "## Deployment check: $BASE"
    echo
    echo "$pass live, $fail not live, checked $(date -u '+%Y-%m-%d %H:%M UTC')."
    echo
    echo "| Result | Feature | Introduced in | Path | Response |"
    echo "|---|---|---|---|---|"
    printf '%s\n' "${rows[@]}"
} | tee -a "${GITHUB_STEP_SUMMARY:-/dev/null}"

[ "$fail" -eq 0 ]
