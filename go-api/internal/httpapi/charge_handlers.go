package httpapi

import (
	"database/sql"
	"net/http"
	"net/mail"
	"slices"
	"strconv"
	"strings"
	"time"

	"github.com/ErsinMehmed/merchant-flow/go-api/internal/access"
	"github.com/ErsinMehmed/merchant-flow/go-api/internal/charge"
)

type chargeJSON struct {
	ID                    int64     `json:"id"`
	AccountID             int64     `json:"account_id"`
	AccountName           string    `json:"account_name"`
	AccountMID            string    `json:"account_mid"`
	Amount                int64     `json:"amount"`
	AmountCaptured        int64     `json:"amount_captured"`
	AmountRefunded        int64     `json:"amount_refunded"`
	AmountVoided          int64     `json:"amount_voided"`
	AmountRefundable      int64     `json:"amount_refundable"`
	Currency              string    `json:"currency"`
	Status                string    `json:"status"`
	ExternalTransactionID string    `json:"external_transaction_id"`
	CustomerEmail         string    `json:"customer_email"`
	Refunded              bool      `json:"refunded"`
	Disputed              bool      `json:"disputed"`
	VoidReason            *string   `json:"void_reason"`
	CreatedBy             int64     `json:"created_by"`
	CreatedAt             time.Time `json:"created_at"`
	UpdatedAt             time.Time `json:"updated_at"`
	// Actions the caller can perform now: "capture", "void", "refund".
	Actions []string `json:"actions"`
}

func toChargeJSON(c charge.Charge, p access.Principal) chargeJSON {
	return chargeJSON{
		ID:                    c.ID,
		AccountID:             c.AccountID,
		AccountName:           c.AccountName,
		AccountMID:            c.AccountMID,
		Amount:                c.Amount,
		AmountCaptured:        c.AmountCaptured,
		AmountRefunded:        c.AmountRefunded,
		AmountVoided:          c.AmountVoided,
		AmountRefundable:      c.Refundable(),
		Currency:              c.Currency,
		Status:                string(c.Status),
		ExternalTransactionID: c.ExternalTransactionID,
		CustomerEmail:         c.CustomerEmail,
		Refunded:              c.Refunded,
		Disputed:              c.Disputed,
		VoidReason:            c.VoidReason,
		CreatedBy:             c.CreatedBy,
		CreatedAt:             c.CreatedAt,
		UpdatedAt:             c.UpdatedAt,
		Actions:               c.Actions(p),
	}
}

type accountJSON struct {
	ID             int64  `json:"id"`
	BusinessName   string `json:"business_name"`
	MID            string `json:"mid"`
	Status         string `json:"status"`
	AcceptsCharges bool   `json:"accepts_charges"`
}

type pageMeta struct {
	Page     int `json:"page"`
	PageSize int `json:"page_size"`
	Total    int `json:"total"`
}

func (s *Server) listAccounts(w http.ResponseWriter, r *http.Request) {
	accounts, err := s.charges.Accounts(r.Context(), principalFrom(r.Context()))
	if err != nil {
		writeServiceError(w, r, err)
		return
	}

	data := make([]accountJSON, 0, len(accounts))
	for _, a := range accounts {
		data = append(data, accountJSON{ID: a.ID, BusinessName: a.Name, MID: a.MID, Status: a.Status, AcceptsCharges: a.AcceptsNewCharges()})
	}
	writeJSON(w, http.StatusOK, dataBody{Data: data})
}

func (s *Server) listCharges(w http.ResponseWriter, r *http.Request) {
	params, fieldErrs := charge.ParseListParams(r.URL.Query())
	if len(fieldErrs) > 0 {
		writeValidation(w, fieldErrs)
		return
	}

	p := principalFrom(r.Context())
	page, err := s.charges.List(r.Context(), p, params)
	if err != nil {
		writeServiceError(w, r, err)
		return
	}

	data := make([]chargeJSON, 0, len(page.Charges))
	for _, c := range page.Charges {
		data = append(data, toChargeJSON(c, p))
	}
	writeJSON(w, http.StatusOK, dataBody{
		Data: data,
		Meta: pageMeta{Page: page.Page, PageSize: page.PageSize, Total: page.Total},
	})
}

func (s *Server) getCharge(w http.ResponseWriter, r *http.Request) {
	id, ok := pathID(r)
	if !ok {
		writeNotFound(w)
		return
	}
	p := principalFrom(r.Context())
	c, err := s.charges.Get(r.Context(), p, id)
	if err != nil {
		writeServiceError(w, r, err)
		return
	}
	writeJSON(w, http.StatusOK, dataBody{Data: toChargeJSON(c, p)})
}

type createChargeRequest struct {
	AccountID     *int64  `json:"account_id"`
	Amount        *int64  `json:"amount"`
	Currency      *string `json:"currency"`
	CustomerEmail *string `json:"customer_email"`
	Capture       *bool   `json:"capture"`
}

func (req createChargeRequest) validate() (charge.NewCharge, map[string]string) {
	errs := map[string]string{}
	in := charge.NewCharge{Capture: true}

	if req.AccountID == nil || *req.AccountID < 1 {
		errs["account_id"] = "is required"
	} else {
		in.AccountID = *req.AccountID
	}

	switch {
	case req.Amount == nil:
		errs["amount"] = "is required"
	case *req.Amount < 1 || *req.Amount > charge.MaxAmount:
		errs["amount"] = "must be between 1 and " + strconv.FormatInt(charge.MaxAmount, 10) + " minor units"
	default:
		in.Amount = *req.Amount
	}

	if req.Currency == nil || !slices.Contains(charge.Currencies, strings.ToUpper(*req.Currency)) {
		errs["currency"] = "must be one of " + strings.Join(charge.Currencies, ", ")
	} else {
		in.Currency = strings.ToUpper(*req.Currency)
	}

	if req.CustomerEmail == nil || !validEmail(*req.CustomerEmail) {
		errs["customer_email"] = "must be a valid email address"
	} else {
		in.CustomerEmail = strings.ToLower(strings.TrimSpace(*req.CustomerEmail))
	}

	if req.Capture != nil {
		in.Capture = *req.Capture
	}

	return in, errs
}

func (s *Server) createCharge(w http.ResponseWriter, r *http.Request) {
	body, ok := readBody(w, r)
	if !ok {
		return
	}
	var req createChargeRequest
	if !decode(w, body, &req) {
		return
	}
	in, fieldErrs := req.validate()
	if len(fieldErrs) > 0 {
		writeValidation(w, fieldErrs)
		return
	}

	p := principalFrom(r.Context())
	s.mutate(w, r, body, func(tx *sql.Tx) (int, any, error) {
		c, err := s.charges.Create(r.Context(), tx, p, in)
		if err != nil {
			return 0, nil, err
		}
		return http.StatusCreated, dataBody{Data: toChargeJSON(c, p)}, nil
	})
}

func (s *Server) captureCharge(w http.ResponseWriter, r *http.Request) {
	id, ok := pathID(r)
	if !ok {
		writeNotFound(w)
		return
	}
	body, ok := readBody(w, r)
	if !ok {
		return
	}
	var req struct{}
	if !decode(w, body, &req) {
		return
	}

	p := principalFrom(r.Context())
	s.mutate(w, r, body, func(tx *sql.Tx) (int, any, error) {
		c, err := s.charges.Capture(r.Context(), tx, p, id)
		if err != nil {
			return 0, nil, err
		}
		return http.StatusOK, dataBody{Data: toChargeJSON(c, p)}, nil
	})
}

type refundRequest struct {
	// Minor units; omitted refunds everything that is left.
	Amount *int64 `json:"amount"`
}

func (s *Server) refundCharge(w http.ResponseWriter, r *http.Request) {
	id, ok := pathID(r)
	if !ok {
		writeNotFound(w)
		return
	}
	body, ok := readBody(w, r)
	if !ok {
		return
	}
	var req refundRequest
	if !decode(w, body, &req) {
		return
	}
	if req.Amount != nil && (*req.Amount < 1 || *req.Amount > charge.MaxAmount) {
		writeValidation(w, map[string]string{"amount": "must be between 1 and " + strconv.FormatInt(charge.MaxAmount, 10) + " minor units"})
		return
	}

	p := principalFrom(r.Context())
	s.mutate(w, r, body, func(tx *sql.Tx) (int, any, error) {
		c, err := s.charges.Refund(r.Context(), tx, p, id, req.Amount)
		if err != nil {
			return 0, nil, err
		}
		return http.StatusOK, dataBody{Data: toChargeJSON(c, p)}, nil
	})
}

type voidRequest struct {
	Reason *string `json:"reason"`
}

func (s *Server) voidCharge(w http.ResponseWriter, r *http.Request) {
	id, ok := pathID(r)
	if !ok {
		writeNotFound(w)
		return
	}
	body, ok := readBody(w, r)
	if !ok {
		return
	}
	var req voidRequest
	if !decode(w, body, &req) {
		return
	}
	reason := "requested_by_customer"
	if req.Reason != nil {
		if !slices.Contains(charge.VoidReasons, *req.Reason) {
			writeValidation(w, map[string]string{"reason": "must be one of " + strings.Join(charge.VoidReasons, ", ")})
			return
		}
		reason = *req.Reason
	}

	p := principalFrom(r.Context())
	s.mutate(w, r, body, func(tx *sql.Tx) (int, any, error) {
		c, err := s.charges.Void(r.Context(), tx, p, id, reason)
		if err != nil {
			return 0, nil, err
		}
		return http.StatusOK, dataBody{Data: toChargeJSON(c, p)}, nil
	})
}

// pathID parses the {id} segment. Anything that is not a positive integer
// cannot name a charge, so it is reported as not found.
func pathID(r *http.Request) (int64, bool) {
	id, err := strconv.ParseInt(r.PathValue("id"), 10, 64)
	return id, err == nil && id > 0
}

func validEmail(s string) bool {
	s = strings.TrimSpace(s)
	if s == "" || len(s) > 254 {
		return false
	}
	addr, err := mail.ParseAddress(s)
	return err == nil && addr.Address == s && addr.Name == ""
}
