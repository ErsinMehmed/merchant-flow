import type { User } from './api/types';
import { ToastProvider } from './components/Toasts';
import { TopBar } from './components/TopBar';
import { useRoute } from './lib/useRoute';
import { ChargesPage } from './pages/ChargesPage';
import { LoginPage } from './pages/LoginPage';
import { ProfilePage } from './pages/ProfilePage';
import { useSession } from './session/SessionContext';

export function App() {
  const { state } = useSession();

  if (state.status === 'loading') {
    return (
      <div className="splash" role="status">
        Loading…
      </div>
    );
  }
  if (state.status === 'signed-out') {
    return <LoginPage notice={state.notice} />;
  }
  return <SignedIn user={state.user} />;
}

function SignedIn({ user }: { user: User }) {
  const [route, navigate] = useRoute();

  return (
    <ToastProvider>
      <div className="app">
        <TopBar user={user} onNavigate={navigate} />
        <main className="content">
          {route === 'profile' ? (
            <ProfilePage user={user} onBack={() => navigate('charges')} />
          ) : (
            <ChargesPage user={user} />
          )}
        </main>
      </div>
    </ToastProvider>
  );
}
