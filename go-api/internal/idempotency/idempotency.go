// Package idempotency stores responses for requests sent with an
// Idempotency-Key header, so a client can safely retry a payment operation
// after a timeout without charging or refunding twice.
//
// A record is written in the same transaction as the change it describes.
// Two concurrent requests with the same key cannot both commit: the second
// insert waits on the unique index, fails once the first commits, and the
// caller rolls back and replays the stored response instead.
package idempotency

import (
	"context"
	"crypto/sha256"
	"database/sql"
	"encoding/hex"
	"errors"
	"fmt"
	"time"

	"github.com/ErsinMehmed/merchant-flow/go-api/internal/database"
)

// TTL is how long a key is remembered.
const TTL = 24 * time.Hour

const maxKeyLength = 255

// ErrDuplicate means another request stored the same key first.
var ErrDuplicate = errors.New("idempotency key already used")

type Record struct {
	RequestHash string
	Status      int
	Body        []byte
}

// ValidKey accepts 1-255 printable ASCII characters, e.g. a UUID.
func ValidKey(key string) bool {
	if key == "" || len(key) > maxKeyLength {
		return false
	}
	for i := 0; i < len(key); i++ {
		if key[i] < 0x21 || key[i] > 0x7e {
			return false
		}
	}
	return true
}

// Fingerprint identifies the request a key was first used with. Reusing a
// key for a different request is an error rather than a replay.
func Fingerprint(method, path string, body []byte) string {
	h := sha256.New()
	h.Write([]byte(method + "\n" + path + "\n"))
	h.Write(body)
	return hex.EncodeToString(h.Sum(nil))
}

// Find returns the stored record for the user's key, or nil. Records are
// kept until Cleanup removes them, at least TTL after they were written.
func Find(ctx context.Context, q database.Querier, userID int64, key string) (*Record, error) {
	var r Record
	err := q.QueryRowContext(ctx, `
		SELECT request_hash, response_status, response_body
		  FROM idempotency_keys
		 WHERE user_id = ? AND idempotency_key = ?`,
		userID, key,
	).Scan(&r.RequestHash, &r.Status, &r.Body)
	if errors.Is(err, sql.ErrNoRows) {
		return nil, nil
	}
	if err != nil {
		return nil, fmt.Errorf("find idempotency key: %w", err)
	}
	return &r, nil
}

// Save stores the response. It returns ErrDuplicate if the key was taken by
// a request that committed first.
//
// It is a plain INSERT on purpose: deleting an expired row first would take
// gap locks and let two concurrent requests with the same key deadlock.
func Save(ctx context.Context, q database.Querier, userID int64, key string, r Record, now time.Time) error {
	_, err := q.ExecContext(ctx, `
		INSERT INTO idempotency_keys (user_id, idempotency_key, request_hash, response_status, response_body, created_at)
		VALUES (?, ?, ?, ?, ?, ?)`,
		userID, key, r.RequestHash, r.Status, r.Body, now,
	)
	if database.IsDuplicateKey(err) {
		return ErrDuplicate
	}
	if err != nil {
		return fmt.Errorf("save idempotency key: %w", err)
	}
	return nil
}

func Cleanup(ctx context.Context, q database.Querier, now time.Time) (int64, error) {
	res, err := q.ExecContext(ctx, `DELETE FROM idempotency_keys WHERE created_at <= ?`, now.Add(-TTL))
	if err != nil {
		return 0, fmt.Errorf("delete expired idempotency keys: %w", err)
	}
	return res.RowsAffected()
}
