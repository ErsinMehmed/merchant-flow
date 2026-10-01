package charge

import (
	"context"
	"database/sql"
	"errors"
	"time"

	"github.com/ErsinMehmed/merchant-flow/go-api/internal/access"
	"github.com/ErsinMehmed/merchant-flow/go-api/internal/database"
)

// ErrForbidden means the caller lacks the permission for the operation. It
// is checked before the charge is looked up, so it reveals nothing about
// whether a particular charge exists.
var ErrForbidden = errors.New("forbidden")

// Service applies permissions, tenant scope and the charge rules.
//
// Write methods take a Querier so the caller decides the transaction: the
// HTTP layer runs each write in one transaction together with its
// idempotency record.
type Service struct {
	db  *sql.DB
	now func() time.Time
}

func NewService(db *sql.DB) *Service {
	return &Service{db: db, now: func() time.Time { return time.Now().UTC().Truncate(time.Second) }}
}

type Page struct {
	Charges  []Charge
	Total    int
	Page     int
	PageSize int
}

func (s *Service) List(ctx context.Context, p access.Principal, params ListParams) (Page, error) {
	if !p.Can(access.ViewCharges) {
		return Page{}, ErrForbidden
	}
	charges, total, err := list(ctx, s.db, p.Scope(), params)
	if err != nil {
		return Page{}, err
	}
	return Page{Charges: charges, Total: total, Page: params.Page, PageSize: params.PageSize}, nil
}

func (s *Service) Get(ctx context.Context, p access.Principal, id int64) (Charge, error) {
	if !p.Can(access.ViewCharges) {
		return Charge{}, ErrForbidden
	}
	return find(ctx, s.db, p.Scope(), id, false)
}

// Accounts lists the merchant accounts in the caller's scope, for filters
// and for choosing where to book a new charge.
func (s *Service) Accounts(ctx context.Context, p access.Principal) ([]Account, error) {
	if !p.Can(access.ViewCharges) && !p.Can(access.CreateCharges) {
		return nil, ErrForbidden
	}
	return listAccounts(ctx, s.db, p.Scope())
}

type NewCharge struct {
	AccountID     int64
	Amount        int64
	Currency      string
	CustomerEmail string
	Capture       bool
}

func (s *Service) Create(ctx context.Context, q database.Querier, p access.Principal, in NewCharge) (Charge, error) {
	if !p.Can(access.CreateCharges) {
		return Charge{}, ErrForbidden
	}
	account, err := findAccount(ctx, q, p.Scope(), in.AccountID)
	if err != nil {
		return Charge{}, err
	}
	c, err := New(account, in.Amount, in.Currency, in.CustomerEmail, in.Capture, p.UserID, s.now())
	if err != nil {
		return Charge{}, err
	}
	if err := insert(ctx, q, &c); err != nil {
		return Charge{}, err
	}
	return c, nil
}

func (s *Service) Capture(ctx context.Context, q database.Querier, p access.Principal, id int64) (Charge, error) {
	return s.change(ctx, q, p, access.CaptureCharges, id, (*Charge).Capture)
}

func (s *Service) Refund(ctx context.Context, q database.Querier, p access.Principal, id int64, amount *int64) (Charge, error) {
	return s.change(ctx, q, p, access.RefundCharges, id, func(c *Charge) error { return c.Refund(amount) })
}

func (s *Service) Void(ctx context.Context, q database.Querier, p access.Principal, id int64, reason string) (Charge, error) {
	return s.change(ctx, q, p, access.VoidCharges, id, func(c *Charge) error { return c.Void(reason) })
}

// change locks the charge, applies the operation to the current row and
// saves it. The lock makes concurrent operations on one charge run one after
// another, each seeing the amounts the previous one left. q must be a
// transaction for the lock to mean anything.
func (s *Service) change(ctx context.Context, q database.Querier, p access.Principal, permission string, id int64, apply func(*Charge) error) (Charge, error) {
	if !p.Can(permission) {
		return Charge{}, ErrForbidden
	}
	c, err := find(ctx, q, p.Scope(), id, true)
	if err != nil {
		return Charge{}, err
	}
	if err := apply(&c); err != nil {
		return Charge{}, err
	}
	c.UpdatedAt = s.now()
	if err := update(ctx, q, c); err != nil {
		return Charge{}, err
	}
	return c, nil
}

// Actions lists the operations the principal could perform on the charge
// right now, so the portal shows only buttons that will work.
func (c Charge) Actions(p access.Principal) []string {
	actions := []string{}
	if c.Status == StatusAuthorized && p.Can(access.CaptureCharges) {
		actions = append(actions, "capture")
	}
	if c.Status == StatusAuthorized && p.Can(access.VoidCharges) {
		actions = append(actions, "void")
	}
	if c.Refundable() > 0 && !c.Disputed && p.Can(access.RefundCharges) {
		actions = append(actions, "refund")
	}
	return actions
}
