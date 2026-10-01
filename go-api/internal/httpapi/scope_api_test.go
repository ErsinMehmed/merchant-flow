package httpapi

import (
	"net/http"
	"slices"
	"strconv"
	"testing"
)

func itoa(id int64) string { return strconv.FormatInt(id, 10) }

func chargeIDs(r response) []int64 {
	ids := []int64{}
	for _, item := range r.list() {
		ids = append(ids, idOf(item))
	}
	slices.Sort(ids)
	return ids
}

// The core rule: a caller only ever sees and operates on charges of the
// accounts in their scope, and anything else is "not found".
func TestMerchantsOnlyReachTheirOwnCharges(t *testing.T) {
	h := newHarness(t)
	alice, bob := h.user("merchant"), h.user("merchant")
	aliceAccount, bobAccount := h.account("active", true), h.account("active", true)
	h.linkMerchant(aliceAccount, alice.ID)
	h.linkMerchant(bobAccount, bob.ID)
	aliceCharge := h.charge(aliceAccount, chargeRow{})
	bobCharge := h.charge(bobAccount, chargeRow{})
	bobAuthorized := h.charge(bobAccount, chargeRow{Status: "authorized", Amount: 900})

	c := h.login(alice)

	if got := chargeIDs(c.get("/api/v1/charges?page_size=100")); !slices.Equal(got, []int64{aliceCharge}) {
		t.Fatalf("alice sees %v", got)
	}
	expectStatus(t, c.get("/api/v1/charges/"+itoa(aliceCharge)), http.StatusOK)

	expectError(t, c.get("/api/v1/charges/"+itoa(bobCharge)), http.StatusNotFound, "not_found")
	expectError(t, c.post("/api/v1/charges/"+itoa(bobCharge)+"/refund", map[string]any{"amount": 100}), http.StatusNotFound, "not_found")
	expectError(t, c.post("/api/v1/charges/"+itoa(bobAuthorized)+"/void", nil), http.StatusNotFound, "not_found")
	expectError(t, c.post("/api/v1/charges/"+itoa(bobAuthorized)+"/capture", nil), http.StatusNotFound, "not_found")
	expectError(t, c.post("/api/v1/charges", map[string]any{
		"account_id": bobAccount, "amount": 100, "currency": "USD", "customer_email": "x@example.com",
	}), http.StatusNotFound, "not_found")

	// Filtering by someone else's account simply finds nothing.
	foreign := c.get("/api/v1/charges?account_id=" + itoa(bobAccount))
	expectStatus(t, foreign, http.StatusOK)
	if len(foreign.list()) != 0 || foreign.body["meta"].(map[string]any)["total"].(float64) != 0 {
		t.Fatalf("expected no charges, got %s", foreign.raw)
	}

	// A charge that does not exist looks exactly like one that belongs to
	// someone else.
	missing := c.get("/api/v1/charges/999999999")
	if string(missing.raw) != string(c.get("/api/v1/charges/"+itoa(bobCharge)).raw) {
		t.Fatal("foreign and missing charges must be indistinguishable")
	}

	if status, refunded := h.chargeState(bobCharge); status != "captured" || refunded != 0 {
		t.Fatalf("bob's charge was changed: %s %d", status, refunded)
	}
}

func TestMerchantWithSeveralAccountsSeesAllOfThem(t *testing.T) {
	h := newHarness(t)
	u := h.user("merchant")
	first, second := h.account("active", true), h.account("suspended", false)
	h.linkMerchant(first, u.ID)
	h.linkMerchant(second, u.ID)
	want := []int64{h.charge(first, chargeRow{}), h.charge(second, chargeRow{})}

	c := h.login(u)
	if got := chargeIDs(c.get("/api/v1/charges")); !slices.Equal(got, want) {
		t.Fatalf("sees %v, want %v", got, want)
	}

	accounts := c.get("/api/v1/accounts").list()
	if len(accounts) != 2 {
		t.Fatalf("expected two accounts, got %d", len(accounts))
	}
	acceptance := map[int64]bool{}
	for _, a := range accounts {
		acceptance[idOf(a)] = a.(map[string]any)["accepts_charges"].(bool)
	}
	if !acceptance[first] || acceptance[second] {
		t.Fatalf("unexpected accepts_charges %v", acceptance)
	}
}

func TestAgentsSeeTheirMerchantsButCannotMoveMoney(t *testing.T) {
	h := newHarness(t)
	agentUser := h.user("agent")
	agent := h.agent(nil)
	h.linkAgentUser(agent, agentUser.ID)

	served, other := h.account("active", true), h.account("active", true)
	h.linkAgentAccount(agent, served)
	servedCharge := h.charge(served, chargeRow{})
	servedAuthorized := h.charge(served, chargeRow{Status: "authorized"})
	otherCharge := h.charge(other, chargeRow{})

	// A sub-agent's merchants are not visible to the parent agent.
	subAgent := h.agent(&agent)
	subAccount := h.account("active", true)
	h.linkAgentAccount(subAgent, subAccount)
	h.charge(subAccount, chargeRow{})

	c := h.login(agentUser)

	if got := chargeIDs(c.get("/api/v1/charges")); !slices.Equal(got, []int64{servedCharge, servedAuthorized}) {
		t.Fatalf("agent sees %v", got)
	}
	expectError(t, c.get("/api/v1/charges/"+itoa(otherCharge)), http.StatusNotFound, "not_found")

	expectError(t, c.post("/api/v1/charges/"+itoa(servedCharge)+"/refund", nil), http.StatusForbidden, "forbidden")
	expectError(t, c.post("/api/v1/charges/"+itoa(servedAuthorized)+"/void", nil), http.StatusForbidden, "forbidden")
	expectError(t, c.post("/api/v1/charges/"+itoa(servedAuthorized)+"/capture", nil), http.StatusForbidden, "forbidden")
	expectError(t, c.post("/api/v1/charges", map[string]any{
		"account_id": served, "amount": 100, "currency": "USD", "customer_email": "x@example.com",
	}), http.StatusForbidden, "forbidden")

	// The permission check comes first, so a foreign ID gives the same
	// answer and reveals nothing.
	expectError(t, c.post("/api/v1/charges/"+itoa(otherCharge)+"/refund", nil), http.StatusForbidden, "forbidden")

	for _, item := range c.get("/api/v1/charges").list() {
		if actions := item.(map[string]any)["actions"].([]any); len(actions) != 0 {
			t.Fatalf("agent should have no actions, got %v", actions)
		}
	}
}

func TestAdministratorsSeeEveryAccount(t *testing.T) {
	h := newHarness(t)
	first, second := h.account("active", true), h.account("active", true)
	a, b := h.charge(first, chargeRow{}), h.charge(second, chargeRow{})

	c := h.login(h.user("admin"))
	for _, id := range []int64{a, b} {
		expectStatus(t, c.get("/api/v1/charges/"+itoa(id)), http.StatusOK)
	}
	expectStatus(t, c.post("/api/v1/charges/"+itoa(b)+"/refund", map[string]any{"amount": 100}), http.StatusOK)

	listed := c.get("/api/v1/charges?account_id=" + itoa(first))
	if got := chargeIDs(listed); !slices.Equal(got, []int64{a}) {
		t.Fatalf("admin filter returned %v", got)
	}
}

func TestUserWithoutRolesCanSignInButSeesNothing(t *testing.T) {
	h := newHarness(t)
	c := h.login(h.user())

	me := c.get("/api/v1/me")
	expectStatus(t, me, http.StatusOK)
	if perms := me.data()["permissions"].([]any); len(perms) != 0 {
		t.Fatalf("expected no permissions, got %v", perms)
	}
	expectError(t, c.get("/api/v1/charges"), http.StatusForbidden, "forbidden")
	expectError(t, c.get("/api/v1/accounts"), http.StatusForbidden, "forbidden")
}

// Permissions and links are read from the database on every request, so a
// change made in the admin portal applies to the very next call.
func TestPermissionChangesApplyImmediately(t *testing.T) {
	h := newHarness(t)
	u := h.user("merchant")
	account := h.account("active", true)
	h.linkMerchant(account, u.ID)
	chargeID := h.charge(account, chargeRow{Amount: 3000, Status: "captured", Captured: 3000})
	c := h.login(u)

	expectStatus(t, c.post("/api/v1/charges/"+itoa(chargeID)+"/refund", map[string]any{"amount": 100}), http.StatusOK)

	// Revoke charges.refund from the merchant role, as Nova would.
	h.exec(`DELETE rp FROM role_has_permissions rp
		JOIN roles r ON r.id = rp.role_id JOIN permissions p ON p.id = rp.permission_id
		WHERE r.name = 'merchant' AND p.name = 'charges.refund'`)
	t.Cleanup(func() {
		h.exec(`INSERT IGNORE INTO role_has_permissions (permission_id, role_id)
			SELECT p.id, r.id FROM permissions p, roles r WHERE p.name = 'charges.refund' AND r.name = 'merchant'`)
	})
	expectError(t, c.post("/api/v1/charges/"+itoa(chargeID)+"/refund", map[string]any{"amount": 100}), http.StatusForbidden, "forbidden")

	// A direct permission on the user (model_has_permissions) restores it.
	h.exec(`INSERT INTO model_has_permissions (permission_id, model_type, model_id)
		SELECT id, 'user', ? FROM permissions WHERE name = 'charges.refund'`, u.ID)
	expectStatus(t, c.post("/api/v1/charges/"+itoa(chargeID)+"/refund", map[string]any{"amount": 100}), http.StatusOK)

	// Unlinking the merchant from the account hides the charge.
	h.exec(`DELETE FROM account_user WHERE account_id = ? AND user_id = ?`, account, u.ID)
	expectError(t, c.get("/api/v1/charges/"+itoa(chargeID)), http.StatusNotFound, "not_found")

	// Removing the role removes the remaining permissions.
	h.exec(`DELETE FROM model_has_roles WHERE model_type = 'user' AND model_id = ?`, u.ID)
	expectError(t, c.get("/api/v1/charges"), http.StatusForbidden, "forbidden")
}
