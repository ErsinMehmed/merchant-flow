package charge

import (
	"net/url"
	"reflect"
	"testing"
	"time"
)

func TestParseListParamsDefaults(t *testing.T) {
	p, errs := ParseListParams(url.Values{})
	if len(errs) != 0 {
		t.Fatalf("unexpected errors: %v", errs)
	}
	if p.Page != 1 || p.PageSize != DefaultPageSize || p.SortColumn != "c.created_at" || !p.SortDesc {
		t.Fatalf("unexpected defaults: %+v", p)
	}
	if where, args := p.where(); where != "1 = 1" || args != nil {
		t.Fatalf("unexpected filter %q %v", where, args)
	}
}

func TestParseListParamsFilters(t *testing.T) {
	q := url.Values{
		"page":         {"3"},
		"page_size":    {"50"},
		"sort":         {"amount"},
		"status":       {"captured,refunded,captured"},
		"account_id":   {"12"},
		"currency":     {"eur"},
		"q":            {"Jane_"},
		"disputed":     {"false"},
		"created_from": {"2026-09-01"},
		"created_to":   {"2026-09-30"},
	}

	p, errs := ParseListParams(q)
	if len(errs) != 0 {
		t.Fatalf("unexpected errors: %v", errs)
	}
	if p.Page != 3 || p.PageSize != 50 || p.SortColumn != "c.amount" || p.SortDesc {
		t.Fatalf("unexpected paging/sort: %+v", p)
	}

	where, args := p.where()
	wantWhere := "c.status IN (?, ?) AND c.account_id = ? AND c.currency = ? AND " +
		"(c.customer_email LIKE ? OR c.external_transaction_id = ?) AND c.disputed = ? AND " +
		"c.created_at >= ? AND c.created_at < ?"
	if where != wantWhere {
		t.Fatalf("where = %q", where)
	}
	wantArgs := []any{
		"captured", "refunded", int64(12), "EUR", `jane\_%`, "Jane_", false,
		time.Date(2026, 9, 1, 0, 0, 0, 0, time.UTC),
		time.Date(2026, 10, 1, 0, 0, 0, 0, time.UTC), // end date is inclusive
	}
	if !reflect.DeepEqual(args, wantArgs) {
		t.Fatalf("args = %#v", args)
	}
}

func TestParseListParamsRejectsInvalidValues(t *testing.T) {
	cases := map[string]url.Values{
		"page":       {"page": {"0"}},
		"page_size":  {"page_size": {"101"}},
		"sort":       {"sort": {"password"}},
		"status":     {"status": {"captured,pending"}},
		"account_id": {"account_id": {"-1"}},
		"currency":   {"currency": {"JPY"}},
		"disputed":   {"disputed": {"maybe"}},
		"created_to": {"created_from": {"2026-09-10"}, "created_to": {"2026-09-01"}},
	}
	for field, q := range cases {
		t.Run(field, func(t *testing.T) {
			_, errs := ParseListParams(q)
			if _, ok := errs[field]; !ok {
				t.Fatalf("expected an error for %s, got %v", field, errs)
			}
		})
	}
}

func TestParseListParamsRejectsDuplicateKnownParameters(t *testing.T) {
	for _, field := range []string{"page", "page_size", "sort", "status", "account_id", "currency", "q", "disputed", "created_from", "created_to"} {
		t.Run(field, func(t *testing.T) {
			_, errs := ParseListParams(url.Values{field: {"", "1"}})
			if errs[field] != "must be specified only once" {
				t.Fatalf("expected duplicate %s to be rejected, got %v", field, errs)
			}
		})
	}
}

func TestSortFieldsCannotInjectSQL(t *testing.T) {
	_, errs := ParseListParams(url.Values{"sort": {"created_at; DROP TABLE charges"}})
	if errs["sort"] == "" {
		t.Fatal("expected the sort field to be rejected")
	}
}
