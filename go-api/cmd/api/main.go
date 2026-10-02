// Command api serves the merchant portal API over the database shared with
// the PHP admin application.
package main

import (
	"context"
	"errors"
	"log/slog"
	"net/http"
	"os"
	"os/signal"
	"syscall"
	"time"

	"github.com/ErsinMehmed/merchant-flow/go-api/internal/auth"
	"github.com/ErsinMehmed/merchant-flow/go-api/internal/charge"
	"github.com/ErsinMehmed/merchant-flow/go-api/internal/config"
	"github.com/ErsinMehmed/merchant-flow/go-api/internal/database"
	"github.com/ErsinMehmed/merchant-flow/go-api/internal/httpapi"
	"github.com/ErsinMehmed/merchant-flow/go-api/internal/idempotency"
	"github.com/ErsinMehmed/merchant-flow/go-api/internal/profile"
)

const cleanupInterval = 10 * time.Minute

func main() {
	slog.SetDefault(slog.New(slog.NewJSONHandler(os.Stdout, nil)))

	if err := run(); err != nil {
		slog.Error("API stopped", "error", err)
		os.Exit(1)
	}
}

func run() error {
	cfg, err := config.Load()
	if err != nil {
		return err
	}

	db, err := database.Open(cfg.Database)
	if err != nil {
		return err
	}
	defer db.Close()

	authService := auth.NewService(db, cfg.SessionTTL)
	server := &http.Server{
		Addr:              cfg.Addr,
		Handler:           httpapi.New(cfg, db, authService, charge.NewService(db), profile.NewService(db)).Handler(),
		ReadHeaderTimeout: 5 * time.Second,
		ReadTimeout:       10 * time.Second,
		WriteTimeout:      15 * time.Second,
		IdleTimeout:       60 * time.Second,
	}

	ctx, stop := signal.NotifyContext(context.Background(), syscall.SIGINT, syscall.SIGTERM)
	defer stop()

	go cleanup(ctx, func(ctx context.Context) {
		if n, err := authService.Cleanup(ctx); err != nil {
			slog.Warn("session cleanup failed", "error", err)
		} else if n > 0 {
			slog.Info("expired sessions removed", "count", n)
		}
		if _, err := idempotency.Cleanup(ctx, db, time.Now().UTC()); err != nil {
			slog.Warn("idempotency cleanup failed", "error", err)
		}
	})

	stopped := make(chan error, 1)
	go func() {
		slog.Info("API listening", "address", server.Addr)
		stopped <- server.ListenAndServe()
	}()

	select {
	case err := <-stopped:
		if errors.Is(err, http.ErrServerClosed) {
			return nil
		}
		return err
	case <-ctx.Done():
		shutdownCtx, cancel := context.WithTimeout(context.Background(), 10*time.Second)
		defer cancel()
		return server.Shutdown(shutdownCtx)
	}
}

// cleanup removes expired sessions and idempotency records periodically.
func cleanup(ctx context.Context, task func(context.Context)) {
	ticker := time.NewTicker(cleanupInterval)
	defer ticker.Stop()
	for {
		select {
		case <-ctx.Done():
			return
		case <-ticker.C:
			taskCtx, cancel := context.WithTimeout(ctx, time.Minute)
			task(taskCtx)
			cancel()
		}
	}
}
