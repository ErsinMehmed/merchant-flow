package auth

import (
	"unicode"
	"unicode/utf8"

	"golang.org/x/crypto/bcrypt"
)

// timingHash is a bcrypt hash of a throwaway value at Laravel's cost (12).
// Comparing against it when the email is unknown keeps the response time
// close to that of a real account.
const timingHash = "$2y$12$sCXdpMx.PIl/SpRO.oe2oeOFflEwlHsXtgnUuiAmyepcdk5A0CFwW"

// hashCost matches Laravel's default bcrypt rounds.
const hashCost = 12

// MaxPasswordBytes is bcrypt's limit: it only looks at the first 72 bytes, so
// longer input is refused rather than silently truncated.
const MaxPasswordBytes = 72

const minPasswordLength = 8

// passwordMatches checks a password against a hash written by Laravel's bcrypt
// hasher. PHP's "$2y$" prefix is the same algorithm as "$2a$"/"$2b$" and Go's
// bcrypt accepts it as is.
func passwordMatches(hash, password string) bool {
	return bcrypt.CompareHashAndPassword([]byte(hash), []byte(password)) == nil
}

// PasswordProblem applies the rules of Password::defaults() in the PHP
// UserServiceProvider, so both applications accept the same passwords. It
// returns a message for the form, or "" when the password is acceptable.
func PasswordProblem(password string) string {
	if utf8.RuneCountInString(password) < minPasswordLength {
		return "must be at least 8 characters"
	}
	if len(password) > MaxPasswordBytes {
		return "must be at most 72 bytes"
	}

	var lower, upper, number, symbol bool
	for _, r := range password {
		switch {
		case unicode.IsLower(r):
			lower = true
		case unicode.IsUpper(r):
			upper = true
		case unicode.IsNumber(r):
			number = true
		// Laravel's symbols() rule: \p{Z}, \p{S} or \p{P}.
		case unicode.Is(unicode.Z, r), unicode.IsSymbol(r), unicode.IsPunct(r):
			symbol = true
		}
	}
	switch {
	case !lower || !upper:
		return "must contain upper and lower case letters"
	case !number:
		return "must contain a number"
	case !symbol:
		return "must contain a symbol"
	}
	return ""
}
