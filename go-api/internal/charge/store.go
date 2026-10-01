package charge

import (
	"context"
	"database/sql"
	"errors"
	"fmt"
	"slices"

	"github.com/ErsinMehmed/merchant-flow/go-api/internal/access"
	"github.com/ErsinMehmed/merchant-flow/go-api/internal/database"
)

const chargeColumns = `
	c.id, c.account_id, a.business_name, a.mid, c.amount, c.amount_captured, c.amount_refunded,
	c.amount_voided, c.currency, c.status, c.external_transaction_id, c.customer_email,
	c.refunded, c.disputed, c.void_reason, c.created_by, c.created_at, c.updated_at`

func scanCharge(row interface{ Scan(...any) error }) (Charge, error) {
	var c Charge
	var voidReason sql.NullString
	err := row.Scan(
		&c.ID, &c.AccountID, &c.AccountName, &c.AccountMID, &c.Amount, &c.AmountCaptured, &c.AmountRefunded,
		&c.AmountVoided, &c.Currency, &c.Status, &c.ExternalTransactionID, &c.CustomerEmail,
		&c.Refunded, &c.Disputed, &voidReason, &c.CreatedBy, &c.CreatedAt, &c.UpdatedAt,
	)
	if voidReason.Valid {
		c.VoidReason = &voidReason.String
	}
	return c, err
}

func list(ctx context.Context, q database.Querier, scope access.Scope, p ListParams) ([]Charge, int, error) {
	scopeSQL, scopeArgs := scope.Filter("c.account_id")
	filterSQL, filterArgs := p.where()
	where := scopeSQL + " AND " + filterSQL
	args := slices.Concat(scopeArgs, filterArgs)

	var total int
	if err := q.QueryRowContext(ctx, `SELECT COUNT(*) FROM charges c WHERE `+where, args...).Scan(&total); err != nil {
		return nil, 0, fmt.Errorf("count charges: %w", err)
	}

	direction := "ASC"
	if p.SortDesc {
		direction = "DESC"
	}
	// c.id breaks ties so pages never overlap or skip rows.
	query := `SELECT ` + chargeColumns + `
		FROM charges c
		JOIN accounts a ON a.id = c.account_id
		WHERE ` + where + `
		ORDER BY ` + p.SortColumn + ` ` + direction + `, c.id ` + direction + `
		LIMIT ? OFFSET ?`

	rows, err := q.QueryContext(ctx, query, slices.Concat(args, []any{p.PageSize, (p.Page - 1) * p.PageSize})...)
	if err != nil {
		return nil, 0, fmt.Errorf("list charges: %w", err)
	}
	defer rows.Close()

	charges := []Charge{}
	for rows.Next() {
		c, err := scanCharge(rows)
		if err != nil {
			return nil, 0, fmt.Errorf("scan charge: %w", err)
		}
		charges = append(charges, c)
	}
	return charges, total, rows.Err()
}

// find loads one charge within the scope. With lock it takes a row lock on
// the charge (not the account) until the transaction ends.
func find(ctx context.Context, q database.Querier, scope access.Scope, id int64, lock bool) (Charge, error) {
	scopeSQL, scopeArgs := scope.Filter("c.account_id")
	query := `SELECT ` + chargeColumns + `
		FROM charges c
		JOIN accounts a ON a.id = c.account_id
		WHERE c.id = ? AND ` + scopeSQL
	if lock {
		query += ` FOR UPDATE OF c`
	}

	c, err := scanCharge(q.QueryRowContext(ctx, query, slices.Concat([]any{id}, scopeArgs)...))
	if errors.Is(err, sql.ErrNoRows) {
		return Charge{}, ErrNotFound
	}
	if err != nil {
		return Charge{}, fmt.Errorf("find charge: %w", err)
	}
	return c, nil
}

func insert(ctx context.Context, q database.Querier, c *Charge) error {
	res, err := q.ExecContext(ctx, `
		INSERT INTO charges (account_id, amount, amount_captured, amount_refunded, amount_voided, currency,
			status, external_transaction_id, customer_email, refunded, disputed, void_reason, created_by,
			created_at, updated_at)
		VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
		c.AccountID, c.Amount, c.AmountCaptured, c.AmountRefunded, c.AmountVoided, c.Currency,
		c.Status, c.ExternalTransactionID, c.CustomerEmail, c.Refunded, c.Disputed, c.VoidReason, c.CreatedBy,
		c.CreatedAt, c.UpdatedAt,
	)
	if err != nil {
		return fmt.Errorf("insert charge: %w", err)
	}
	c.ID, err = res.LastInsertId()
	return err
}

// update writes the fields an operation may change.
func update(ctx context.Context, q database.Querier, c Charge) error {
	_, err := q.ExecContext(ctx, `
		UPDATE charges
		   SET amount_captured = ?, amount_refunded = ?, amount_voided = ?, status = ?, refunded = ?,
		       void_reason = ?, updated_at = ?
		 WHERE id = ?`,
		c.AmountCaptured, c.AmountRefunded, c.AmountVoided, c.Status, c.Refunded, c.VoidReason, c.UpdatedAt, c.ID,
	)
	if err != nil {
		return fmt.Errorf("update charge: %w", err)
	}
	return nil
}

func findAccount(ctx context.Context, q database.Querier, scope access.Scope, id int64) (Account, error) {
	scopeSQL, scopeArgs := scope.Filter("a.id")
	var a Account
	err := q.QueryRowContext(ctx, `
		SELECT a.id, a.business_name, a.mid, a.status, a.active_yn
		  FROM accounts a
		 WHERE a.id = ? AND `+scopeSQL,
		slices.Concat([]any{id}, scopeArgs)...,
	).Scan(&a.ID, &a.Name, &a.MID, &a.Status, &a.ActiveYN)
	if errors.Is(err, sql.ErrNoRows) {
		return Account{}, ErrNotFound
	}
	if err != nil {
		return Account{}, fmt.Errorf("find account: %w", err)
	}
	return a, nil
}

func listAccounts(ctx context.Context, q database.Querier, scope access.Scope) ([]Account, error) {
	scopeSQL, scopeArgs := scope.Filter("a.id")
	rows, err := q.QueryContext(ctx, `
		SELECT a.id, a.business_name, a.mid, a.status, a.active_yn
		  FROM accounts a
		 WHERE `+scopeSQL+`
		 ORDER BY a.business_name, a.id`, scopeArgs...)
	if err != nil {
		return nil, fmt.Errorf("list accounts: %w", err)
	}
	defer rows.Close()

	accounts := []Account{}
	for rows.Next() {
		var a Account
		if err := rows.Scan(&a.ID, &a.Name, &a.MID, &a.Status, &a.ActiveYN); err != nil {
			return nil, fmt.Errorf("scan account: %w", err)
		}
		accounts = append(accounts, a)
	}
	return accounts, rows.Err()
}
