// Package config reads the API settings from the environment.
package config

import (
	"fmt"
	"os"
	"strconv"
	"time"
)

type Config struct {
	Addr     string
	Database Database
	// SessionTTL is the absolute lifetime of a merchant portal session.
	SessionTTL time.Duration
	// CookieSecure marks the session cookie Secure; required behind HTTPS,
	// off for the plain-HTTP localhost demo.
	CookieSecure bool
	// TrustProxyHeaders reads the client address from X-Real-IP. Only safe
	// when the API is reachable through the reverse proxy alone.
	TrustProxyHeaders bool
}

type Database struct {
	Host     string
	Port     string
	Name     string
	User     string
	Password string
}

func Load() (Config, error) {
	cfg := Config{
		Addr: env("API_ADDR", ":8081"),
		Database: Database{
			Host:     env("DB_HOST", "127.0.0.1"),
			Port:     env("DB_PORT", "3306"),
			Name:     os.Getenv("DB_DATABASE"),
			User:     os.Getenv("DB_USERNAME"),
			Password: os.Getenv("DB_PASSWORD"),
		},
	}

	var err error
	if cfg.SessionTTL, err = time.ParseDuration(env("SESSION_TTL", "8h")); err != nil || cfg.SessionTTL <= 0 {
		return Config{}, fmt.Errorf("SESSION_TTL must be a positive duration")
	}
	if cfg.CookieSecure, err = strconv.ParseBool(env("COOKIE_SECURE", "true")); err != nil {
		return Config{}, fmt.Errorf("COOKIE_SECURE must be a boolean")
	}
	if cfg.TrustProxyHeaders, err = strconv.ParseBool(env("TRUST_PROXY_HEADERS", "true")); err != nil {
		return Config{}, fmt.Errorf("TRUST_PROXY_HEADERS must be a boolean")
	}
	if cfg.Database.Name == "" || cfg.Database.User == "" {
		return Config{}, fmt.Errorf("DB_DATABASE and DB_USERNAME are required")
	}

	return cfg, nil
}

func env(key, fallback string) string {
	if value, ok := os.LookupEnv(key); ok && value != "" {
		return value
	}
	return fallback
}
