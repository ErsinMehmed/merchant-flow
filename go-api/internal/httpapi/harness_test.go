package httpapi

import (
	"bytes"
	"crypto/rand"
	"database/sql"
	"encoding/hex"
	"encoding/json"
	"io"
	"log/slog"
	"net/http"
	"net/http/httptest"
	"os"
	"testing"
	"time"

	"golang.org/x/crypto/bcrypt"

	"github.com/ErsinMehmed/merchant-flow/go-api/internal/auth"
	"github.com/ErsinMehmed/merchant-flow/go-api/internal/charge"
	"github.com/ErsinMehmed/merchant-flow/go-api/internal/config"
	"github.com/ErsinMehmed/merchant-flow/go-api/internal/database"
)

// Integration tests run the real handler against MySQL with the schema and
// roles created by the PHP migrations and seeders (see go-test-schema in
// compose.yaml). Every test creates its own users, accounts and charges, so
// tests do not depend on each other or on demo data.

const testPassword = "Test-Passw0rd!"

var testPasswordHash = func() string {
	h, err := bcrypt.GenerateFromPassword([]byte(testPassword), bcrypt.MinCost)
	if err != nil {
		panic(err)
	}
	return string(h)
}()

func TestMain(m *testing.M) {
	// Request logs are noise in test output.
	slog.SetDefault(slog.New(slog.NewTextHandler(io.Discard, nil)))
	os.Exit(m.Run())
}

type harness struct {
	t   *testing.T
	db  *sql.DB
	url string
}

func newHarness(t *testing.T) *harness {
	t.Helper()
	dsn := os.Getenv("TEST_DATABASE_DSN")
	if dsn == "" {
		t.Skip("TEST_DATABASE_DSN is not set; run: docker compose --profile test run --rm go-test")
	}

	db, err := database.OpenDSN(dsn)
	if err != nil {
		t.Fatal(err)
	}
	t.Cleanup(func() { _ = db.Close() })

	cfg := config.Config{SessionTTL: time.Hour, CookieSecure: false, TrustProxyHeaders: true}
	srv := httptest.NewServer(New(cfg, db, auth.NewService(db, time.Hour), charge.NewService(db)).Handler())
	t.Cleanup(srv.Close)

	return &harness{t: t, db: db, url: srv.URL}
}

func unique() string {
	b := make([]byte, 6)
	_, _ = rand.Read(b)
	return hex.EncodeToString(b)
}

func (h *harness) exec(query string, args ...any) sql.Result {
	h.t.Helper()
	res, err := h.db.Exec(query, args...)
	if err != nil {
		h.t.Fatalf("exec %q: %v", query, err)
	}
	return res
}

func (h *harness) insertID(query string, args ...any) int64 {
	h.t.Helper()
	id, err := h.exec(query, args...).LastInsertId()
	if err != nil {
		h.t.Fatal(err)
	}
	return id
}

type testUser struct {
	ID    int64
	Email string
}

// user creates a user with the given Spatie roles, stored the way PHP
// stores them.
func (h *harness) user(roles ...string) testUser {
	h.t.Helper()
	email := "user-" + unique() + "@test.example"
	id := h.insertID(`INSERT INTO users (name, email, password, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())`,
		"Test User", email, testPasswordHash)
	for _, role := range roles {
		h.exec(`INSERT INTO model_has_roles (role_id, model_type, model_id)
			SELECT id, 'user', ? FROM roles WHERE name = ? AND guard_name = 'web'`, id, role)
	}
	return testUser{ID: id, Email: email}
}

func (h *harness) account(status string, active bool) int64 {
	h.t.Helper()
	creator := h.user()
	return h.insertID(`INSERT INTO accounts (business_name, dba_name, legal_name, email, mid, status, active_yn, created_by, created_at, updated_at)
		VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())`,
		"Merchant "+unique(), "DBA", "Legal", "billing@test.example", "MID"+unique(), status, active, creator.ID)
}

func (h *harness) agent(parentID *int64) int64 {
	h.t.Helper()
	return h.insertID(`INSERT INTO agents (first_name, last_name, email, company_legal_name, company_dba, type, default_split, parent_agent_id, created_at, updated_at)
		VALUES ('Test', 'Agent', ?, 'Agency LLC', 'Agency', 'iso', 10, ?, NOW(), NOW())`,
		"agent-"+unique()+"@test.example", parentID)
}

func (h *harness) linkMerchant(accountID, userID int64) {
	h.exec(`INSERT INTO account_user (account_id, user_id) VALUES (?, ?)`, accountID, userID)
}

func (h *harness) linkAgentUser(agentID, userID int64) {
	h.exec(`INSERT INTO agent_user (agent_id, user_id) VALUES (?, ?)`, agentID, userID)
}

func (h *harness) linkAgentAccount(agentID, accountID int64) {
	h.exec(`INSERT INTO agent_account (agent_id, account_id) VALUES (?, ?)`, agentID, accountID)
}

type chargeRow struct {
	Status   string
	Amount   int64
	Captured int64
	Refunded int64
	Disputed bool
}

func (h *harness) charge(accountID int64, row chargeRow) int64 {
	h.t.Helper()
	if row.Amount == 0 {
		row.Amount = 5000
	}
	if row.Status == "" {
		row.Status = "captured"
		row.Captured = row.Amount
	}
	creator := h.user()
	return h.insertID(`INSERT INTO charges (account_id, amount, amount_captured, amount_refunded, currency, status,
			external_transaction_id, customer_email, refunded, disputed, created_by, created_at, updated_at)
		VALUES (?, ?, ?, ?, 'USD', ?, ?, ?, ?, ?, ?, NOW(), NOW())`,
		accountID, row.Amount, row.Captured, row.Refunded, row.Status, "txn_test_"+unique(),
		"customer-"+unique()+"@example.com", row.Status == "refunded", row.Disputed, creator.ID)
}

func (h *harness) chargeState(id int64) (status string, refunded int64) {
	h.t.Helper()
	if err := h.db.QueryRow(`SELECT status, amount_refunded FROM charges WHERE id = ?`, id).Scan(&status, &refunded); err != nil {
		h.t.Fatal(err)
	}
	return status, refunded
}

// client is a signed-in API caller using a Bearer token.
type client struct {
	h     *harness
	token string
}

func (h *harness) login(u testUser) *client {
	h.t.Helper()
	res := h.request(http.MethodPost, "/api/v1/auth/login", "", map[string]string{"email": u.Email, "password": testPassword}, nil)
	if res.status != http.StatusOK {
		h.t.Fatalf("login failed: %d %s", res.status, res.raw)
	}
	return &client{h: h, token: res.data()["token"].(string)}
}

type response struct {
	status int
	header http.Header
	raw    []byte
	body   map[string]any
}

func (r response) data() map[string]any {
	d, _ := r.body["data"].(map[string]any)
	return d
}

func (r response) list() []any {
	d, _ := r.body["data"].([]any)
	return d
}

func (r response) errorCode() string {
	e, _ := r.body["error"].(map[string]any)
	code, _ := e["code"].(string)
	return code
}

func (c *client) do(method, path string, body any, headers map[string]string) response {
	c.h.t.Helper()
	return c.h.request(method, path, c.token, body, headers)
}

func (c *client) get(path string) response {
	c.h.t.Helper()
	return c.do(http.MethodGet, path, nil, nil)
}

func (c *client) post(path string, body any) response {
	c.h.t.Helper()
	return c.do(http.MethodPost, path, body, nil)
}

func (h *harness) request(method, path, token string, body any, headers map[string]string) response {
	h.t.Helper()
	var reader io.Reader
	if body != nil {
		raw, ok := body.(string)
		if !ok {
			b, err := json.Marshal(body)
			if err != nil {
				h.t.Fatal(err)
			}
			raw = string(b)
		}
		reader = bytes.NewReader([]byte(raw))
	}

	req, err := http.NewRequest(method, h.url+path, reader)
	if err != nil {
		h.t.Fatal(err)
	}
	if body != nil {
		req.Header.Set("Content-Type", "application/json")
	}
	if token != "" {
		req.Header.Set("Authorization", "Bearer "+token)
	}
	for k, v := range headers {
		req.Header.Set(k, v)
	}

	res, err := http.DefaultClient.Do(req)
	if err != nil {
		h.t.Fatal(err)
	}
	defer res.Body.Close()

	raw, _ := io.ReadAll(res.Body)
	out := response{status: res.StatusCode, header: res.Header, raw: raw}
	_ = json.Unmarshal(raw, &out.body)
	return out
}

func idOf(v any) int64 {
	return int64(v.(map[string]any)["id"].(float64))
}

func expectStatus(t *testing.T, r response, want int) {
	t.Helper()
	if r.status != want {
		t.Fatalf("status %d, want %d: %s", r.status, want, r.raw)
	}
}

func expectError(t *testing.T, r response, status int, code string) {
	t.Helper()
	expectStatus(t, r, status)
	if r.errorCode() != code {
		t.Fatalf("error code %q, want %q: %s", r.errorCode(), code, r.raw)
	}
}
