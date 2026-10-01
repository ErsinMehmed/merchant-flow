// Package database opens the shared MySQL database. The schema belongs to the
// PHP application; this service never creates or alters tables.
package database

import (
	"context"
	"database/sql"
	"errors"
	"fmt"
	"net"
	"time"

	"github.com/go-sql-driver/mysql"

	"github.com/ErsinMehmed/merchant-flow/go-api/internal/config"
)

// Querier is satisfied by *sql.DB and *sql.Tx, so store functions can run
// inside or outside a transaction.
type Querier interface {
	ExecContext(ctx context.Context, query string, args ...any) (sql.Result, error)
	QueryContext(ctx context.Context, query string, args ...any) (*sql.Rows, error)
	QueryRowContext(ctx context.Context, query string, args ...any) *sql.Row
}

func Open(cfg config.Database) (*sql.DB, error) {
	mc := mysql.NewConfig()
	mc.User = cfg.User
	mc.Passwd = cfg.Password
	mc.Net = "tcp"
	mc.Addr = net.JoinHostPort(cfg.Host, cfg.Port)
	mc.DBName = cfg.Name
	mc.ParseTime = true
	mc.Loc = time.UTC
	mc.Timeout = 3 * time.Second
	mc.ReadTimeout = 10 * time.Second
	mc.WriteTimeout = 10 * time.Second

	return OpenDSN(mc.FormatDSN())
}

// OpenDSN is used by integration tests, which receive a ready-made DSN.
func OpenDSN(dsn string) (*sql.DB, error) {
	mc, err := mysql.ParseDSN(dsn)
	if err != nil {
		return nil, fmt.Errorf("parse database DSN: %w", err)
	}
	// Laravel writes timestamps in UTC; read them back the same way.
	mc.ParseTime = true
	mc.Loc = time.UTC

	connector, err := mysql.NewConnector(mc)
	if err != nil {
		return nil, err
	}
	db := sql.OpenDB(connector)
	db.SetMaxOpenConns(20)
	db.SetMaxIdleConns(10)
	db.SetConnMaxLifetime(3 * time.Minute)
	return db, nil
}

// InTx runs fn in a transaction, committing on success and rolling back on
// error or panic. InnoDB can pick a transaction as a deadlock victim under
// concurrent writes; that transaction was rolled back completely, so it is
// safe to run fn again from the start.
func InTx(ctx context.Context, db *sql.DB, fn func(tx *sql.Tx) error) error {
	const attempts = 3
	var err error
	for range attempts {
		if err = runTx(ctx, db, fn); !isDeadlock(err) {
			return err
		}
	}
	return err
}

func runTx(ctx context.Context, db *sql.DB, fn func(tx *sql.Tx) error) (err error) {
	tx, err := db.BeginTx(ctx, nil)
	if err != nil {
		return fmt.Errorf("begin transaction: %w", err)
	}
	defer func() {
		if p := recover(); p != nil {
			_ = tx.Rollback()
			panic(p)
		}
		if err != nil {
			_ = tx.Rollback()
		}
	}()

	if err = fn(tx); err != nil {
		return err
	}
	return tx.Commit()
}

// IsDuplicateKey reports a unique index violation (MySQL error 1062).
func IsDuplicateKey(err error) bool {
	return hasCode(err, 1062)
}

func isDeadlock(err error) bool {
	return hasCode(err, 1213)
}

func hasCode(err error, code uint16) bool {
	var mysqlErr *mysql.MySQLError
	return errors.As(err, &mysqlErr) && mysqlErr.Number == code
}
