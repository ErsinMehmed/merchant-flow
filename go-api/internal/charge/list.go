package charge

import (
	"net/url"
	"slices"
	"strconv"
	"strings"
	"time"
)

const (
	DefaultPageSize = 25
	MaxPageSize     = 100
	// maxPage bounds OFFSET; deeper pages should be reached by filtering.
	maxPage     = 10_000
	maxQueryLen = 100
)

// sortColumns is the allowlist of sortable fields. The values are written
// into SQL, so they must never come from the request.
var sortColumns = map[string]string{
	"id":             "c.id",
	"created_at":     "c.created_at",
	"amount":         "c.amount",
	"status":         "c.status",
	"customer_email": "c.customer_email",
	"account":        "a.business_name",
}

type ListParams struct {
	Page       int
	PageSize   int
	SortColumn string
	SortDesc   bool
	Statuses   []Status
	AccountID  int64
	Currency   string
	// Query matches a customer email prefix or an exact transaction ID.
	Query    string
	Disputed *bool
	// CreatedFrom and CreatedTo are whole UTC days, both inclusive.
	CreatedFrom *time.Time
	CreatedTo   *time.Time
}

// ParseListParams validates the list query string. Unknown parameters are
// ignored; invalid values are reported per field.
func ParseListParams(q url.Values) (ListParams, map[string]string) {
	p := ListParams{Page: 1, PageSize: DefaultPageSize, SortColumn: sortColumns["created_at"], SortDesc: true}
	errs := map[string]string{}

	if v := q.Get("page"); v != "" {
		n, err := strconv.Atoi(v)
		if err != nil || n < 1 || n > maxPage {
			errs["page"] = "must be a whole number between 1 and " + strconv.Itoa(maxPage)
		} else {
			p.Page = n
		}
	}

	if v := q.Get("page_size"); v != "" {
		n, err := strconv.Atoi(v)
		if err != nil || n < 1 || n > MaxPageSize {
			errs["page_size"] = "must be a whole number between 1 and " + strconv.Itoa(MaxPageSize)
		} else {
			p.PageSize = n
		}
	}

	if v := q.Get("sort"); v != "" {
		field, desc := strings.CutPrefix(v, "-")
		column, ok := sortColumns[field]
		if !ok {
			errs["sort"] = "unsupported sort field"
		} else {
			p.SortColumn, p.SortDesc = column, desc
		}
	}

	if v := q.Get("status"); v != "" {
		for _, s := range strings.Split(v, ",") {
			status := Status(strings.TrimSpace(s))
			if !slices.Contains([]Status{StatusAuthorized, StatusCaptured, StatusRefunded, StatusVoided, StatusFailed}, status) {
				errs["status"] = "unknown status " + strconv.Quote(string(status))
				break
			}
			if !slices.Contains(p.Statuses, status) {
				p.Statuses = append(p.Statuses, status)
			}
		}
	}

	if v := q.Get("account_id"); v != "" {
		n, err := strconv.ParseInt(v, 10, 64)
		if err != nil || n < 1 {
			errs["account_id"] = "must be a positive whole number"
		} else {
			p.AccountID = n
		}
	}

	if v := q.Get("currency"); v != "" {
		currency := strings.ToUpper(v)
		if !slices.Contains(Currencies, currency) {
			errs["currency"] = "unsupported currency"
		} else {
			p.Currency = currency
		}
	}

	if v := strings.TrimSpace(q.Get("q")); v != "" {
		if len(v) > maxQueryLen {
			errs["q"] = "must be at most " + strconv.Itoa(maxQueryLen) + " characters"
		} else {
			p.Query = v
		}
	}

	if v := q.Get("disputed"); v != "" {
		b, err := strconv.ParseBool(v)
		if err != nil {
			errs["disputed"] = "must be true or false"
		} else {
			p.Disputed = &b
		}
	}

	p.CreatedFrom = parseDay(q.Get("created_from"), "created_from", errs)
	p.CreatedTo = parseDay(q.Get("created_to"), "created_to", errs)
	if p.CreatedFrom != nil && p.CreatedTo != nil && p.CreatedTo.Before(*p.CreatedFrom) {
		errs["created_to"] = "must not be before created_from"
	}
	for _, field := range []string{"page", "page_size", "sort", "status", "account_id", "currency", "q", "disputed", "created_from", "created_to"} {
		if len(q[field]) > 1 {
			errs[field] = "must be specified only once"
		}
	}

	return p, errs
}

func parseDay(v, field string, errs map[string]string) *time.Time {
	if v == "" {
		return nil
	}
	t, err := time.ParseInLocation(time.DateOnly, v, time.UTC)
	if err != nil {
		errs[field] = "must be a date in YYYY-MM-DD format"
		return nil
	}
	return &t
}

// where builds the filter part of the list query. Values are bound as
// arguments; only fixed fragments are concatenated.
func (p ListParams) where() (string, []any) {
	var (
		clauses []string
		args    []any
	)

	if len(p.Statuses) > 0 {
		clauses = append(clauses, "c.status IN (?"+strings.Repeat(", ?", len(p.Statuses)-1)+")")
		for _, s := range p.Statuses {
			args = append(args, string(s))
		}
	}
	if p.AccountID != 0 {
		clauses = append(clauses, "c.account_id = ?")
		args = append(args, p.AccountID)
	}
	if p.Currency != "" {
		clauses = append(clauses, "c.currency = ?")
		args = append(args, p.Currency)
	}
	if p.Query != "" {
		clauses = append(clauses, "(c.customer_email LIKE ? OR c.external_transaction_id = ?)")
		args = append(args, escapeLike(strings.ToLower(p.Query))+"%", p.Query)
	}
	if p.Disputed != nil {
		clauses = append(clauses, "c.disputed = ?")
		args = append(args, *p.Disputed)
	}
	if p.CreatedFrom != nil {
		clauses = append(clauses, "c.created_at >= ?")
		args = append(args, *p.CreatedFrom)
	}
	if p.CreatedTo != nil {
		clauses = append(clauses, "c.created_at < ?")
		args = append(args, p.CreatedTo.AddDate(0, 0, 1))
	}

	if len(clauses) == 0 {
		return "1 = 1", nil
	}
	return strings.Join(clauses, " AND "), args
}

func escapeLike(s string) string {
	return strings.NewReplacer(`\`, `\\`, `%`, `\%`, `_`, `\_`).Replace(s)
}
