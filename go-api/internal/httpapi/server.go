// Package httpapi is the HTTP interface of the merchant portal API.
package httpapi

import (
	"context"
	"database/sql"
	"net/http"
	"time"

	"github.com/ErsinMehmed/merchant-flow/go-api/internal/auth"
	"github.com/ErsinMehmed/merchant-flow/go-api/internal/charge"
	"github.com/ErsinMehmed/merchant-flow/go-api/internal/config"
	"github.com/ErsinMehmed/merchant-flow/go-api/internal/profile"
)

type Server struct {
	cfg     config.Config
	db      *sql.DB
	auth    *auth.Service
	charges *charge.Service
	profile *profile.Service
	now     func() time.Time
}

func New(cfg config.Config, db *sql.DB, authService *auth.Service, charges *charge.Service, profiles *profile.Service) *Server {
	return &Server{
		cfg:     cfg,
		db:      db,
		auth:    authService,
		charges: charges,
		profile: profiles,
		now:     func() time.Time { return time.Now().UTC() },
	}
}

func (s *Server) Handler() http.Handler {
	mux := http.NewServeMux()

	mux.HandleFunc("GET /api/health", s.health)

	mux.HandleFunc("POST /api/v1/auth/login", s.login)
	mux.HandleFunc("POST /api/v1/auth/logout", s.logout)
	mux.HandleFunc("GET /api/v1/me", s.authenticated(s.me))
	mux.HandleFunc("POST /api/v1/me/password", s.authenticated(s.changePassword))
	mux.HandleFunc("GET "+avatarPath, s.authenticated(s.avatar))
	mux.HandleFunc("PUT "+avatarPath, s.authenticated(s.updateAvatar))
	mux.HandleFunc("DELETE "+avatarPath, s.authenticated(s.deleteAvatar))

	mux.HandleFunc("GET /api/v1/accounts", s.authenticated(s.listAccounts))

	mux.HandleFunc("GET /api/v1/charges", s.authenticated(s.listCharges))
	mux.HandleFunc("POST /api/v1/charges", s.authenticated(s.createCharge))
	mux.HandleFunc("GET /api/v1/charges/{id}", s.authenticated(s.getCharge))
	mux.HandleFunc("POST /api/v1/charges/{id}/capture", s.authenticated(s.captureCharge))
	mux.HandleFunc("POST /api/v1/charges/{id}/refund", s.authenticated(s.refundCharge))
	mux.HandleFunc("POST /api/v1/charges/{id}/void", s.authenticated(s.voidCharge))

	mux.HandleFunc("/api/", func(w http.ResponseWriter, r *http.Request) { writeNotFound(w) })

	return observe(secureHeaders(requireJSON(mux)))
}

func (s *Server) health(w http.ResponseWriter, r *http.Request) {
	ctx, cancel := context.WithTimeout(r.Context(), 3*time.Second)
	defer cancel()

	if err := s.db.PingContext(ctx); err != nil {
		writeJSON(w, http.StatusServiceUnavailable, map[string]string{"status": "unavailable", "service": "go-api"})
		return
	}
	writeJSON(w, http.StatusOK, map[string]string{"status": "ok", "service": "go-api"})
}
