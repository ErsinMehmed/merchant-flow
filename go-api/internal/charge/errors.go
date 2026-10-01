package charge

import (
	"errors"
	"fmt"
)

// RuleError is a charge operation refused by a business rule. Code is stable
// and shared with the PHP implementation; the HTTP layer maps it to a status.
type RuleError struct {
	Code    string
	Message string
}

func (e *RuleError) Error() string {
	return e.Message
}

// ErrNotFound covers both missing charges and charges outside the caller's
// scope, deliberately indistinguishable.
var ErrNotFound = errors.New("not found")

const (
	CodeInvalidAmount          = "invalid_amount"
	CodeAccountInactive        = "account_inactive"
	CodeNotCapturable          = "charge_not_capturable"
	CodeNotVoidable            = "charge_not_voidable"
	CodeNotRefundable          = "charge_not_refundable"
	CodeAlreadyRefunded        = "charge_already_refunded"
	CodeDisputed               = "charge_disputed"
	CodeRefundExceedsAvailable = "refund_exceeds_available"
)

var (
	errInvalidAmount = &RuleError{CodeInvalidAmount,
		fmt.Sprintf("The amount must be between 1 and %d minor units.", MaxAmount)}
	errAccountInactive        = &RuleError{CodeAccountInactive, "The merchant account cannot accept new charges."}
	errAlreadyRefunded        = &RuleError{CodeAlreadyRefunded, "The charge has already been fully refunded."}
	errDisputed               = &RuleError{CodeDisputed, "A disputed charge cannot be refunded."}
	errRefundExceedsAvailable = &RuleError{CodeRefundExceedsAvailable, "The refund exceeds the amount left to refund."}
)

func notCapturable(s Status) error {
	return &RuleError{CodeNotCapturable, fmt.Sprintf("A %s charge cannot be captured.", s)}
}

func notVoidable(s Status) error {
	return &RuleError{CodeNotVoidable, fmt.Sprintf("A %s charge cannot be voided; only authorized charges can.", s)}
}

func notRefundable(s Status) error {
	return &RuleError{CodeNotRefundable, fmt.Sprintf("A %s charge cannot be refunded; only captured charges can.", s)}
}
