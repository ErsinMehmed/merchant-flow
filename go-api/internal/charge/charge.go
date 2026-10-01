// Package charge holds the payment rules and their storage.
//
// The state transitions mirror the PHP Charge model; both are tested against
// contracts/charge-operations.json so the admin portal and this API cannot
// drift apart.
package charge

import (
	"crypto/rand"
	"encoding/hex"
	"time"
)

type Status string

const (
	StatusAuthorized Status = "authorized"
	StatusCaptured   Status = "captured"
	StatusRefunded   Status = "refunded"
	StatusVoided     Status = "voided"
	StatusFailed     Status = "failed"
)

// MaxAmount is 999,999.99 in a two-decimal currency.
const MaxAmount int64 = 99_999_999

// Currencies are limited to two-decimal ones, so a minor unit is always a cent.
var Currencies = []string{"USD", "EUR", "GBP", "CAD"}

var VoidReasons = []string{"requested_by_customer", "duplicate", "fraudulent", "abandoned"}

type Charge struct {
	ID                    int64
	AccountID             int64
	AccountName           string
	AccountMID            string
	Amount                int64
	AmountCaptured        int64
	AmountRefunded        int64
	AmountVoided          int64
	Currency              string
	Status                Status
	ExternalTransactionID string
	CustomerEmail         string
	Refunded              bool
	Disputed              bool
	VoidReason            *string
	CreatedBy             int64
	CreatedAt             time.Time
	UpdatedAt             time.Time
}

// Account is the part of a merchant account the charge rules need.
type Account struct {
	ID       int64
	Name     string
	MID      string
	Status   string
	ActiveYN bool
}

func (a Account) AcceptsNewCharges() bool {
	return a.Status == "active" && a.ActiveYN
}

// New builds a charge for an account: captured straight away or only
// authorized, mirroring Charge::initiate() in PHP.
func New(account Account, amount int64, currency, customerEmail string, capture bool, createdBy int64, now time.Time) (Charge, error) {
	if err := validAmount(amount); err != nil {
		return Charge{}, err
	}
	if !account.AcceptsNewCharges() {
		return Charge{}, errAccountInactive
	}

	c := Charge{
		AccountID:             account.ID,
		AccountName:           account.Name,
		AccountMID:            account.MID,
		Amount:                amount,
		Currency:              currency,
		Status:                StatusAuthorized,
		ExternalTransactionID: newTransactionID(),
		CustomerEmail:         customerEmail,
		CreatedBy:             createdBy,
		CreatedAt:             now,
		UpdatedAt:             now,
	}
	if capture {
		c.Status = StatusCaptured
		c.AmountCaptured = amount
	}
	return c, nil
}

func (c *Charge) Capture() error {
	if c.Status != StatusAuthorized {
		return notCapturable(c.Status)
	}
	c.AmountCaptured = c.Amount
	c.Status = StatusCaptured
	return nil
}

// Refund returns part or all of the captured amount; a nil amount refunds
// whatever is left. The charge becomes "refunded" once nothing is left.
func (c *Charge) Refund(amount *int64) error {
	if amount != nil {
		if err := validAmount(*amount); err != nil {
			return err
		}
	}
	switch {
	case c.Status == StatusRefunded:
		return errAlreadyRefunded
	case c.Status != StatusCaptured:
		return notRefundable(c.Status)
	case c.Disputed:
		return errDisputed
	}

	refund := c.Refundable()
	if amount != nil {
		refund = *amount
	}
	if refund > c.Refundable() {
		return errRefundExceedsAvailable
	}

	c.AmountRefunded += refund
	if c.AmountRefunded == c.AmountCaptured {
		c.Status = StatusRefunded
		c.Refunded = true
	}
	return nil
}

func (c *Charge) Void(reason string) error {
	if c.Status != StatusAuthorized {
		return notVoidable(c.Status)
	}
	c.AmountVoided = c.Amount
	c.Status = StatusVoided
	c.VoidReason = &reason
	return nil
}

// Refundable is the captured amount not yet refunded.
func (c *Charge) Refundable() int64 {
	if c.Status != StatusCaptured {
		return 0
	}
	return c.AmountCaptured - c.AmountRefunded
}

func validAmount(amount int64) error {
	if amount < 1 || amount > MaxAmount {
		return errInvalidAmount
	}
	return nil
}

func newTransactionID() string {
	b := make([]byte, 12)
	_, _ = rand.Read(b)
	return "txn_" + hex.EncodeToString(b)
}
