package httpapi

import (
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"
)

func TestCrossSiteDetection(t *testing.T) {
	cases := []struct {
		name    string
		headers map[string]string
		want    bool
	}{
		{"no browser headers", nil, false},
		{"same origin", map[string]string{"Sec-Fetch-Site": "same-origin", "Origin": "http://localhost:8080"}, false},
		{"typed URL", map[string]string{"Sec-Fetch-Site": "none"}, false},
		{"cross site", map[string]string{"Sec-Fetch-Site": "cross-site"}, true},
		{"same site, other origin", map[string]string{"Sec-Fetch-Site": "same-site"}, true},
		{"foreign origin", map[string]string{"Origin": "https://evil.example"}, true},
		{"other port", map[string]string{"Origin": "http://localhost:9999"}, true},
	}
	for _, tc := range cases {
		t.Run(tc.name, func(t *testing.T) {
			r := httptest.NewRequest(http.MethodPost, "http://localhost:8080/api/v1/charges", nil)
			for k, v := range tc.headers {
				r.Header.Set(k, v)
			}
			if got := crossSite(r); got != tc.want {
				t.Fatalf("crossSite = %v, want %v", got, tc.want)
			}
		})
	}
}

func TestRequireJSONRejectsOtherBodies(t *testing.T) {
	h := requireJSON(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(http.StatusNoContent)
	}))

	for contentType, want := range map[string]int{
		"application/json":                  http.StatusNoContent,
		"application/json; charset=utf-8":   http.StatusNoContent,
		"application/x-www-form-urlencoded": http.StatusUnsupportedMediaType,
		"text/plain":                        http.StatusUnsupportedMediaType,
		"":                                  http.StatusUnsupportedMediaType,
	} {
		r := httptest.NewRequest(http.MethodPost, "/api/v1/charges", strings.NewReader(`{}`))
		if contentType != "" {
			r.Header.Set("Content-Type", contentType)
		}
		w := httptest.NewRecorder()
		h.ServeHTTP(w, r)
		if w.Code != want {
			t.Errorf("%q: status %d, want %d", contentType, w.Code, want)
		}
	}
}

func TestDecodeIsStrict(t *testing.T) {
	cases := map[string]int{
		`{"amount": 100}`:             0,
		``:                            0,
		`{"amount": 12.5}`:            http.StatusUnprocessableEntity,
		`{"amount": "100"}`:           http.StatusUnprocessableEntity,
		`{"amount": 1e30}`:            http.StatusUnprocessableEntity,
		`{"amount": 100, "x": 1}`:     http.StatusBadRequest,
		`{"amount": 100}{"amount":1}`: http.StatusBadRequest,
		`{"amount": 100} null`:        http.StatusBadRequest,
		`null`:                        http.StatusBadRequest,
		`true`:                        http.StatusBadRequest,
		`[1]`:                         http.StatusBadRequest,
		`{"amount":`:                  http.StatusBadRequest,
	}
	for body, wantStatus := range cases {
		var dst refundRequest
		w := httptest.NewRecorder()
		ok := decode(w, []byte(body), &dst)
		switch {
		case wantStatus == 0 && !ok:
			t.Errorf("%q: rejected with %d", body, w.Code)
		case wantStatus != 0 && (ok || w.Code != wantStatus):
			t.Errorf("%q: ok=%v status=%d, want %d", body, ok, w.Code, wantStatus)
		}
	}
}

func TestCreateChargeValidation(t *testing.T) {
	amount, account := int64(1500), int64(3)
	currency, email := "usd", " Jane@Example.com "
	in, errs := createChargeRequest{AccountID: &account, Amount: &amount, Currency: &currency, CustomerEmail: &email}.validate()
	if len(errs) != 0 {
		t.Fatalf("unexpected errors %v", errs)
	}
	if in.Currency != "USD" || in.CustomerEmail != "jane@example.com" || !in.Capture {
		t.Fatalf("unexpected normalisation %+v", in)
	}

	zero, tooBig := int64(0), int64(100_000_000)
	bad, badEmail := "JPY", "Jane <jane@example.com>"
	_, errs = createChargeRequest{Amount: &zero, Currency: &bad, CustomerEmail: &badEmail}.validate()
	for _, field := range []string{"account_id", "amount", "currency", "customer_email"} {
		if errs[field] == "" {
			t.Errorf("expected an error for %s", field)
		}
	}
	if _, errs = (createChargeRequest{Amount: &tooBig}).validate(); errs["amount"] == "" {
		t.Error("expected the amount above the maximum to be rejected")
	}
}
