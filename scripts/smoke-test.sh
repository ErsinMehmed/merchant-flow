#!/bin/sh
# Smoke test for a running stack (docker compose up): checks every component
# through Nginx the way a reviewer would use it. Needs curl and jq.
#
#   sh scripts/smoke-test.sh                  # http://localhost:8080
#   BASE_URL=http://localhost:8090 sh scripts/smoke-test.sh
#
# It relies on the seeded demo accounts and creates one small charge.
set -eu

BASE_URL="${BASE_URL:-http://localhost:8080}"
PASSWORD="${DEMO_PASSWORD:-MerchantFlowDemo!2026}"

tmp=$(mktemp -d)
trap 'rm -rf "$tmp"' EXIT
failures=0
STATUS=000

pass() { printf 'ok    %s\n' "$1"; }
fail() {
    printf 'FAIL  %s\n' "$1"
    failures=$((failures + 1))
}

# request METHOD PATH [TOKEN] [JSON_BODY] [EXTRA_HEADER]
# Sets STATUS; the body is in $tmp/body and the headers in $tmp/headers.
request() {
    method=$1 path=$2 token=${3:-} body=${4:-} header=${5:-}
    : >"$tmp/body"
    : >"$tmp/headers"
    set -- -s -o "$tmp/body" -D "$tmp/headers" -w '%{http_code}' -X "$method" "$BASE_URL$path"
    if [ -n "$token" ]; then set -- "$@" -H "Authorization: Bearer $token"; fi
    if [ -n "$body" ]; then set -- "$@" -H 'Content-Type: application/json' --data "$body"; fi
    if [ -n "$header" ]; then set -- "$@" -H "$header"; fi
    STATUS=$(curl "$@") || STATUS=000
}

# expect STATUS DESCRIPTION
expect() {
    if [ "$STATUS" = "$1" ]; then
        pass "$2"
    else
        fail "$2 (HTTP $STATUS, expected $1): $(head -c 300 "$tmp/body")"
    fi
}

# check DESCRIPTION JQ_FILTER — passes when the filter is true for the body.
check() {
    if jq -e "$2" "$tmp/body" >/dev/null 2>&1; then pass "$1"; else fail "$1: $(head -c 300 "$tmp/body")"; fi
}

login() {
    request POST /api/v1/auth/login "" "{\"email\":\"$1\",\"password\":\"$PASSWORD\"}"
    jq -r '.data.token // empty' "$tmp/body" 2>/dev/null || true
}

# field JQ_FILTER — a value from the last response, empty if missing.
field() {
    jq -r "$1 // empty" "$tmp/body" 2>/dev/null || true
}

echo "Smoke test against $BASE_URL"

# --- Services ---------------------------------------------------------------
for path in /health /health/php /api/health; do
    request GET "$path"
    expect 200 "health $path"
done

request GET /
expect 200 "merchant portal is served"
if grep -q 'id="root"' "$tmp/body"; then pass "merchant portal HTML"; else fail "merchant portal HTML"; fi

request GET /admin/login
expect 200 "admin portal login page"

# --- Authentication ---------------------------------------------------------
request GET /api/v1/charges
expect 401 "API refuses requests without a session"

request POST /api/v1/auth/login "" "{\"email\":\"merchant@merchant-flow.test\",\"password\":\"wrong\"}"
expect 401 "API refuses a wrong password"

merchant=$(login merchant@merchant-flow.test)
if [ -n "$merchant" ]; then pass "merchant signs in"; else fail "merchant signs in"; fi
other=$(login merchant-two@merchant-flow.test)
agent=$(login agent@merchant-flow.test)
admin=$(login admin@merchant-flow.test)

# --- Scope ------------------------------------------------------------------
request GET /api/v1/accounts "$merchant"
expect 200 "merchant lists accounts"
check "merchant sees only their two accounts" '(.data | length) == 2'

request GET /api/v1/accounts "$admin"
check "admin sees every account" '(.data | length) == 4'

request GET '/api/v1/charges?page_size=100' "$merchant"
expect 200 "merchant lists charges"
check "list is paginated with a total" '.meta.total > 0 and (.data | length) <= 100'

request GET '/api/v1/charges?page_size=1&status=captured&disputed=false' "$other"
foreign=$(field '.data[0].id')
request GET "/api/v1/charges/$foreign" "$merchant"
expect 404 "another merchant's charge is not found"
request POST "/api/v1/charges/$foreign/refund" "$merchant" '{"amount":100}'
expect 404 "refunding another merchant's charge is not found"

request GET '/api/v1/charges?page_size=1&status=captured&disputed=false' "$agent"
check "agent sees their merchants' charges" '.meta.total > 0'
served=$(field '.data[0].id')
request POST "/api/v1/charges/$served/refund" "$agent" '{"amount":100}'
expect 403 "agent cannot refund"

# --- Charge operations --------------------------------------------------------
request GET /api/v1/accounts "$merchant"
account=$(field '[.data[] | select(.accepts_charges)][0].id')
key="smoke-$(date +%s)-$$"
charge_body="{\"account_id\":$account,\"amount\":1500,\"currency\":\"USD\",\"customer_email\":\"smoke-test@example.com\"}"

request POST /api/v1/charges "$merchant" "$charge_body" "Idempotency-Key: $key"
expect 201 "merchant creates a charge"
check "new charge is captured" '.data.status == "captured" and .data.amount_captured == 1500'
charge=$(field '.data.id')

request POST /api/v1/charges "$merchant" "$charge_body" "Idempotency-Key: $key"
expect 201 "retry with the same Idempotency-Key"
if grep -qi '^idempotent-replayed: true' "$tmp/headers" && jq -e --argjson id "$charge" '.data.id == $id' "$tmp/body" >/dev/null; then
    pass "retry replays the first charge instead of creating another"
else
    fail "retry replays the first charge instead of creating another"
fi

request POST "/api/v1/charges/$charge/refund" "$merchant" '{"amount":500}'
expect 200 "partial refund"
check "partial refund keeps the charge captured" '.data.status == "captured" and .data.amount_refunded == 500'

request POST "/api/v1/charges/$charge/refund" "$merchant" '{"amount":1001}'
expect 422 "refund above the remaining amount is refused"

request POST "/api/v1/charges/$charge/void" "$merchant"
expect 409 "a captured charge cannot be voided"

request POST "/api/v1/charges/$charge/refund" "$merchant"
expect 200 "refund the rest"
check "charge is fully refunded" '.data.status == "refunded" and .data.refunded == true'

request POST "/api/v1/charges/$charge/refund" "$merchant" '{"amount":1}'
expect 409 "second full refund is refused"

# --- Sign out -----------------------------------------------------------------
request POST /api/v1/auth/logout "$merchant"
expect 204 "merchant signs out"
request GET /api/v1/me "$merchant"
expect 401 "token is invalid after sign-out"

echo
if [ "$failures" -gt 0 ]; then
    echo "$failures check(s) failed"
    exit 1
fi
echo "All checks passed"
