package httpapi

import (
	"errors"
	"math"
	"net/http"
	"strconv"
	"strings"
	"time"

	"github.com/ErsinMehmed/merchant-flow/go-api/internal/access"
	"github.com/ErsinMehmed/merchant-flow/go-api/internal/auth"
)

const sessionCookie = "mf_session"

// bcrypt only looks at the first 72 bytes; longer input is refused rather
// than silently truncated.
const maxPasswordBytes = 72

type loginRequest struct {
	Email    string `json:"email"`
	Password string `json:"password"`
}

type userJSON struct {
	ID          int64    `json:"id"`
	Name        string   `json:"name"`
	Email       string   `json:"email"`
	Roles       []string `json:"roles"`
	Permissions []string `json:"permissions"`
}

func toUserJSON(p access.Principal) userJSON {
	return userJSON{ID: p.UserID, Name: p.Name, Email: p.Email, Roles: p.Roles, Permissions: p.Permissions}
}

func (s *Server) login(w http.ResponseWriter, r *http.Request) {
	body, ok := readBody(w, r)
	if !ok {
		return
	}
	var req loginRequest
	if !decode(w, body, &req) {
		return
	}

	fields := map[string]string{}
	if strings.TrimSpace(req.Email) == "" {
		fields["email"] = "is required"
	}
	if req.Password == "" {
		fields["password"] = "is required"
	}
	if len(fields) > 0 {
		writeValidation(w, fields)
		return
	}
	if len(req.Password) > maxPasswordBytes || len(req.Email) > 254 {
		writeError(w, http.StatusUnauthorized, "invalid_credentials", "The email or password is incorrect.")
		return
	}

	session, user, err := s.auth.Login(r.Context(), req.Email, req.Password, auth.Client{
		IP:        s.clientIP(r),
		UserAgent: r.UserAgent(),
	})
	var tooMany *auth.TooManyAttemptsError
	switch {
	case errors.As(err, &tooMany):
		w.Header().Set("Retry-After", strconv.Itoa(int(math.Ceil(tooMany.RetryAfter.Seconds()))))
		writeError(w, http.StatusTooManyRequests, "too_many_attempts", "Too many sign-in attempts. Try again in a minute.")
		return
	case errors.Is(err, auth.ErrInvalidCredentials):
		writeError(w, http.StatusUnauthorized, "invalid_credentials", "The email or password is incorrect.")
		return
	case err != nil:
		writeInternal(w, r, err)
		return
	}

	principal, err := access.Load(r.Context(), s.db, user.ID, user.Name, user.Email)
	if err != nil {
		writeInternal(w, r, err)
		return
	}

	http.SetCookie(w, &http.Cookie{
		Name:     sessionCookie,
		Value:    session.Token,
		Path:     "/api",
		Expires:  session.ExpiresAt,
		MaxAge:   int(time.Until(session.ExpiresAt).Seconds()),
		HttpOnly: true,
		Secure:   s.cfg.CookieSecure,
		SameSite: http.SameSiteStrictMode,
	})

	// The token is also returned for API clients that send it as a Bearer
	// header; the portal relies on the HttpOnly cookie.
	writeJSON(w, http.StatusOK, dataBody{Data: map[string]any{
		"token":      session.Token,
		"expires_at": session.ExpiresAt,
		"user":       toUserJSON(principal),
	}})
}

func (s *Server) logout(w http.ResponseWriter, r *http.Request) {
	token, fromCookie := sessionToken(r)
	if fromCookie && crossSite(r) {
		writeError(w, http.StatusForbidden, "cross_site_request", "Cross-site requests are not allowed.")
		return
	}
	if token != "" {
		if err := s.auth.Logout(r.Context(), token); err != nil {
			writeInternal(w, r, err)
			return
		}
	}
	s.clearSessionCookie(w)
	w.WriteHeader(http.StatusNoContent)
}

func (s *Server) me(w http.ResponseWriter, r *http.Request) {
	writeJSON(w, http.StatusOK, dataBody{Data: toUserJSON(principalFrom(r.Context()))})
}

func (s *Server) clearSessionCookie(w http.ResponseWriter) {
	http.SetCookie(w, &http.Cookie{
		Name:     sessionCookie,
		Value:    "",
		Path:     "/api",
		MaxAge:   -1,
		HttpOnly: true,
		Secure:   s.cfg.CookieSecure,
		SameSite: http.SameSiteStrictMode,
	})
}
