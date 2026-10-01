package charge

import (
	"encoding/json"
	"errors"
	"os"
	"path/filepath"
	"testing"
	"time"
)

// contractCase is one entry of contracts/charge-operations.json, the cases the
// PHP suite runs as well.
type contractCase struct {
	Name   string `json:"name"`
	Charge *struct {
		Status         Status `json:"status"`
		Amount         int64  `json:"amount"`
		AmountCaptured int64  `json:"amount_captured"`
		AmountRefunded int64  `json:"amount_refunded"`
		AmountVoided   int64  `json:"amount_voided"`
		Disputed       bool   `json:"disputed"`
	} `json:"charge"`
	Operation struct {
		Type    string `json:"type"`
		Amount  *int64 `json:"amount"`
		Capture bool   `json:"capture"`
		Reason  string `json:"reason"`
		Account struct {
			Status   string `json:"status"`
			ActiveYN bool   `json:"active_yn"`
		} `json:"account"`
	} `json:"operation"`
	Expect struct {
		Error          string  `json:"error"`
		Status         Status  `json:"status"`
		Amount         int64   `json:"amount"`
		AmountCaptured int64   `json:"amount_captured"`
		AmountRefunded int64   `json:"amount_refunded"`
		AmountVoided   int64   `json:"amount_voided"`
		Refunded       bool    `json:"refunded"`
		VoidReason     *string `json:"void_reason"`
	} `json:"expect"`
}

func TestSharedChargeContract(t *testing.T) {
	cases := loadContract(t)
	if len(cases) == 0 {
		t.Fatal("contract has no cases")
	}

	for _, tc := range cases {
		t.Run(tc.Name, func(t *testing.T) {
			c, err := apply(tc)

			if tc.Expect.Error != "" {
				var ruleErr *RuleError
				if !errors.As(err, &ruleErr) {
					t.Fatalf("expected rule error %q, got %v", tc.Expect.Error, err)
				}
				if ruleErr.Code != tc.Expect.Error {
					t.Fatalf("expected %q, got %q", tc.Expect.Error, ruleErr.Code)
				}
				return
			}
			if err != nil {
				t.Fatalf("unexpected error: %v", err)
			}

			got := [4]int64{c.Amount, c.AmountCaptured, c.AmountRefunded, c.AmountVoided}
			want := [4]int64{tc.Expect.Amount, tc.Expect.AmountCaptured, tc.Expect.AmountRefunded, tc.Expect.AmountVoided}
			if got != want {
				t.Errorf("amounts (amount, captured, refunded, voided) = %v, want %v", got, want)
			}
			if c.Status != tc.Expect.Status {
				t.Errorf("status = %q, want %q", c.Status, tc.Expect.Status)
			}
			if c.Refunded != tc.Expect.Refunded {
				t.Errorf("refunded = %v, want %v", c.Refunded, tc.Expect.Refunded)
			}
			if tc.Expect.VoidReason != nil && (c.VoidReason == nil || *c.VoidReason != *tc.Expect.VoidReason) {
				t.Errorf("void reason = %v, want %q", c.VoidReason, *tc.Expect.VoidReason)
			}
		})
	}
}

func apply(tc contractCase) (Charge, error) {
	op := tc.Operation
	if op.Type == "create" {
		account := Account{ID: 1, Status: op.Account.Status, ActiveYN: op.Account.ActiveYN}
		var amount int64
		if op.Amount != nil {
			amount = *op.Amount
		}
		return New(account, amount, "USD", "customer@example.com", op.Capture, 1, time.Now())
	}

	c := Charge{
		Status:         tc.Charge.Status,
		Amount:         tc.Charge.Amount,
		AmountCaptured: tc.Charge.AmountCaptured,
		AmountRefunded: tc.Charge.AmountRefunded,
		AmountVoided:   tc.Charge.AmountVoided,
		Disputed:       tc.Charge.Disputed,
		Refunded:       tc.Charge.Status == StatusRefunded,
	}
	var err error
	switch op.Type {
	case "capture":
		err = c.Capture()
	case "refund":
		err = c.Refund(op.Amount)
	case "void":
		err = c.Void(op.Reason)
	default:
		err = errors.New("unknown operation " + op.Type)
	}
	return c, err
}

func loadContract(t *testing.T) []contractCase {
	t.Helper()
	raw, err := os.ReadFile(filepath.Join("..", "..", "..", "contracts", "charge-operations.json"))
	if err != nil {
		t.Fatalf("read contract: %v", err)
	}
	var contract struct {
		Cases []contractCase `json:"cases"`
	}
	if err := json.Unmarshal(raw, &contract); err != nil {
		t.Fatalf("parse contract: %v", err)
	}
	return contract.Cases
}

func TestNewChargeGetsAUniqueTransactionID(t *testing.T) {
	account := Account{ID: 7, Status: "active", ActiveYN: true}
	a, _ := New(account, 100, "USD", "a@example.com", true, 1, time.Now())
	b, _ := New(account, 100, "USD", "a@example.com", true, 1, time.Now())

	if len(a.ExternalTransactionID) != len("txn_")+24 || a.ExternalTransactionID == b.ExternalTransactionID {
		t.Fatalf("unexpected transaction IDs %q and %q", a.ExternalTransactionID, b.ExternalTransactionID)
	}
}
