#!/usr/bin/env bash

# ==============================================================================
# NCCIA API Automated Test Suite (cURL & Cookie-Auth)
# Usage: bash test_apis.sh [BASE_URL] [EMAIL] [PASSWORD]
# Example: bash test_apis.sh https://nccia.real-erp.net admin@admin.com password
# ==============================================================================

BASE_URL="${1:-https://nccia.real-erp.net}"
EMAIL="${2:-admin@admin.com}"
PASSWORD="${3:-password}"
COOKIE_JAR=$(mktemp /tmp/nccia_cookies.XXXXXX)

GREEN="\033[0;32m"
RED="\033[0;31m"
YELLOW="\033[1;33m"
CYAN="\033[0;36m"
NC="\033[0m"

echo -e "${CYAN}===============================================================${NC}"
echo -e "${CYAN}             NCCIA API TEST RUNNER                             ${NC}"
echo -e "${CYAN}===============================================================${NC}"
echo -e "Target:   ${YELLOW}${BASE_URL}${NC}"
echo -e "Account:  ${YELLOW}${EMAIL}${NC}"
echo ""

# Step 1: Login to get session cookies & CSRF
echo -e "Authenticating against ${BASE_URL}/api/login..."

LOGIN_RESP=$(curl -s -c "${COOKIE_JAR}" -b "${COOKIE_JAR}" \
  -X POST "${BASE_URL}/api/login" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "X-Requested-With: XMLHttpRequest" \
  -d "{\"email\":\"${EMAIL}\",\"password\":\"${PASSWORD}\"}")

if grep -q "user" <<< "${LOGIN_RESP}"; then
  echo -e "${GREEN}✔ Login successful! Session established.${NC}\n"
else
  echo -e "${YELLOW}⚠ Login did not return user payload (Response: ${LOGIN_RESP:0:80}...)${NC}"
  echo -e "${YELLOW}  Proceeding to test endpoints (unauthenticated response verification)...${NC}\n"
fi

printf "%-35s %-8s %-12s %-10s\n" "ENDPOINT" "METHOD" "STATUS" "RESULT"
echo "----------------------------------------------------------------------"

PASSED=0
FAILED=0

test_route() {
  local METHOD="$1"
  local URI="$2"
  local EXPECT="$3"

  local START=$(date +%s%N 2>/dev/null || date +%s)
  local CODE=$(curl -s -o /dev/null -w "%{http_code}" \
    -b "${COOKIE_JAR}" -c "${COOKIE_JAR}" \
    -X "${METHOD}" "${BASE_URL}${URI}" \
    -H "Accept: application/json" \
    -H "X-Requested-With: XMLHttpRequest")
  
  if [ "$CODE" == "$EXPECT" ]; then
    printf "%-35s %-8s ${GREEN}%-12s${NC} ${GREEN}✔ PASS${NC}\n" "${URI:0:34}" "${METHOD}" "${CODE}"
    ((PASSED++))
  else
    printf "%-35s %-8s ${RED}%-12s${NC} ${RED}✖ FAIL (exp ${EXPECT})${NC}\n" "${URI:0:34}" "${METHOD}" "${CODE}"
    ((FAILED++))
  fi
}

# --- System & Alerts ---
test_route "GET" "/api/security-alerts" "200"
test_route "GET" "/api/dashboard" "200"
test_route "GET" "/api/analytics" "200"
test_route "GET" "/api/sidebar-counts" "200"
test_route "GET" "/api/department-progress" "200"
test_route "GET" "/api/search?q=test" "200"

# --- Lookups ---
test_route "GET" "/api/lookup/professions" "200"
test_route "GET" "/api/lookup/received-via" "200"
test_route "GET" "/api/lookup/received-from" "200"
test_route "GET" "/api/lookup/cmu-options" "200"
test_route "GET" "/api/lookup/offence-types" "200"
test_route "GET" "/api/lookup/roles" "200"
test_route "GET" "/api/lookup/circles" "200"
test_route "GET" "/api/lookup/zones" "200"
test_route "GET" "/api/lookup/enquiry-officers" "200"
test_route "GET" "/api/lookup/legal-officers" "200"
test_route "GET" "/api/lookup/verification-officers" "200"
test_route "GET" "/api/lookup/investigation-officers" "200"
test_route "GET" "/api/lookup/circle-incharges" "200"

# --- Complaints & Imports ---
test_route "GET" "/api/complaints" "200"
test_route "GET" "/api/complaints/search?query=test" "200"
test_route "GET" "/api/complaint-pdf-imports" "200"
test_route "GET" "/api/complaint-pdf-imports/stats" "200"
test_route "GET" "/api/complaint-pdf-imports/capabilities" "200"

# --- Verifications ---
test_route "GET" "/api/verifications" "200"
test_route "GET" "/api/verifications/stats" "200"
test_route "GET" "/api/verifications/reports-list" "200"

# --- Enquiries ---
test_route "GET" "/api/enquiries" "200"
test_route "GET" "/api/enquiries/stats" "200"

# --- Cases & Court ---
test_route "GET" "/api/cases" "200"
test_route "GET" "/api/court-cases" "200"

# --- Reports ---
test_route "GET" "/api/dsr-reports" "200"
test_route "GET" "/api/do-letters" "200"

# --- Admin & Master Data ---
test_route "GET" "/api/users" "200"
test_route "GET" "/api/circles" "200"
test_route "GET" "/api/offence-types" "200"
test_route "GET" "/api/investigation-officers" "200"
test_route "GET" "/api/login-history" "200"
test_route "GET" "/api/login-history/stats" "200"

# --- Reference Data ---
test_route "GET" "/api/laws" "200"
test_route "GET" "/api/rules" "200"
test_route "GET" "/api/sops" "200"
test_route "GET" "/api/user-manuals" "200"

# --- Forensic ---
test_route "GET" "/api/forensic/stats" "200"
test_route "GET" "/api/forensic/request-stats" "200"
test_route "GET" "/api/forensic/users" "200"

# --- Security Negative Probe ---
test_route "GET" "/api/v1/users" "404"

rm -f "${COOKIE_JAR}"

echo "----------------------------------------------------------------------"
echo -e "Summary: Total: $((PASSED + FAILED)) | ${GREEN}Passed: ${PASSED}${NC} | ${RED}Failed: ${FAILED}${NC}"
echo -e "${CYAN}===============================================================${NC}"
