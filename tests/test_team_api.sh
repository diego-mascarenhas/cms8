#!/bin/bash

# Team API Test Script
# Usage: ./test_team_api.sh

# Configuration
BASE_URL="http://humano.test/api"
TOKEN="d8da230c496e26b1dcab3a05b385db2417e32168a2cb1a217dbf0a8e677af382"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to make API request
make_request() {
    local method=$1
    local endpoint=$2
    local data=$3
    
    echo -e "${YELLOW}Testing: $method $endpoint${NC}"
    
    if [ -n "$data" ]; then
        response=$(curl -s -w "\n%{http_code}" -X $method \
            -H "Authorization: Bearer $TOKEN" \
            -H "Content-Type: application/json" \
            -H "Accept: application/json" \
            -d "$data" \
            "$BASE_URL$endpoint")
    else
        response=$(curl -s -w "\n%{http_code}" -X $method \
            -H "Authorization: Bearer $TOKEN" \
            -H "Content-Type: application/json" \
            -H "Accept: application/json" \
            "$BASE_URL$endpoint")
    fi
    
    # Split response and status code
    status_code=$(echo "$response" | tail -n1)
    body=$(echo "$response" | sed '$d')
    
    # Check status code
    if [[ $status_code -ge 200 && $status_code -lt 300 ]]; then
        echo -e "${GREEN}✓ Success ($status_code)${NC}"
        echo "$body" | jq '.' 2>/dev/null || echo "$body"
    else
        echo -e "${RED}✗ Failed ($status_code)${NC}"
        echo "$body" | jq '.' 2>/dev/null || echo "$body"
    fi
    
    echo "----------------------------------------"
}

echo "============================================"
echo "           TEAM API TEST SCRIPT             "
echo "============================================"
echo "Base URL: $BASE_URL"
echo "Token: ${TOKEN:0:20}..."
echo "============================================"

# Test 1: Get team information
make_request "GET" "/team"

# Test 2: Get team settings
make_request "GET" "/team/settings"

# Test 3: Get contacts list
make_request "GET" "/team/contacts"

# Test 4: Create a new contact
make_request "POST" "/team/contacts" '{
    "name": "Test Contact API",
    "email": "test.api@example.com",
    "phone": "+1234567890"
}'

# Test 5: Get projects list
make_request "GET" "/team/projects"

# Test 6: Create a new project
make_request "POST" "/team/projects" '{
    "name": "Test Project API",
    "description": "This is a test project created via API"
}'

# Test 7: Test without token (should fail)
echo -e "${YELLOW}Testing without token (should fail):${NC}"
curl -s -w "\n%{http_code}" -X GET \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    "$BASE_URL/team" | tail -n1

echo -e "${RED}✗ Expected 401 Unauthorized${NC}"
echo "----------------------------------------"

# Test 8: Test with invalid token (should fail)
echo -e "${YELLOW}Testing with invalid token (should fail):${NC}"
curl -s -w "\n%{http_code}" -X GET \
    -H "Authorization: Bearer invalid_token_123" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    "$BASE_URL/team" | tail -n1

echo -e "${RED}✗ Expected 401 Unauthorized${NC}"
echo "----------------------------------------"

echo "============================================"
echo "              TESTS COMPLETED               "
echo "============================================" 