import { ChargesPage } from './pages/ChargesPage';
import { LoginPage } from './pages/LoginPage';
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
  return <ChargesPage user={state.user} />;
}
