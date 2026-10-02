// Package profile keeps the profile photos shown in the merchant portal and in
// Nova. Photos are rows in user_avatars (see the PHP migration
// create_user_avatars_table) because the PHP and Go containers share no file
// system; users.avatar_updated_at records when the photo last changed.
package profile

import (
	"context"
	"database/sql"
	"errors"
	"fmt"
	"slices"
	"time"

	"github.com/ErsinMehmed/merchant-flow/go-api/internal/database"
)

// MaxAvatarBytes matches UserAvatar::MAX_KILOBYTES in the PHP application.
const MaxAvatarBytes = 2 << 20

// avatarTypes leaves out SVG, which can carry scripts.
var avatarTypes = []string{"image/jpeg", "image/png", "image/webp"}

// AcceptedType reports whether photos of this media type are accepted.
func AcceptedType(contentType string) bool {
	return slices.Contains(avatarTypes, contentType)
}

var ErrNoAvatar = errors.New("the user has no profile photo")

type Avatar struct {
	ContentType string
	Content     []byte
}

type Service struct {
	db  *sql.DB
	now func() time.Time
}

func NewService(db *sql.DB) *Service {
	return &Service{db: db, now: func() time.Time { return time.Now().UTC() }}
}

// AvatarUpdatedAt returns when the user's photo last changed, or nil when the
// user has none.
func (s *Service) AvatarUpdatedAt(ctx context.Context, userID int64) (*time.Time, error) {
	var updatedAt sql.NullTime
	err := s.db.QueryRowContext(ctx, `SELECT avatar_updated_at FROM users WHERE id = ?`, userID).Scan(&updatedAt)
	if err != nil {
		return nil, fmt.Errorf("find user: %w", err)
	}
	if !updatedAt.Valid {
		return nil, nil
	}
	return &updatedAt.Time, nil
}

func (s *Service) Avatar(ctx context.Context, userID int64) (Avatar, error) {
	var a Avatar
	err := s.db.QueryRowContext(ctx,
		`SELECT content_type, content FROM user_avatars WHERE user_id = ?`, userID,
	).Scan(&a.ContentType, &a.Content)
	if errors.Is(err, sql.ErrNoRows) {
		return Avatar{}, ErrNoAvatar
	}
	if err != nil {
		return Avatar{}, fmt.Errorf("find profile photo: %w", err)
	}
	return a, nil
}

// SaveAvatar replaces the user's photo. The caller checks the type and size.
func (s *Service) SaveAvatar(ctx context.Context, userID int64, a Avatar) error {
	return database.InTx(ctx, s.db, func(tx *sql.Tx) error {
		if _, err := tx.ExecContext(ctx, `
			INSERT INTO user_avatars (user_id, content_type, content) VALUES (?, ?, ?) AS new
			ON DUPLICATE KEY UPDATE content_type = new.content_type, content = new.content`,
			userID, a.ContentType, a.Content,
		); err != nil {
			return fmt.Errorf("store profile photo: %w", err)
		}
		return s.touch(ctx, tx, userID, s.now().Truncate(time.Second))
	})
}

func (s *Service) DeleteAvatar(ctx context.Context, userID int64) error {
	return database.InTx(ctx, s.db, func(tx *sql.Tx) error {
		if _, err := tx.ExecContext(ctx, `DELETE FROM user_avatars WHERE user_id = ?`, userID); err != nil {
			return fmt.Errorf("delete profile photo: %w", err)
		}
		return s.touch(ctx, tx, userID, nil)
	})
}

func (s *Service) touch(ctx context.Context, q database.Querier, userID int64, updatedAt any) error {
	if _, err := q.ExecContext(ctx, `UPDATE users SET avatar_updated_at = ? WHERE id = ?`, updatedAt, userID); err != nil {
		return fmt.Errorf("update user: %w", err)
	}
	return nil
}
