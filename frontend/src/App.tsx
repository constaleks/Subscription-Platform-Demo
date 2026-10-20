import { useEffect } from 'react';
import { RouterProvider } from 'react-router-dom';
import { router } from './router';
import { useAuthStore } from './features/user/auth/authStore';

function App() {
    const status = useAuthStore((s) => s.status);
    const fetchCurrentUser = useAuthStore((s) => s.fetchCurrentUser);

    useEffect(() => {
        if (status === 'idle') {
            fetchCurrentUser();
        }
    }, [status, fetchCurrentUser]);

    return <RouterProvider router={router} />;
}

export default App;
