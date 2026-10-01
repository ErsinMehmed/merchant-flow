package httpapi

import (
	"net/http"
	"strings"
	"testing"
)

func TestLoginIssuesAnHttpOnlySessionCookie(t *testing.T) {
	h := newHarness(t)
	u := h.user("merchant")

	res := h.request(http.MethodPost, "/api/v1/auth/login", "", map[string]string{"email": strings.ToUpper(u.Email), "password": testPassword}, nil)
	expectStatus(t, res, http.StatusOK)

	cookie := res.header.Get("Set-Cookie")
	for _, attr := range []string{"mf_session=mf_", "Path=/api", "HttpOnly", "SameSite=Strict"} {
		if !strings.Contains(cookie, attr) {
			t.Errorf("cookie %q lacks %q", cookie, attr)
		}
	}

	user := res.data()["user"].(map[string]any)
	if user["email"] != u.Email || len(user["permissions"].([]any)) == 0 {
		t.Fatalf("unexpected user %v", user)
	}

	// Only the hash of the token is stored.
	token := res.data()["token"].(string)
	var stored int
	if err := h.db.QueryRow(`SELECT COUNT(*) FROM api_tokens WHERE user_id = ? AND token_hash <> ?`, u.ID, token).Scan(&stored); err != nil || stored != 1 {
		t.Fatalf("expected one hashed token row, got %d (%v)", stored, err)
	}
}

func TestLoginRejectsBadCredentialsTheSameWay(t *testing.T) {
	h := newHarness(t)
	u := h.user("merchant")

	wrongPassword := h.request(http.MethodPost, "/api/v1/auth/login", "", map[string]string{"email": u.Email, "password": "nope"}, nil)
	unknownUser := h.request(http.MethodPost, "/api/v1/auth/login", "", map[string]string{"email": "nobody-" + unique() + "@test.example", "password": testPassword}, nil)

	expectError(t, wrongPassword, http.StatusUnauthorized, "invalid_credentials")
	expectError(t, unknownUser, http.StatusUnauthorized, "invalid_credentials")
	if string(wrongPassword.raw) != string(unknownUser.raw) {
		t.Fatal("wrong password and unknown email must be indistinguishable")
	}

	expectError(t, h.request(http.MethodPost, "/api/v1/auth/login", "", map[string]string{"email": u.Email}, nil),
		http.StatusUnprocessableEntity, "validation_failed")
	expectError(t, h.request(http.MethodPost, "/api/v1/auth/login", "", "email=a&password=b", map[string]string{"Content-Type": "application/x-www-form-urlencoded"}),
		http.StatusUnsupportedMediaType, "unsupported_media_type")
}

func TestLoginIsRateLimited(t *testing.T) {
	h := newHarness(t)
	u := h.user("merchant")

	for i := 0; i < 5; i++ {
		h.request(http.MethodPost, "/api/v1/auth/login", "", map[string]string{"email": u.Email, "password": "nope"}, nil)
	}
	res := h.request(http.MethodPost, "/api/v1/auth/login", "", map[string]string{"email": u.Email, "password": testPassword}, nil)

	expectError(t, res, http.StatusTooManyRequests, "too_many_attempts")
	if res.header.Get("Retry-After") == "" {
		t.Fatal("expected a Retry-After header")
	}
}

func TestEndpointsRequireASession(t *testing.T) {
	h := newHarness(t)
	routes := [][2]string{
		{http.MethodGet, "/api/v1/me"},
		{http.MethodGet, "/api/v1/accounts"},
		{http.MethodGet, "/api/v1/charges"},
		{http.MethodGet, "/api/v1/charges/1"},
		{http.MethodPost, "/api/v1/charges"},
		{http.MethodPost, "/api/v1/charges/1/capture"},
		{http.MethodPost, "/api/v1/charges/1/refund"},
		{http.MethodPost, "/api/v1/charges/1/void"},
	}
	for _, route := range routes {
		expectError(t, h.request(route[0], route[1], "", nil, nil), http.StatusUnauthorized, "unauthenticated")
		expectError(t, h.request(route[0], route[1], "mf_"+strings.Repeat("x", 43), nil, nil), http.StatusUnauthorized, "unauthenticated")
	}
}

func TestLogoutRevokesTheSession(t *testing.T) {
	h := newHarness(t)
	c := h.login(h.user("merchant"))

	expectStatus(t, c.get("/api/v1/me"), http.StatusOK)
	expectStatus(t, c.post("/api/v1/auth/logout", nil), http.StatusNoContent)
	expectError(t, c.get("/api/v1/me"), http.StatusUnauthorized, "unauthenticated")
}

func TestExpiredAndRevokedSessionsAreRejected(t *testing.T) {
	h := newHarness(t)
	u := h.user("merchant")

	expired := h.login(u)
	h.exec(`UPDATE api_tokens SET expires_at = NOW() - INTERVAL 1 MINUTE WHERE user_id = ?`, u.ID)
	expectError(t, expired.get("/api/v1/me"), http.StatusUnauthorized, "unauthenticated")

	// Deleting the row is what "revoke" in the admin portal does.
	revoked := h.login(u)
	h.exec(`DELETE FROM api_tokens WHERE user_id = ?`, u.ID)
	expectError(t, revoked.get("/api/v1/me"), http.StatusUnauthorized, "unauthenticated")
}

func TestCookieSessionsRefuseCrossSiteWrites(t *testing.T) {
	h := newHarness(t)
	u := h.user("merchant")
	account := h.account("active", true)
	h.linkMerchant(account, u.ID)
	chargeID := h.charge(account, chargeRow{})

	login := h.request(http.MethodPost, "/api/v1/auth/login", "", map[string]string{"email": u.Email, "password": testPassword}, nil)
	cookie := "mf_session=" + login.data()["token"].(string)
	path := "/api/v1/charges/" + itoa(chargeID) + "/refund"

	crossSite := h.request(http.MethodPost, path, "", map[string]any{"amount": 100},
		map[string]string{"Cookie": cookie, "Origin": "https://evil.example", "Sec-Fetch-Site": "cross-site"})
	expectError(t, crossSite, http.StatusForbidden, "cross_site_request")

	sameOrigin := h.request(http.MethodPost, path, "", map[string]any{"amount": 100},
		map[string]string{"Cookie": cookie, "Sec-Fetch-Site": "same-origin"})
	expectStatus(t, sameOrigin, http.StatusOK)

	if _, refunded := h.chargeState(chargeID); refunded != 100 {
		t.Fatalf("expected only the same-origin refund, got %d", refunded)
	}
}
