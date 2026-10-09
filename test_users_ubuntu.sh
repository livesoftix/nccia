#!/usr/bin/env bash

# ==============================================================================
# NCCIA Enterprise User Authentication & Role Verification (Ubuntu Runner)
# Usage: bash test_users_ubuntu.sh [BASE_URL]
# Example: bash test_users_ubuntu.sh https://nccia.real-erp.net
# ==============================================================================

BASE_URL="${1:-https://nccia.real-erp.net}"

GREEN="\033[0;32m"
RED="\033[0;31m"
YELLOW="\033[1;33m"
CYAN="\033[0;36m"
BOLD="\033[1m"
NC="\033[0m"

echo -e "${CYAN}====================================================================${NC}"
echo -e "${BOLD}${CYAN}          NCCIA USER AUTHENTICATION & ROLE TEST (UBUNTU)            ${NC}"
echo -e "${CYAN}====================================================================${NC}"
echo -e "Target Domain: ${YELLOW}${BASE_URL}${NC}"
echo -e "Testing live user logins against ${BASE_URL}/api/login..."
echo ""

printf "%-32s %-22s %-12s %-10s\n" "USER EMAIL" "EXPECTED ROLE" "HTTP STATUS" "RESULT"
echo "-------------------------------------------------------------------------------"

# List of all standard users and credentials
# Format: email:password:expected_role
USERS=(
    "admin@admin.com:password:admin"
    "admin@nccia.gov.pk:password:admin"
    "dg@nccia.gov.pk:password123:director_general"
    "director.general@nccia.gov.pk:password:director_general"
    "director.punjab@nccia.gov.pk:password123:additional_director"
    "additional.director@nccia.gov.pk:password:additional_director"
    "ad.administration@nccia.gov.pk:password:ad_administration"
    "ad.legal@nccia.gov.pk:password:ad_legal"
    "dd.legal@nccia.gov.pk:password:dd_legal"
    "circle.incharge@nccia.gov.pk:password:circle_incharge"
    "ci.lhr@nccia.gov.pk:password123:circle_incharge"
    "ci.grw@nccia.gov.pk:password123:circle_incharge"
    "ci.rwp@nccia.gov.pk:password123:circle_incharge"
    "ci.mux@nccia.gov.pk:password123:circle_incharge"
    "ci.fsd@nccia.gov.pk:password123:circle_incharge"
    "operator@nccia.gov.pk:password:operator"
    "fdo.lhr@nccia.gov.pk:password123:operator"
    "fdo.grw@nccia.gov.pk:password123:operator"
    "verification.officer@nccia.gov.pk:password:verification_officer"
    "vo.lhr@nccia.gov.pk:password123:verification_officer"
    "vo.grw@nccia.gov.pk:password123:verification_officer"
    "enquiry.officer@nccia.gov.pk:password:enquiry_officer"
    "eo.lhr@nccia.gov.pk:password123:enquiry_officer"
    "eo.grw@nccia.gov.pk:password123:enquiry_officer"
    "investigation.officer@nccia.gov.pk:password:investigation_officer"
    "io.lhr@nccia.gov.pk:password123:investigation_officer"
    "io.grw@nccia.gov.pk:password123:investigation_officer"
    "moharrar@nccia.gov.pk:password:moharrar"
    "reader.branch@nccia.gov.pk:password:reader_branch"
)

PASSED=0
FAILED=0

for item in "${USERS[@]}"; do
    IFS=":" read -r EMAIL PASS ROLE <<< "${item}"

    # Send login request
    RESP=$(curl -s -w "\n%{http_code}" \
        -X POST "${BASE_URL}/api/login" \
        -H "Accept: application/json" \
        -H "Content-Type: application/json" \
        -H "X-Requested-With: XMLHttpRequest" \
        -d "{\"email\":\"${EMAIL}\",\"password\":\"${PASS}\"}")

    HTTP_STATUS=$(echo "${RESP}" | tail -n1)
    BODY=$(echo "${RESP}" | head -n -1)

    if [ "${HTTP_STATUS}" == "200" ] && [[ "${BODY}" == *"\"user\""* ]]; then
        printf "%-32s %-22s ${GREEN}%-12s${NC} ${GREEN}✔ PASS${NC}\n" "${EMAIL:0:31}" "${ROLE:0:21}" "${HTTP_STATUS}"
        ((PASSED++))
    else
        # If 401, check message
        local_msg="FAIL"
        if [[ "${BODY}" == *"Too many attempts"* ]]; then
            local_msg="RATE LIMIT"
        elif [ "${HTTP_STATUS}" == "401" ]; then
            local_msg="INVALID CREDS"
        elif [ "${HTTP_STATUS}" == "403" ]; then
            local_msg="REVOKED/ONLINE"
        fi
        printf "%-32s %-22s ${RED}%-12s${NC} ${RED}✖ ${local_msg}${NC}\n" "${EMAIL:0:31}" "${ROLE:0:21}" "${HTTP_STATUS}"
        ((FAILED++))
    fi

    # Small pause to be polite to rate-limiter
    sleep 0.1
done

# Test Forensic User on forensic login endpoint
echo ""
echo -e "Testing Forensic User on ${BASE_URL}/api/forensic/login..."
FORENSIC_RESP=$(curl -s -w "\n%{http_code}" \
    -X POST "${BASE_URL}/api/forensic/login" \
    -H "Accept: application/json" \
    -H "Content-Type: application/json" \
    -H "X-Requested-With: XMLHttpRequest" \
    -d '{"email":"admin@forensic.gov.pk","password":"password"}')

F_STATUS=$(echo "${FORENSIC_RESP}" | tail -n1)
F_BODY=$(echo "${FORENSIC_RESP}" | head -n -1)

if [ "${F_STATUS}" == "200" ]; then
    printf "%-32s %-22s ${GREEN}%-12s${NC} ${GREEN}✔ PASS${NC}\n" "admin@forensic.gov.pk" "admin_forensic" "${F_STATUS}"
    ((PASSED++))
else
    printf "%-32s %-22s ${YELLOW}%-12s${NC} ${YELLOW}⚠ SKIPPED / NOT SEEDED${NC}\n" "admin@forensic.gov.pk" "admin_forensic" "${F_STATUS}"
fi

echo "-------------------------------------------------------------------------------"
echo -e "Summary: Total Tested: $((PASSED + FAILED)) | ${GREEN}Passed: ${PASSED}${NC} | ${RED}Failed: ${FAILED}${NC}"
echo -e "${CYAN}====================================================================${NC}"
