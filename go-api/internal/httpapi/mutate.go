package httpapi

import (
	"database/sql"
	"encoding/json"
	"errors"
	"net/http"

	"github.com/ErsinMehmed/merchant-flow/go-api/internal/charge"
	"github.com/ErsinMehmed/merchant-flow/go-api/internal/database"
	"github.com/ErsinMehmed/merchant-flow/go-api/internal/idempotency"
)

// writeFunc performs a write inside the given transaction and returns the
// success status and body, or an error.
type writeFunc func(tx *sql.Tx) (int, any, error)

// mutate runs a write in one transaction and, when the request carries an
// Idempotency-Key, stores the response in that same transaction.
//
// Successful results and charge rule rejections are stored and replayed on
// retry. Permission and not-found errors are not stored: nothing ran, and the
// answer may change once an administrator grants access.
func (s *Server) mutate(w http.ResponseWriter, r *http.Request, body []byte, fn writeFunc) {
	ctx := r.Context()
	p := principalFrom(ctx)
	key := r.Header.Get("Idempotency-Key")
	if key != "" && !idempotency.ValidKey(key) {
		writeValidation(w, map[string]string{"Idempotency-Key": "must be 1-255 printable ASCII characters"})
		return
	}
	fingerprint := idempotency.Fingerprint(r.Method, r.URL.Path, body)

	if key != "" {
		stored, err := idempotency.Find(ctx, s.db, p.UserID, key)
		if err != nil {
			writeInternal(w, r, err)
			return
		}
		if stored != nil {
			replay(w, stored, fingerprint)
			return
		}
	}

	var (
		status  int
		payload []byte
	)
	err := database.InTx(ctx, s.db, func(tx *sql.Tx) error {
		st, result, err := fn(tx)
		var ruleErr *charge.RuleError
		switch {
		case errors.As(err, &ruleErr):
			if key == "" {
				return err
			}
			st, result = ruleStatus(ruleErr), newError(ruleErr.Code, ruleErr.Message, nil)
		case err != nil:
			return err
		}

		status = st
		if payload, err = json.Marshal(result); err != nil {
			return err
		}
		if key == "" {
			return nil
		}
		return idempotency.Save(ctx, tx, p.UserID, key, idempotency.Record{
			RequestHash: fingerprint,
			Status:      status,
			Body:        payload,
		}, s.now())
	})

	switch {
	case errors.Is(err, idempotency.ErrDuplicate):
		// A concurrent request with the same key committed first; our
		// changes were rolled back. Answer with its result.
		stored, findErr := idempotency.Find(ctx, s.db, p.UserID, key)
		if findErr != nil || stored == nil {
			writeInternal(w, r, errors.Join(err, findErr))
			return
		}
		replay(w, stored, fingerprint)
	case err != nil:
		writeServiceError(w, r, err)
	default:
		writeRaw(w, status, payload)
	}
}

func replay(w http.ResponseWriter, stored *idempotency.Record, fingerprint string) {
	if stored.RequestHash != fingerprint {
		writeError(w, http.StatusUnprocessableEntity, "idempotency_key_reused",
			"This Idempotency-Key was already used for a different request.")
		return
	}
	w.Header().Set("Idempotent-Replayed", "true")
	writeRaw(w, stored.Status, stored.Body)
}
