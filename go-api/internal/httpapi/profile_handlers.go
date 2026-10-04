package httpapi

import (
	"errors"
	"io"
	"math"
	"net/http"
	"strconv"
	"time"

	"github.com/ErsinMehmed/merchant-flow/go-api/internal/auth"
	"github.com/ErsinMehmed/merchant-flow/go-api/internal/profile"
)

const avatarPath = "/api/v1/me/avatar"

// avatarURL carries the photo's version, so a new photo gets a new URL.
func avatarURL(updatedAt *time.Time) *string {
	if updatedAt == nil {
		return nil
	}
	url := avatarPath + "?v=" + strconv.FormatInt(updatedAt.UnixMicro(), 10)
	return &url
}

func (s *Server) avatar(w http.ResponseWriter, r *http.Request) {
	a, err := s.profile.Avatar(r.Context(), principalFrom(r.Context()).UserID)
	if errors.Is(err, profile.ErrNoAvatar) {
		writeNotFound(w)
		return
	}
	if err != nil {
		writeInternal(w, r, err)
		return
	}

	h := w.Header()
	h.Set("Content-Type", a.ContentType)
	h.Set("Content-Length", strconv.Itoa(len(a.Content)))
	h.Set("Content-Disposition", "inline")
	h.Set("Content-Security-Policy", "default-src 'none'")
	w.WriteHeader(http.StatusOK)
	_, _ = w.Write(a.Content)
}

// updateAvatar takes the image itself as the request body, sent with its
// image Content-Type. HTML forms cannot send such a request, so this keeps the
// cross-site protection of the JSON-only API (see requireJSON).
func (s *Server) updateAvatar(w http.ResponseWriter, r *http.Request) {
	content, err := io.ReadAll(http.MaxBytesReader(w, r.Body, profile.MaxAvatarBytes))
	if err != nil {
		var tooLarge *http.MaxBytesError
		if errors.As(err, &tooLarge) {
			writeError(w, http.StatusRequestEntityTooLarge, "payload_too_large", "The photo must be 2 MB or smaller.")
		} else {
			writeError(w, http.StatusBadRequest, "invalid_body", "The photo could not be read.")
		}
		return
	}

	// The stored type comes from the bytes, not from the request header.
	contentType := http.DetectContentType(content)
	switch {
	case len(content) == 0:
		writeValidation(w, map[string]string{"photo": "is required"})
		return
	case !profile.AcceptedType(contentType):
		writeValidation(w, map[string]string{"photo": "must be a JPEG, PNG or WebP image"})
		return
	}

	photo := profile.Avatar{ContentType: contentType, Content: content}
	if err := s.profile.SaveAvatar(r.Context(), principalFrom(r.Context()).UserID, photo); err != nil {
		writeInternal(w, r, err)
		return
	}
	s.writeUser(w, r)
}

func (s *Server) deleteAvatar(w http.ResponseWriter, r *http.Request) {
	if err := s.profile.DeleteAvatar(r.Context(), principalFrom(r.Context()).UserID); err != nil {
		writeInternal(w, r, err)
		return
	}
	s.writeUser(w, r)
}

type changePasswordRequest struct {
	CurrentPassword      string `json:"current_password"`
	Password             string `json:"password"`
	PasswordConfirmation string `json:"password_confirmation"`
}

func (s *Server) changePassword(w http.ResponseWriter, r *http.Request) {
	body, ok := readBody(w, r)
	if !ok {
		return
	}
	var req changePasswordRequest
	if !decode(w, body, &req) {
		return
	}

	fields := map[string]string{}
	if req.CurrentPassword == "" {
		fields["current_password"] = "is required"
	}
	switch problem := auth.PasswordProblem(req.Password); {
	case req.Password == "":
		fields["password"] = "is required"
	case problem != "":
		fields["password"] = problem
	case req.Password != req.PasswordConfirmation:
		fields["password_confirmation"] = "does not match the new password"
	}
	if len(fields) > 0 {
		writeValidation(w, fields)
		return
	}

	token, _ := sessionToken(r)
	err := s.auth.ChangePassword(r.Context(), principalFrom(r.Context()).UserID, token, req.CurrentPassword, req.Password)
	var tooMany *auth.TooManyAttemptsError
	switch {
	case errors.As(err, &tooMany):
		w.Header().Set("Retry-After", strconv.Itoa(int(math.Ceil(tooMany.RetryAfter.Seconds()))))
		writeError(w, http.StatusTooManyRequests, "too_many_attempts", "Too many attempts. Try again in a minute.")
	case errors.Is(err, auth.ErrWrongPassword):
		writeValidation(w, map[string]string{"current_password": "is incorrect"})
	case err != nil:
		writeInternal(w, r, err)
	default:
		w.WriteHeader(http.StatusNoContent)
	}
}
