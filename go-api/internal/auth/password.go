package auth

import "golang.org/x/crypto/bcrypt"

// timingHash is a bcrypt hash of a throwaway value at Laravel's cost (12).
// Comparing against it when the email is unknown keeps the response time
// close to that of a real account.
const timingHash = "$2y$12$sCXdpMx.PIl/SpRO.oe2oeOFflEwlHsXtgnUuiAmyepcdk5A0CFwW"

// passwordMatches checks a password against a hash written by Laravel's bcrypt
// hasher. PHP's "$2y$" prefix is the same algorithm as "$2a$"/"$2b$" and Go's
// bcrypt accepts it as is.
func passwordMatches(hash, password string) bool {
	return bcrypt.CompareHashAndPassword([]byte(hash), []byte(password)) == nil
}
