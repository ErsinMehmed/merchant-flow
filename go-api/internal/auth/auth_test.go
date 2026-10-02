package auth

import (
	"strings"
	"testing"
	"time"
)

func TestPasswordMatchesLaravelHashes(t *testing.T) {
	// timingHash was produced by PHP's password_hash(), which Laravel uses.
	if !passwordMatches(timingHash, "merchant-flow-timing-equaliser") {
		t.Fatal("expected the PHP-generated $2y$ hash to verify")
	}
	if passwordMatches(timingHash, "merchant-flow-timing-equaliser ") {
		t.Fatal("a different password must not verify")
	}
	if passwordMatches("not-a-bcrypt-hash", "anything") {
		t.Fatal("an invalid hash must not verify")
	}
}

func TestPasswordProblemFollowsTheLaravelDefaults(t *testing.T) {
	accepted := []string{"Test-Passw0rd!", "Пароль-Дълга1", "Spaced Out 9a"}
	for _, p := range accepted {
		if problem := PasswordProblem(p); problem != "" {
			t.Errorf("%q was refused: %s", p, problem)
		}
	}

	refused := map[string]string{
		"Sh0rt!":                   "must be at least 8 characters",
		"all-lower-case-1":         "must contain upper and lower case letters",
		"ALL-UPPER-CASE-1":         "must contain upper and lower case letters",
		"No-Numbers-Here":          "must contain a number",
		"NoSymbols123":             "must contain a symbol",
		strings.Repeat("Aa1!", 19): "must be at most 72 bytes",
	}
	for p, want := range refused {
		if got := PasswordProblem(p); got != want {
			t.Errorf("%q: got %q, want %q", p, got, want)
		}
	}
}

func TestTokensAreRandomAndOnlyTheirHashIsStored(t *testing.T) {
	a, b := newToken(), newToken()
	if a == b {
		t.Fatal("tokens must be random")
	}
	if !wellFormed(a) || wellFormed("mf_short") || wellFormed("x"+a[1:]) {
		t.Fatal("token format check is wrong")
	}
	if h := hashToken(a); len(h) != 64 || h == a || h != hashToken(a) {
		t.Fatalf("unexpected hash %q", h)
	}
}

func TestLimiterLocksOutAfterRepeatedFailures(t *testing.T) {
	now := time.Date(2026, 10, 1, 12, 0, 0, 0, time.UTC)
	l := newLimiter(3, time.Minute)
	l.now = func() time.Time { return now }

	for i := 0; i < 3; i++ {
		if blocked, _ := l.blocked("k"); blocked {
			t.Fatalf("blocked after %d failures", i)
		}
		l.fail("k")
	}
	blocked, retry := l.blocked("k")
	if !blocked || retry != time.Minute {
		t.Fatalf("expected a one minute lock-out, got %v %v", blocked, retry)
	}
	if blocked, _ := l.blocked("other"); blocked {
		t.Fatal("keys must be independent")
	}

	now = now.Add(time.Minute)
	if blocked, _ := l.blocked("k"); blocked {
		t.Fatal("the window should have expired")
	}
}

func TestLimiterResetClearsFailures(t *testing.T) {
	l := newLimiter(2, time.Minute)
	l.fail("k")
	l.fail("k")
	l.reset("k")
	if blocked, _ := l.blocked("k"); blocked {
		t.Fatal("reset should clear the failures")
	}
}

func TestTruncateKeepsValidUTF8(t *testing.T) {
	if got := truncate("aé", 2); got != "a" {
		t.Fatalf("truncate = %q", got)
	}
}
