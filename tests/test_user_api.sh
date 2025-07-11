#!/bin/bash

# User API Test Script
# Usage: ./test_user_api.sh

# Configuration
BASE_URL="https://humano.test/api"
EMAIL="admin@example.com"
PASSWORD="Simplicity!"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    local status=$1
    local message=$2
    
    if [ "$status" = "SUCCESS" ]; then
        echo -e "${GREEN}✅ $message${NC}"
    elif [ "$status" = "ERROR" ]; then
        echo -e "${RED}❌ $message${NC}"
    elif [ "$status" = "INFO" ]; then
        echo -e "${BLUE}ℹ️  $message${NC}"
    elif [ "$status" = "WARNING" ]; then
        echo -e "${YELLOW}⚠️  $message${NC}"
    fi
}

# Function to make API request and check response
test_endpoint() {
    local method=$1
    local endpoint=$2
    local data=$3
    local expected_status=$4
    local description=$5
    
    echo -e "\n${YELLOW}Testing: $description${NC}"
    echo "🔗 $method $endpoint"
    
    if [ -n "$data" ]; then
        response=$(curl -s -w "\n%{http_code}" -X $method \
            -H "Authorization: Bearer $USER_TOKEN" \
            -H "Content-Type: application/json" \
            -H "Accept: application/json" \
            -d "$data" \
            "$BASE_URL$endpoint" -k)
    else
        response=$(curl -s -w "\n%{http_code}" -X $method \
            -H "Authorization: Bearer $USER_TOKEN" \
            -H "Content-Type: application/json" \
            -H "Accept: application/json" \
            "$BASE_URL$endpoint" -k)
    fi
    
    # Extract HTTP status code (last line)
    http_code=$(echo "$response" | tail -n1)
    # Extract response body (all but last line)
    response_body=$(echo "$response" | head -n -1)
    
    if [ "$http_code" -eq "$expected_status" ]; then
        print_status "SUCCESS" "HTTP $http_code - $description"
        if [ -n "$response_body" ] && [ "$response_body" != "null" ]; then
            echo "$response_body" | jq '.' 2>/dev/null || echo "$response_body"
        fi
    else
        print_status "ERROR" "HTTP $http_code (expected $expected_status) - $description"
        echo "$response_body"
    fi
}

# Function to test login and extract token
test_login() {
    echo -e "\n${BLUE}🔐 Testing User Login${NC}"
    echo "══════════════════════════════════════"
    
    response=$(curl -s -w "\n%{http_code}" -X POST \
        -H "Content-Type: application/json" \
        -H "Accept: application/json" \
        -d "{\"email\": \"$EMAIL\", \"password\": \"$PASSWORD\", \"remember_me\": true}" \
        "$BASE_URL/auth/login" -k)
    
    http_code=$(echo "$response" | tail -n1)
    response_body=$(echo "$response" | head -n -1)
    
    if [ "$http_code" -eq 200 ]; then
        print_status "SUCCESS" "Login successful"
        echo "$response_body" | jq '.'
        
        # Extract token
        USER_TOKEN=$(echo "$response_body" | jq -r '.token')
        if [ "$USER_TOKEN" != "null" ] && [ -n "$USER_TOKEN" ]; then
            print_status "INFO" "Token extracted: ${USER_TOKEN:0:20}..."
            return 0
        else
            print_status "ERROR" "Could not extract token from response"
            return 1
        fi
    else
        print_status "ERROR" "Login failed - HTTP $http_code"
        echo "$response_body"
        return 1
    fi
}

# Function to test logout
test_logout() {
    echo -e "\n${BLUE}🚪 Testing Logout${NC}"
    echo "══════════════════════════════════════"
    
    response=$(curl -s -w "\n%{http_code}" -X POST \
        -H "Authorization: Bearer $USER_TOKEN" \
        -H "Content-Type: application/json" \
        -H "Accept: application/json" \
        "$BASE_URL/auth/logout" -k)
    
    http_code=$(echo "$response" | tail -n1)
    response_body=$(echo "$response" | head -n -1)
    
    if [ "$http_code" -eq 200 ]; then
        print_status "SUCCESS" "Logout successful"
        echo "$response_body" | jq '.'
    else
        print_status "ERROR" "Logout failed - HTTP $http_code"
        echo "$response_body"
    fi
}

# Main execution
echo -e "${BLUE}🧪 User API Testing Suite${NC}"
echo "════════════════════════════════════════════════"
echo "Base URL: $BASE_URL"
echo "Test User: $EMAIL"
echo "════════════════════════════════════════════════"

# Step 1: Test login
if test_login; then
    
    # Step 2: Test protected endpoints
    echo -e "\n${BLUE}👤 Testing User Endpoints${NC}"
    echo "══════════════════════════════════════"
    
    test_endpoint "GET" "/user" "" 200 "Get authenticated user info"
    
    echo -e "\n${BLUE}📂 Testing Resource Endpoints${NC}"
    echo "══════════════════════════════════════"
    
    test_endpoint "GET" "/category" "" 200 "Get categories"
    test_endpoint "GET" "/message" "" 200 "Get messages"
    
    # Step 3: Test invalid token after some operations
    echo -e "\n${BLUE}🔒 Testing Token Validation${NC}"
    echo "══════════════════════════════════════"
    
    # Save current token
    VALID_TOKEN=$USER_TOKEN
    
    # Test with invalid token
    USER_TOKEN="invalid_token_123"
    test_endpoint "GET" "/user" "" 401 "Get user info with invalid token"
    
    # Restore valid token
    USER_TOKEN=$VALID_TOKEN
    
    # Step 4: Test logout
    test_logout
    
    # Step 5: Test using token after logout (should fail)
    echo -e "\n${BLUE}🔒 Testing Token After Logout${NC}"
    echo "══════════════════════════════════════"
    
    test_endpoint "GET" "/user" "" 401 "Get user info after logout"
    
else
    print_status "ERROR" "Login test failed. Skipping other tests."
    exit 1
fi

echo -e "\n${GREEN}🎉 User API testing completed!${NC}"
echo "════════════════════════════════════════════════" 