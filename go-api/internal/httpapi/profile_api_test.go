package httpapi

import (
	"encoding/base64"
	"net/http"
	"strings"
	"testing"
)

// A 1x1 transparent PNG.
var testPNG, _ = base64.StdEncoding.DecodeString("iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=")

func (c *client) putPhoto(contentType string, content []byte) response {
	c.h.t.Helper()
	return c.do(http.MethodPut, avatarPath, string(content), map[string]string{"Content-Type": contentType})
}

func TestProfilePhotoCanBeUploadedServedAndRemoved(t *testing.T) {
	h := newHarness(t)
	c := h.login(h.user("merchant"))

	if url := c.get("/api/v1/me").data()["avatar_url"]; url != nil {
		t.Fatalf("expected no photo, got %v", url)
	}

	uploaded := c.putPhoto("image/png", testPNG)
	expectStatus(t, uploaded, http.StatusOK)
	url, _ := uploaded.data()["avatar_url"].(string)
	if !strings.HasPrefix(url, avatarPath+"?v=") {
		t.Fatalf("unexpected avatar_url %q", url)
	}
	if got := c.get("/api/v1/me").data()["avatar_url"]; got != url {
		t.Fatalf("/me returned %v, want %q", got, url)
	}

	photo := c.get(url)
	expectStatus(t, photo, http.StatusOK)
	if string(photo.raw) != string(testPNG) || photo.header.Get("Content-Type") != "image/png" {
		t.Fatalf("unexpected photo %q (%s)", photo.raw, photo.header.Get("Content-Type"))
	}
	if photo.header.Get("Content-Security-Policy") != "default-src 'none'" {
		t.Fatal("the photo must be served with a restrictive CSP")
	}

	removed := c.do(http.MethodDelete, avatarPath, nil, nil)
	expectStatus(t, removed, http.StatusOK)
	if removed.data()["avatar_url"] != nil {
		t.Fatalf("expected no photo after removal: %s", removed.raw)
	}
	expectError(t, c.get(avatarPath), http.StatusNotFound, "not_found")
}

func TestProfilePhotosBelongToTheirUser(t *testing.T) {
	h := newHarness(t)
	owner := h.login(h.user("merchant"))
	other := h.login(h.user("merchant"))

	expectStatus(t, owner.putPhoto("image/png", testPNG), http.StatusOK)
	expectError(t, other.get(avatarPath), http.StatusNotFound, "not_found")
}

func TestProfilePhotoUploadsAreChecked(t *testing.T) {
	h := newHarness(t)
	u := h.user("merchant")
	c := h.login(u)

	// The declared type is not trusted: the bytes must be an accepted image.
	svg := []byte(`<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>`)
	expectError(t, c.putPhoto("image/png", svg), http.StatusUnprocessableEntity, "validation_failed")
	expectError(t, c.putPhoto("image/svg+xml", svg), http.StatusUnsupportedMediaType, "unsupported_media_type")

	tooLarge := append(append([]byte{}, testPNG...), make([]byte, 2<<20)...)
	expectError(t, c.putPhoto("image/png", tooLarge), http.StatusRequestEntityTooLarge, "payload_too_large")

	var stored int
	if err := h.db.QueryRow(`SELECT COUNT(*) FROM user_avatars WHERE user_id = ?`, u.ID).Scan(&stored); err != nil || stored != 0 {
		t.Fatalf("expected nothing stored, got %d (%v)", stored, err)
	}
}

func TestChangePasswordValidatesTheRequest(t *testing.T) {
	h := newHarness(t)
	c := h.login(h.user("merchant"))

	cases := []struct {
		body  map[string]string
		field string
	}{
		{map[string]string{"password": "New-Passw0rd!", "password_confirmation": "New-Passw0rd!"}, "current_password"},
		{map[string]string{"current_password": testPassword, "password": "short", "password_confirmation": "short"}, "password"},
		{map[string]string{"current_password": testPassword, "password": "new-passw0rd!", "password_confirmation": "new-passw0rd!"}, "password"},
		{map[string]string{"current_password": testPassword, "password": "New-Passw0rd!", "password_confirmation": "New-Passw0rd?"}, "password_confirmation"},
		{map[string]string{"current_password": "wrong", "password": "New-Passw0rd!", "password_confirmation": "New-Passw0rd!"}, "current_password"},
	}
	for _, tc := range cases {
		res := c.post("/api/v1/me/password", tc.body)
		expectError(t, res, http.StatusUnprocessableEntity, "validation_failed")
		fields, _ := res.body["error"].(map[string]any)["fields"].(map[string]any)
		if _, ok := fields[tc.field]; !ok {
			t.Errorf("%v: expected an error for %s, got %s", tc.body, tc.field, res.raw)
		}
	}
}

func TestChangePasswordEndsTheOtherSessions(t *testing.T) {
	h := newHarness(t)
	u := h.user("merchant")
	current := h.login(u)
	other := h.login(u)
	h.exec(`INSERT INTO sessions (id, user_id, payload, last_activity) VALUES (?, ?, '', UNIX_TIMESTAMP())`, "nova-"+unique(), u.ID)

	const newPassword = "Brand-New-Passw0rd!"
	res := current.post("/api/v1/me/password", map[string]string{
		"current_password": testPassword, "password": newPassword, "password_confirmation": newPassword,
	})
	expectStatus(t, res, http.StatusNoContent)

	expectStatus(t, current.get("/api/v1/me"), http.StatusOK)
	expectError(t, other.get("/api/v1/me"), http.StatusUnauthorized, "unauthenticated")
	var novaSessions int
	if err := h.db.QueryRow(`SELECT COUNT(*) FROM sessions WHERE user_id = ?`, u.ID).Scan(&novaSessions); err != nil || novaSessions != 0 {
		t.Fatalf("expected the admin sessions to end, got %d (%v)", novaSessions, err)
	}

	oldLogin := h.request(http.MethodPost, "/api/v1/auth/login", "", map[string]string{"email": u.Email, "password": testPassword}, nil)
	expectError(t, oldLogin, http.StatusUnauthorized, "invalid_credentials")
	newLogin := h.request(http.MethodPost, "/api/v1/auth/login", "", map[string]string{"email": u.Email, "password": newPassword}, nil)
	expectStatus(t, newLogin, http.StatusOK)
}

func TestChangePasswordLimitsWrongCurrentPasswords(t *testing.T) {
	h := newHarness(t)
	c := h.login(h.user("merchant"))
	body := map[string]string{"current_password": "wrong", "password": "New-Passw0rd!", "password_confirmation": "New-Passw0rd!"}

	for i := 0; i < 5; i++ {
		expectError(t, c.post("/api/v1/me/password", body), http.StatusUnprocessableEntity, "validation_failed")
	}
	body["current_password"] = testPassword
	res := c.post("/api/v1/me/password", body)
	expectError(t, res, http.StatusTooManyRequests, "too_many_attempts")
	if res.header.Get("Retry-After") == "" {
		t.Fatal("expected a Retry-After header")
	}
}
