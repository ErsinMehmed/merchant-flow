package auth

import (
	"context"
	"database/sql"
	"errors"
	"fmt"
	"strconv"

	"golang.org/x/crypto/bcrypt"

	"github.com/ErsinMehmed/merchant-flow/go-api/internal/database"
)

var ErrWrongPassword = errors.New("the current password is incorrect")

// ChangePassword replaces the user's password once the current one checks
// out, and signs the user out everywhere else: other portal sessions (all but
// keepToken), Nova sessions and Nova's remember-me cookie. The new password
// must already have passed PasswordProblem.
//
// Wrong current passwords count towards the same lock-out as failed logins.
func (s *Service) ChangePassword(ctx context.Context, userID int64, keepToken, current, next string) error {
	key := "password|" + strconv.FormatInt(userID, 10)
	if blocked, retryAfter := s.limiter.blocked(key); blocked {
		return &TooManyAttemptsError{RetryAfter: retryAfter}
	}

	var hash string
	err := s.db.QueryRowContext(ctx, `SELECT password FROM users WHERE id = ?`, userID).Scan(&hash)
	if err != nil {
		return fmt.Errorf("find user: %w", err)
	}
	if !passwordMatches(hash, current) {
		s.limiter.fail(key)
		return ErrWrongPassword
	}
	s.limiter.reset(key)

	newHash, err := bcrypt.GenerateFromPassword([]byte(next), hashCost)
	if err != nil {
		return fmt.Errorf("hash password: %w", err)
	}

	return database.InTx(ctx, s.db, func(tx *sql.Tx) error {
		if _, err := tx.ExecContext(ctx,
			`UPDATE users SET password = ?, remember_token = NULL, updated_at = ? WHERE id = ?`,
			string(newHash), s.now(), userID,
		); err != nil {
			return fmt.Errorf("update password: %w", err)
		}
		if _, err := tx.ExecContext(ctx,
			`DELETE FROM api_tokens WHERE user_id = ? AND token_hash <> ?`, userID, hashToken(keepToken),
		); err != nil {
			return fmt.Errorf("end other portal sessions: %w", err)
		}
		// Laravel's database session driver; Nova does not re-check the
		// password on existing sessions.
		if _, err := tx.ExecContext(ctx, `DELETE FROM sessions WHERE user_id = ?`, userID); err != nil {
			return fmt.Errorf("end admin sessions: %w", err)
		}
		return nil
	})
}
