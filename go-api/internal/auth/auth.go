// Package auth implements merchant portal sign-in.
//
// The API checks the email and password against the users table (Laravel
// bcrypt hashes) and issues an opaque random token. Only the token's SHA-256
// hash is stored, in the api_tokens table owned by the PHP application, so a
// session can be listed and revoked from the admin portal. Tokens carry no
// permissions; those are read from the database on every request.
package auth

import (
	"context"
	"database/sql"
	"errors"
	"fmt"
	"strings"
	"time"
)

var (
	ErrInvalidCredentials = errors.New("invalid email or password")
	ErrUnauthenticated    = errors.New("authentication required")
)

// TooManyAttemptsError is returned while a client is locked out of login.
type TooManyAttemptsError struct {
	RetryAfter time.Duration
}

func (e *TooManyAttemptsError) Error() string {
	return "too many login attempts"
}

type User struct {
	ID    int64
	Name  string
	Email string
}

type Session struct {
	Token     string
	ExpiresAt time.Time
}

// Client describes where a login came from; stored with the session so an
// administrator can recognise it in Nova.
type Client struct {
	IP        string
	UserAgent string
}

const (
	loginAttempts = 5
	loginWindow   = time.Minute
	// Writing last_used_at on every request would turn reads into writes.
	touchInterval = time.Minute
)

type Service struct {
	db      *sql.DB
	ttl     time.Duration
	limiter *limiter
	now     func() time.Time
}

func NewService(db *sql.DB, ttl time.Duration) *Service {
	return &Service{
		db:      db,
		ttl:     ttl,
		limiter: newLimiter(loginAttempts, loginWindow),
		now:     func() time.Time { return time.Now().UTC() },
	}
}

func (s *Service) Login(ctx context.Context, email, password string, client Client) (Session, User, error) {
	email = strings.ToLower(strings.TrimSpace(email))
	key := email + "|" + client.IP
	if blocked, retryAfter := s.limiter.blocked(key); blocked {
		return Session{}, User{}, &TooManyAttemptsError{RetryAfter: retryAfter}
	}

	var (
		user User
		hash string
	)
	err := s.db.QueryRowContext(ctx,
		`SELECT id, name, email, password FROM users WHERE email = ?`, email,
	).Scan(&user.ID, &user.Name, &user.Email, &hash)
	switch {
	case errors.Is(err, sql.ErrNoRows):
		passwordMatches(timingHash, password)
		s.limiter.fail(key)
		return Session{}, User{}, ErrInvalidCredentials
	case err != nil:
		return Session{}, User{}, fmt.Errorf("find user: %w", err)
	}

	if !passwordMatches(hash, password) {
		s.limiter.fail(key)
		return Session{}, User{}, ErrInvalidCredentials
	}
	s.limiter.reset(key)

	session := Session{Token: newToken(), ExpiresAt: s.now().Add(s.ttl).Truncate(time.Second)}
	_, err = s.db.ExecContext(ctx, `
		INSERT INTO api_tokens (user_id, token_hash, user_agent, ip_address, expires_at, created_at)
		VALUES (?, ?, ?, ?, ?, ?)`,
		user.ID, hashToken(session.Token), truncate(client.UserAgent, 255), truncate(client.IP, 45),
		session.ExpiresAt, s.now(),
	)
	if err != nil {
		return Session{}, User{}, fmt.Errorf("store session: %w", err)
	}

	return session, user, nil
}

// Authenticate resolves a token to its user. Expired, revoked and unknown
// tokens all return ErrUnauthenticated.
func (s *Service) Authenticate(ctx context.Context, token string) (User, error) {
	if !wellFormed(token) {
		return User{}, ErrUnauthenticated
	}

	var (
		tokenID  int64
		lastUsed sql.NullTime
		user     User
	)
	err := s.db.QueryRowContext(ctx, `
		SELECT t.id, t.last_used_at, u.id, u.name, u.email
		  FROM api_tokens t
		  JOIN users u ON u.id = t.user_id
		 WHERE t.token_hash = ? AND t.expires_at > ?`,
		hashToken(token), s.now(),
	).Scan(&tokenID, &lastUsed, &user.ID, &user.Name, &user.Email)
	switch {
	case errors.Is(err, sql.ErrNoRows):
		return User{}, ErrUnauthenticated
	case err != nil:
		return User{}, fmt.Errorf("find session: %w", err)
	}

	if !lastUsed.Valid || s.now().Sub(lastUsed.Time) > touchInterval {
		// Best effort: a failed touch must not fail the request.
		_, _ = s.db.ExecContext(ctx, `UPDATE api_tokens SET last_used_at = ? WHERE id = ?`, s.now(), tokenID)
	}

	return user, nil
}

func (s *Service) Logout(ctx context.Context, token string) error {
	if !wellFormed(token) {
		return nil
	}
	if _, err := s.db.ExecContext(ctx, `DELETE FROM api_tokens WHERE token_hash = ?`, hashToken(token)); err != nil {
		return fmt.Errorf("delete session: %w", err)
	}
	return nil
}

// Cleanup removes expired sessions and stale login counters.
func (s *Service) Cleanup(ctx context.Context) (int64, error) {
	s.limiter.prune()
	res, err := s.db.ExecContext(ctx, `DELETE FROM api_tokens WHERE expires_at <= ?`, s.now())
	if err != nil {
		return 0, fmt.Errorf("delete expired sessions: %w", err)
	}
	return res.RowsAffected()
}

// truncate limits s to max bytes without leaving a broken UTF-8 sequence,
// which MySQL would reject.
func truncate(s string, max int) string {
	if len(s) <= max {
		return s
	}
	return strings.ToValidUTF8(s[:max], "")
}
