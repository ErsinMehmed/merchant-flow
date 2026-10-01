// Package access reads roles and permissions from the Spatie tables owned by
// the PHP application and turns them into a Principal for one request.
//
// Nothing is cached: every request reads the current rows, so a change made
// in the admin portal applies to the next API call.
package access

import (
	"context"
	"fmt"
	"slices"

	"github.com/ErsinMehmed/merchant-flow/go-api/internal/database"
)

// Permission and role names, matching the PHP enums in
// php/app/Containers/AppSection/Authorization/Enums.
const (
	ViewCharges    = "charges.view"
	CreateCharges  = "charges.create"
	CaptureCharges = "charges.capture"
	RefundCharges  = "charges.refund"
	VoidCharges    = "charges.void"

	RoleAdmin = "admin"
)

// The values Spatie stores for users: the morph alias registered in
// ShipServiceProvider and the single auth guard.
const (
	morphType = "user"
	guardName = "web"
)

type Principal struct {
	UserID      int64
	Name        string
	Email       string
	Roles       []string
	Permissions []string
}

func (p Principal) Can(permission string) bool {
	return slices.Contains(p.Permissions, permission)
}

func (p Principal) IsAdmin() bool {
	return slices.Contains(p.Roles, RoleAdmin)
}

// Scope returns the accounts this principal may see: every account for an
// administrator, otherwise the accounts linked to the user directly
// (account_user) or through one of their agents (agent_user + agent_account).
func (p Principal) Scope() Scope {
	return Scope{all: p.IsAdmin(), userID: p.UserID}
}

// Load reads the user's roles and the union of direct and role permissions.
func Load(ctx context.Context, q database.Querier, userID int64, name, email string) (Principal, error) {
	p := Principal{UserID: userID, Name: name, Email: email}

	var err error
	p.Roles, err = names(ctx, q, `
		SELECT r.name
		  FROM model_has_roles m
		  JOIN roles r ON r.id = m.role_id
		 WHERE m.model_type = ? AND m.model_id = ? AND r.guard_name = ?
		 ORDER BY r.name`,
		morphType, userID, guardName)
	if err != nil {
		return Principal{}, fmt.Errorf("load roles: %w", err)
	}

	p.Permissions, err = names(ctx, q, `
		SELECT p.name
		  FROM model_has_permissions m
		  JOIN permissions p ON p.id = m.permission_id
		 WHERE m.model_type = ? AND m.model_id = ? AND p.guard_name = ?
		UNION
		SELECT p.name
		  FROM model_has_roles m
		  JOIN role_has_permissions rp ON rp.role_id = m.role_id
		  JOIN permissions p ON p.id = rp.permission_id
		 WHERE m.model_type = ? AND m.model_id = ? AND p.guard_name = ?
		 ORDER BY name`,
		morphType, userID, guardName, morphType, userID, guardName)
	if err != nil {
		return Principal{}, fmt.Errorf("load permissions: %w", err)
	}

	return p, nil
}

func names(ctx context.Context, q database.Querier, query string, args ...any) ([]string, error) {
	rows, err := q.QueryContext(ctx, query, args...)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	result := []string{}
	for rows.Next() {
		var name string
		if err := rows.Scan(&name); err != nil {
			return nil, err
		}
		result = append(result, name)
	}
	return result, rows.Err()
}
