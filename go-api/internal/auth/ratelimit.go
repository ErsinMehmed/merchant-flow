package auth

import (
	"sync"
	"time"
)

// limiter counts failed login attempts per key in fixed windows. It is in
// memory, which is enough for the single API instance in this deployment; a
// horizontally scaled API would move this to a shared store.
type limiter struct {
	mu      sync.Mutex
	max     int
	window  time.Duration
	now     func() time.Time
	entries map[string]*attempts
}

type attempts struct {
	start    time.Time
	failures int
}

func newLimiter(max int, period time.Duration) *limiter {
	return &limiter{max: max, window: period, now: time.Now, entries: map[string]*attempts{}}
}

// blocked reports whether the key has used up its attempts, and for how long.
func (l *limiter) blocked(key string) (bool, time.Duration) {
	l.mu.Lock()
	defer l.mu.Unlock()

	e, ok := l.entries[key]
	if !ok {
		return false, 0
	}
	elapsed := l.now().Sub(e.start)
	if elapsed >= l.window {
		delete(l.entries, key)
		return false, 0
	}
	if e.failures >= l.max {
		return true, l.window - elapsed
	}
	return false, 0
}

func (l *limiter) fail(key string) {
	l.mu.Lock()
	defer l.mu.Unlock()

	e, ok := l.entries[key]
	if !ok || l.now().Sub(e.start) >= l.window {
		l.entries[key] = &attempts{start: l.now(), failures: 1}
		return
	}
	e.failures++
}

func (l *limiter) reset(key string) {
	l.mu.Lock()
	defer l.mu.Unlock()
	delete(l.entries, key)
}

// prune drops expired windows so the map does not grow without bound.
func (l *limiter) prune() {
	l.mu.Lock()
	defer l.mu.Unlock()
	for key, e := range l.entries {
		if l.now().Sub(e.start) >= l.window {
			delete(l.entries, key)
		}
	}
}
