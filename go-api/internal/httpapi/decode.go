package httpapi

import (
	"bytes"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"net/http"
)

const maxBodyBytes = 64 << 10

// readBody reads a size-limited request body. The raw bytes are kept for the
// idempotency fingerprint.
func readBody(w http.ResponseWriter, r *http.Request) ([]byte, bool) {
	body, err := io.ReadAll(http.MaxBytesReader(w, r.Body, maxBodyBytes))
	if err != nil {
		var tooLarge *http.MaxBytesError
		if errors.As(err, &tooLarge) {
			writeError(w, http.StatusRequestEntityTooLarge, "payload_too_large", "The request body is too large.")
		} else {
			writeError(w, http.StatusBadRequest, "invalid_json", "The request body could not be read.")
		}
		return nil, false
	}
	return body, true
}

// decode parses body into dst strictly: one JSON object, no unknown fields.
// An empty body is allowed and leaves dst unchanged, for endpoints whose
// fields are all optional. It writes the error response itself.
func decode(w http.ResponseWriter, body []byte, dst any) bool {
	if len(bytes.TrimSpace(body)) == 0 {
		return true
	}

	dec := json.NewDecoder(bytes.NewReader(body))
	dec.DisallowUnknownFields()
	err := dec.Decode(dst)
	if err == nil && dec.More() {
		err = errors.New("the body must contain a single JSON object")
	}
	if err == nil {
		return true
	}

	var typeErr *json.UnmarshalTypeError
	if errors.As(err, &typeErr) && typeErr.Field != "" {
		writeValidation(w, map[string]string{typeErr.Field: fmt.Sprintf("must be a %s", jsonTypeName(typeErr.Type.Kind().String()))})
		return false
	}
	writeError(w, http.StatusBadRequest, "invalid_json", "The request body is not valid JSON: "+err.Error())
	return false
}

func jsonTypeName(kind string) string {
	switch kind {
	case "int64", "int":
		return "whole number"
	case "bool":
		return "boolean"
	default:
		return kind
	}
}
