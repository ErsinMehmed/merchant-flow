package httpapi

import (
	"encoding/json"
	"errors"
	"log/slog"
	"net/http"

	"github.com/ErsinMehmed/merchant-flow/go-api/internal/charge"
)

// errorBody is the single error shape returned by the API:
//
//	{"error": {"code": "...", "message": "...", "fields": {"amount": "..."}}}
type errorBody struct {
	Error apiError `json:"error"`
}

type apiError struct {
	Code    string            `json:"code"`
	Message string            `json:"message"`
	Fields  map[string]string `json:"fields,omitempty"`
}

func newError(code, message string, fields map[string]string) errorBody {
	return errorBody{Error: apiError{Code: code, Message: message, Fields: fields}}
}

type dataBody struct {
	Data any `json:"data"`
	Meta any `json:"meta,omitempty"`
}

func writeJSON(w http.ResponseWriter, status int, body any) {
	payload, err := json.Marshal(body)
	if err != nil {
		slog.Error("encode response", "error", err)
		status, payload = http.StatusInternalServerError, []byte(`{"error":{"code":"internal_error","message":"Something went wrong."}}`)
	}
	writeRaw(w, status, payload)
}

func writeRaw(w http.ResponseWriter, status int, payload []byte) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(status)
	_, _ = w.Write(payload)
}

func writeError(w http.ResponseWriter, status int, code, message string) {
	writeJSON(w, status, newError(code, message, nil))
}

func writeValidation(w http.ResponseWriter, fields map[string]string) {
	writeJSON(w, http.StatusUnprocessableEntity, newError("validation_failed", "The request is invalid.", fields))
}

func writeInternal(w http.ResponseWriter, r *http.Request, err error) {
	slog.ErrorContext(r.Context(), "request failed", "error", err, "request_id", requestID(r.Context()))
	writeError(w, http.StatusInternalServerError, "internal_error", "Something went wrong.")
}

// ruleStatus maps a refused charge operation to an HTTP status: 422 when the
// request itself asks for something impossible, 409 when the charge's current
// state does not allow it.
func ruleStatus(err *charge.RuleError) int {
	switch err.Code {
	case charge.CodeInvalidAmount, charge.CodeRefundExceedsAvailable:
		return http.StatusUnprocessableEntity
	default:
		return http.StatusConflict
	}
}

// writeServiceError handles the errors charge.Service returns.
func writeServiceError(w http.ResponseWriter, r *http.Request, err error) {
	var ruleErr *charge.RuleError
	switch {
	case errors.As(err, &ruleErr):
		writeError(w, ruleStatus(ruleErr), ruleErr.Code, ruleErr.Message)
	case errors.Is(err, charge.ErrForbidden):
		writeError(w, http.StatusForbidden, "forbidden", "You do not have permission to do this.")
	case errors.Is(err, charge.ErrNotFound):
		writeNotFound(w)
	default:
		writeInternal(w, r, err)
	}
}

func writeNotFound(w http.ResponseWriter) {
	writeError(w, http.StatusNotFound, "not_found", "The resource was not found.")
}
