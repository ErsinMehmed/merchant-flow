package httpapi

import (
	"context"
	"crypto/rand"
	"encoding/hex"
	"errors"
	"log/slog"
	"mime"
	"net"
	"net/http"
	"net/url"
	"runtime/debug"
	"strings"
	"time"

	"github.com/ErsinMehmed/merchant-flow/go-api/internal/access"
	"github.com/ErsinMehmed/merchant-flow/go-api/internal/auth"
	"github.com/ErsinMehmed/merchant-flow/go-api/internal/profile"
)

type contextKey int

const (
	requestIDKey contextKey = iota
	principalKey
)

func requestID(ctx context.Context) string {
	id, _ := ctx.Value(requestIDKey).(string)
	return id
}

func principalFrom(ctx context.Context) access.Principal {
	p, _ := ctx.Value(principalKey).(access.Principal)
	return p
}

type statusRecorder struct {
	http.ResponseWriter
	status int
}

func (r *statusRecorder) WriteHeader(status int) {
	r.status = status
	r.ResponseWriter.WriteHeader(status)
}

// observe assigns a request ID, recovers from panics and logs one line per
// request.
func observe(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		id := r.Header.Get("X-Request-ID")
		if id == "" || len(id) > 64 {
			b := make([]byte, 8)
			_, _ = rand.Read(b)
			id = hex.EncodeToString(b)
		}
		ctx := context.WithValue(r.Context(), requestIDKey, id)
		w.Header().Set("X-Request-ID", id)

		rec := &statusRecorder{ResponseWriter: w, status: http.StatusOK}
		start := time.Now()
		defer func() {
			if p := recover(); p != nil {
				slog.ErrorContext(ctx, "panic", "panic", p, "stack", string(debug.Stack()), "request_id", id)
				writeError(rec, http.StatusInternalServerError, "internal_error", "Something went wrong.")
			}
			if r.URL.Path == "/api/health" {
				return // polled every few seconds by Docker
			}
			slog.InfoContext(ctx, "request",
				"method", r.Method,
				"path", r.URL.Path,
				"status", rec.status,
				"duration_ms", time.Since(start).Milliseconds(),
				"request_id", id,
			)
		}()

		next.ServeHTTP(rec, r.WithContext(ctx))
	})
}

// secureHeaders applies to every API response. Responses carry tenant data,
// so nothing may be cached.
func secureHeaders(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		h := w.Header()
		h.Set("Cache-Control", "no-store")
		h.Set("X-Content-Type-Options", "nosniff")
		h.Set("X-Frame-Options", "DENY")
		h.Set("Referrer-Policy", "no-referrer")
		next.ServeHTTP(w, r)
	})
}

// requireJSON rejects bodies that are not JSON. Besides keeping parsing
// simple, it stops HTML forms on other sites from posting to the API. The one
// exception is a profile photo upload, sent as the image itself; forms cannot
// send image types either.
func requireJSON(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.ContentLength != 0 && r.Method != http.MethodGet {
			mediaType, _, err := mime.ParseMediaType(r.Header.Get("Content-Type"))
			photo := r.Method == http.MethodPut && r.URL.Path == avatarPath
			switch {
			case photo && (err != nil || !profile.AcceptedType(mediaType)):
				writeError(w, http.StatusUnsupportedMediaType, "unsupported_media_type", "Send the photo as image/jpeg, image/png or image/webp.")
				return
			case !photo && (err != nil || mediaType != "application/json"):
				writeError(w, http.StatusUnsupportedMediaType, "unsupported_media_type", "Send the request body as application/json.")
				return
			}
		}
		next.ServeHTTP(w, r)
	})
}

// authenticated resolves the session and loads the caller's roles and
// permissions from the database for this request.
func (s *Server) authenticated(next http.HandlerFunc) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		token, fromCookie := sessionToken(r)
		if token == "" {
			writeError(w, http.StatusUnauthorized, "unauthenticated", "Sign in to continue.")
			return
		}
		if fromCookie && !safeMethod(r.Method) && crossSite(r) {
			writeError(w, http.StatusForbidden, "cross_site_request", "Cross-site requests are not allowed.")
			return
		}

		user, err := s.auth.Authenticate(r.Context(), token)
		if errors.Is(err, auth.ErrUnauthenticated) {
			if fromCookie {
				s.clearSessionCookie(w)
			}
			writeError(w, http.StatusUnauthorized, "unauthenticated", "Your session has expired. Sign in again.")
			return
		}
		if err != nil {
			writeInternal(w, r, err)
			return
		}

		principal, err := access.Load(r.Context(), s.db, user.ID, user.Name, user.Email)
		if err != nil {
			writeInternal(w, r, err)
			return
		}

		next(w, r.WithContext(context.WithValue(r.Context(), principalKey, principal)))
	}
}

// sessionToken prefers an Authorization header (API clients) over the
// session cookie (the merchant portal).
func sessionToken(r *http.Request) (token string, fromCookie bool) {
	if h := r.Header.Get("Authorization"); h != "" {
		if t, ok := strings.CutPrefix(h, "Bearer "); ok {
			return strings.TrimSpace(t), false
		}
		return "", false
	}
	if c, err := r.Cookie(sessionCookie); err == nil {
		return c.Value, true
	}
	return "", false
}

func safeMethod(method string) bool {
	return method == http.MethodGet || method == http.MethodHead || method == http.MethodOptions
}

// crossSite backs up the SameSite=Strict cookie: browsers send
// Sec-Fetch-Site and Origin on cross-site requests, and a mismatch is
// refused before any state changes.
func crossSite(r *http.Request) bool {
	if site := r.Header.Get("Sec-Fetch-Site"); site != "" && site != "same-origin" && site != "none" {
		return true
	}
	if origin := r.Header.Get("Origin"); origin != "" {
		u, err := url.Parse(origin)
		if err != nil || u.Host != r.Host {
			return true
		}
	}
	return false
}

func (s *Server) clientIP(r *http.Request) string {
	if s.cfg.TrustProxyHeaders {
		if ip := r.Header.Get("X-Real-IP"); ip != "" {
			return ip
		}
	}
	host, _, err := net.SplitHostPort(r.RemoteAddr)
	if err != nil {
		return r.RemoteAddr
	}
	return host
}
