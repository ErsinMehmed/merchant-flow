package auth

import (
	"crypto/rand"
	"crypto/sha256"
	"encoding/base64"
	"encoding/hex"
	"strings"
)

const tokenPrefix = "mf_"

// newToken returns a random session token. Only its hash is stored, so a
// database leak does not expose usable tokens.
func newToken() string {
	b := make([]byte, 32)
	// crypto/rand.Read never returns an error on supported platforms.
	_, _ = rand.Read(b)
	return tokenPrefix + base64.RawURLEncoding.EncodeToString(b)
}

func hashToken(token string) string {
	sum := sha256.Sum256([]byte(token))
	return hex.EncodeToString(sum[:])
}

// wellFormed rejects obviously invalid tokens before touching the database.
func wellFormed(token string) bool {
	return strings.HasPrefix(token, tokenPrefix) && len(token) == len(tokenPrefix)+43
}
