package httpapi

import (
	"net/http"
	"sync"
	"testing"
)

func TestIdempotencyKeyReplaysInsteadOfRepeating(t *testing.T) {
	m := newMerchant(t)
	charge := m.create(5_000, true)
	path := "/api/v1/charges/" + itoa(idOf(charge)) + "/refund"
	key := map[string]string{"Idempotency-Key": "refund-" + unique()}

	first := m.client.do(http.MethodPost, path, map[string]any{"amount": 1_000}, key)
	second := m.client.do(http.MethodPost, path, map[string]any{"amount": 1_000}, key)

	expectStatus(t, first, http.StatusOK)
	expectStatus(t, second, http.StatusOK)
	if string(first.raw) != string(second.raw) || second.header.Get("Idempotent-Replayed") != "true" {
		t.Fatalf("expected an identical replay, got %s / %s", first.raw, second.raw)
	}
	if _, refunded := m.h.chargeState(idOf(charge)); refunded != 1_000 {
		t.Fatalf("refunded %d, want 1000", refunded)
	}

	reused := m.client.do(http.MethodPost, path, map[string]any{"amount": 2_000}, key)
	expectError(t, reused, http.StatusUnprocessableEntity, "idempotency_key_reused")
}

func TestIdempotentCreateUnderConcurrentRetries(t *testing.T) {
	m := newMerchant(t)
	key := map[string]string{"Idempotency-Key": "create-" + unique()}
	body := map[string]any{"account_id": m.account, "amount": 777, "currency": "USD", "customer_email": "x@example.com"}

	const attempts = 6
	responses := make([]response, attempts)
	var wg sync.WaitGroup
	for i := range attempts {
		wg.Add(1)
		go func() {
			defer wg.Done()
			responses[i] = m.client.do(http.MethodPost, "/api/v1/charges", body, key)
		}()
	}
	wg.Wait()

	for _, r := range responses {
		expectStatus(t, r, http.StatusCreated)
		if string(r.raw) != string(responses[0].raw) {
			t.Fatalf("responses differ: %s vs %s", r.raw, responses[0].raw)
		}
	}
	var count int
	if err := m.h.db.QueryRow(`SELECT COUNT(*) FROM charges WHERE account_id = ? AND amount = 777`, m.account).Scan(&count); err != nil || count != 1 {
		t.Fatalf("created %d charges, want 1 (%v)", count, err)
	}
}

func TestRejectedOperationsAreReplayedToo(t *testing.T) {
	m := newMerchant(t)
	charge := m.create(1_000, true)
	path := "/api/v1/charges/" + itoa(idOf(charge)) + "/refund"
	key := map[string]string{"Idempotency-Key": "too-much-" + unique()}

	expectError(t, m.client.do(http.MethodPost, path, map[string]any{"amount": 5_000}, key), http.StatusUnprocessableEntity, "refund_exceeds_available")
	replayed := m.client.do(http.MethodPost, path, map[string]any{"amount": 5_000}, key)
	expectError(t, replayed, http.StatusUnprocessableEntity, "refund_exceeds_available")
	if replayed.header.Get("Idempotent-Replayed") != "true" {
		t.Fatal("expected the rejection to be replayed")
	}

	expectError(t, m.client.do(http.MethodPost, path, nil, map[string]string{"Idempotency-Key": "bad key with spaces"}),
		http.StatusUnprocessableEntity, "validation_failed")
}

// Keys belong to a user: another user's identical key is a separate request.
func TestIdempotencyKeysAreScopedToTheUser(t *testing.T) {
	m := newMerchant(t)
	other := m.h.user("merchant")
	m.h.linkMerchant(m.account, other.ID)
	charge := m.create(5_000, true)
	path := "/api/v1/charges/" + itoa(idOf(charge)) + "/refund"
	key := map[string]string{"Idempotency-Key": "shared-" + unique()}

	expectStatus(t, m.client.do(http.MethodPost, path, map[string]any{"amount": 1_000}, key), http.StatusOK)
	second := m.h.login(other).do(http.MethodPost, path, map[string]any{"amount": 1_000}, key)
	expectStatus(t, second, http.StatusOK)
	if second.header.Get("Idempotent-Replayed") != "" {
		t.Fatal("another user's key must not be replayed")
	}
	if _, refunded := m.h.chargeState(idOf(charge)); refunded != 2_000 {
		t.Fatalf("refunded %d, want 2000", refunded)
	}
}
