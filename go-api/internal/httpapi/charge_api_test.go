package httpapi

import (
	"fmt"
	"net/http"
	"slices"
	"sync"
	"testing"
)

type merchantFixture struct {
	h       *harness
	client  *client
	account int64
}

func newMerchant(t *testing.T) merchantFixture {
	h := newHarness(t)
	u := h.user("merchant")
	account := h.account("active", true)
	h.linkMerchant(account, u.ID)
	return merchantFixture{h: h, client: h.login(u), account: account}
}

func (m merchantFixture) create(amount int64, capture bool) map[string]any {
	m.h.t.Helper()
	res := m.client.post("/api/v1/charges", map[string]any{
		"account_id": m.account, "amount": amount, "currency": "eur",
		"customer_email": "Buyer@Example.com", "capture": capture,
	})
	expectStatus(m.h.t, res, http.StatusCreated)
	return res.data()
}

func TestChargeLifecycle(t *testing.T) {
	m := newMerchant(t)
	c := m.client

	captured := m.create(10_000, true)
	if captured["status"] != "captured" || captured["amount_captured"].(float64) != 10_000 ||
		captured["currency"] != "EUR" || captured["customer_email"] != "buyer@example.com" {
		t.Fatalf("unexpected charge %v", captured)
	}
	path := "/api/v1/charges/" + itoa(idOf(captured))

	partial := c.post(path+"/refund", map[string]any{"amount": 2_500})
	expectStatus(t, partial, http.StatusOK)
	if d := partial.data(); d["status"] != "captured" || d["amount_refunded"].(float64) != 2_500 || d["amount_refundable"].(float64) != 7_500 || d["refunded"] != false {
		t.Fatalf("unexpected partial refund %v", d)
	}

	expectError(t, c.post(path+"/refund", map[string]any{"amount": 7_501}), http.StatusUnprocessableEntity, "refund_exceeds_available")
	expectError(t, c.post(path+"/void", nil), http.StatusConflict, "charge_not_voidable")
	expectError(t, c.post(path+"/capture", nil), http.StatusConflict, "charge_not_capturable")

	rest := c.post(path+"/refund", nil)
	expectStatus(t, rest, http.StatusOK)
	if d := rest.data(); d["status"] != "refunded" || d["amount_refunded"].(float64) != 10_000 || d["refunded"] != true || len(d["actions"].([]any)) != 0 {
		t.Fatalf("unexpected full refund %v", d)
	}
	expectError(t, c.post(path+"/refund", map[string]any{"amount": 1}), http.StatusConflict, "charge_already_refunded")

	authorized := m.create(4_000, false)
	if authorized["status"] != "authorized" || !slices.Equal(toStrings(authorized["actions"]), []string{"capture", "void"}) {
		t.Fatalf("unexpected authorization %v", authorized)
	}
	authPath := "/api/v1/charges/" + itoa(idOf(authorized))
	expectError(t, c.post(authPath+"/refund", nil), http.StatusConflict, "charge_not_refundable")

	voided := c.post(authPath+"/void", map[string]any{"reason": "duplicate"})
	expectStatus(t, voided, http.StatusOK)
	if d := voided.data(); d["status"] != "voided" || d["amount_voided"].(float64) != 4_000 || d["void_reason"] != "duplicate" {
		t.Fatalf("unexpected void %v", d)
	}
	expectError(t, c.post(authPath+"/void", nil), http.StatusConflict, "charge_not_voidable")

	toCapture := m.create(1_500, false)
	capturedLater := c.post("/api/v1/charges/"+itoa(idOf(toCapture))+"/capture", nil)
	expectStatus(t, capturedLater, http.StatusOK)
	if d := capturedLater.data(); d["status"] != "captured" || d["amount_captured"].(float64) != 1_500 {
		t.Fatalf("unexpected capture %v", d)
	}
}

func TestChargeRulesAgainstStoredState(t *testing.T) {
	m := newMerchant(t)

	disputed := m.h.charge(m.account, chargeRow{Status: "captured", Amount: 2_000, Captured: 2_000, Disputed: true})
	expectError(t, m.client.post("/api/v1/charges/"+itoa(disputed)+"/refund", nil), http.StatusConflict, "charge_disputed")

	failed := m.h.charge(m.account, chargeRow{Status: "failed", Amount: 2_000})
	expectError(t, m.client.post("/api/v1/charges/"+itoa(failed)+"/refund", nil), http.StatusConflict, "charge_not_refundable")
	expectError(t, m.client.post("/api/v1/charges/"+itoa(failed)+"/void", nil), http.StatusConflict, "charge_not_voidable")

	suspended := m.h.account("suspended", false)
	u := m.h.user("merchant")
	m.h.linkMerchant(suspended, u.ID)
	expectError(t, m.h.login(u).post("/api/v1/charges", map[string]any{
		"account_id": suspended, "amount": 100, "currency": "USD", "customer_email": "x@example.com",
	}), http.StatusConflict, "account_inactive")
}

func TestRequestValidation(t *testing.T) {
	m := newMerchant(t)
	c := m.client
	valid := map[string]any{"account_id": m.account, "amount": 100, "currency": "USD", "customer_email": "x@example.com"}
	with := func(k string, v any) map[string]any {
		out := map[string]any{}
		for key, val := range valid {
			out[key] = val
		}
		out[k] = v
		return out
	}

	for name, body := range map[string]any{
		"fractional amount":  with("amount", 12.5),
		"amount as string":   with("amount", "100"),
		"zero amount":        with("amount", 0),
		"negative amount":    with("amount", -5),
		"amount above max":   with("amount", 100_000_000),
		"unknown currency":   with("currency", "JPY"),
		"invalid email":      with("customer_email", "not-an-email"),
		"missing account":    with("account_id", nil),
		"capture not a bool": with("capture", "yes"),
	} {
		t.Run(name, func(t *testing.T) {
			expectError(t, c.post("/api/v1/charges", body), http.StatusUnprocessableEntity, "validation_failed")
		})
	}

	expectError(t, c.post("/api/v1/charges", with("surprise", 1)), http.StatusBadRequest, "invalid_json")
	expectError(t, c.post("/api/v1/charges", `{"amount":`), http.StatusBadRequest, "invalid_json")
	expectError(t, c.get("/api/v1/charges?sort=password"), http.StatusUnprocessableEntity, "validation_failed")
	expectError(t, c.get("/api/v1/charges/abc"), http.StatusNotFound, "not_found")
	expectError(t, c.get("/api/v1/charges/-1"), http.StatusNotFound, "not_found")

	charge := m.create(1_000, true)
	path := "/api/v1/charges/" + itoa(idOf(charge))
	expectError(t, c.post(path+"/refund", map[string]any{"amount": 0}), http.StatusUnprocessableEntity, "validation_failed")
	expectError(t, c.post(path+"/refund", map[string]any{"amount": 1.5}), http.StatusUnprocessableEntity, "validation_failed")
	expectError(t, c.post(path+"/void", map[string]any{"reason": "boredom"}), http.StatusUnprocessableEntity, "validation_failed")
}

// Ten refunds of 10.00 race on a 50.00 charge: exactly five may succeed and
// the stored total must never exceed the captured amount.
func TestConcurrentRefundsNeverOverRefund(t *testing.T) {
	m := newMerchant(t)
	charge := m.create(5_000, true)
	path := "/api/v1/charges/" + itoa(idOf(charge)) + "/refund"

	const attempts = 10
	statuses := make([]int, attempts)
	var wg sync.WaitGroup
	for i := range attempts {
		wg.Add(1)
		go func() {
			defer wg.Done()
			statuses[i] = m.client.post(path, map[string]any{"amount": 1_000}).status
		}()
	}
	wg.Wait()

	ok := 0
	for _, status := range statuses {
		switch status {
		case http.StatusOK:
			ok++
		case http.StatusConflict, http.StatusUnprocessableEntity:
		default:
			t.Fatalf("unexpected status %d in %v", status, statuses)
		}
	}
	if ok != 5 {
		t.Fatalf("%d refunds succeeded, want 5 (%v)", ok, statuses)
	}
	if status, refunded := m.h.chargeState(idOf(charge)); status != "refunded" || refunded != 5_000 {
		t.Fatalf("charge ended as %s with %d refunded", status, refunded)
	}
}

func TestListPaginatesSortsAndFilters(t *testing.T) {
	m := newMerchant(t)
	for i := 1; i <= 23; i++ {
		m.h.charge(m.account, chargeRow{Status: "captured", Amount: int64(i * 100), Captured: int64(i * 100)})
	}
	m.h.charge(m.account, chargeRow{Status: "voided", Amount: 9_999})

	page := m.client.get("/api/v1/charges?page=3&page_size=10&sort=amount")
	expectStatus(t, page, http.StatusOK)
	meta := page.body["meta"].(map[string]any)
	if meta["total"].(float64) != 24 || meta["page"].(float64) != 3 || meta["page_size"].(float64) != 10 || len(page.list()) != 4 {
		t.Fatalf("unexpected page: %v with %d items", meta, len(page.list()))
	}
	var amounts []float64
	for _, item := range page.list() {
		amounts = append(amounts, item.(map[string]any)["amount"].(float64))
	}
	if !slices.Equal(amounts, []float64{2_100, 2_200, 2_300, 9_999}) {
		t.Fatalf("unexpected order %v", amounts)
	}

	voided := m.client.get("/api/v1/charges?status=voided")
	if len(voided.list()) != 1 || voided.list()[0].(map[string]any)["status"] != "voided" {
		t.Fatalf("status filter returned %s", voided.raw)
	}

	first := page.list()[0].(map[string]any)
	byEmail := m.client.get("/api/v1/charges?q=" + first["customer_email"].(string)[:12])
	if len(byEmail.list()) != 1 {
		t.Fatalf("email prefix search returned %d rows", len(byEmail.list()))
	}
	byTxn := m.client.get("/api/v1/charges?q=" + first["external_transaction_id"].(string))
	if len(byTxn.list()) != 1 || idOf(byTxn.list()[0]) != idOf(first) {
		t.Fatalf("transaction search returned %s", byTxn.raw)
	}

	// LIKE wildcards are escaped, so "%" matches only a literal percent sign.
	if wild := m.client.get("/api/v1/charges?q=%25"); len(wild.list()) != 0 {
		t.Fatalf("wildcard search matched %d rows", len(wild.list()))
	}
}

func toStrings(v any) []string {
	out := []string{}
	for _, s := range v.([]any) {
		out = append(out, fmt.Sprint(s))
	}
	return out
}
