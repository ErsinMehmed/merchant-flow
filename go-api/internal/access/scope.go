package access

// Scope limits queries to the accounts a principal may see. Every query on
// accounts or charges goes through Filter, which is what turns another
// tenant's ID into "not found" rather than "forbidden".
type Scope struct {
	all    bool
	userID int64
}

// AllAccounts reports whether the scope is unrestricted (administrators).
func (s Scope) AllAccounts() bool {
	return s.all
}

// Filter returns a SQL condition restricting accountColumn to the scope, and
// its arguments. accountColumn must be a column reference written in code,
// never user input.
//
// Agents see the merchants their own agent records serve; a parent agent does
// not inherit the merchants of its sub-agents.
func (s Scope) Filter(accountColumn string) (string, []any) {
	if s.all {
		return "1 = 1", nil
	}

	return accountColumn + ` IN (
			SELECT au.account_id FROM account_user au WHERE au.user_id = ?
			UNION
			SELECT aa.account_id
			  FROM agent_user ag
			  JOIN agent_account aa ON aa.agent_id = ag.agent_id
			 WHERE ag.user_id = ?
		)`, []any{s.userID, s.userID}
}
