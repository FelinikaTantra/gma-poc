<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Omnichannel Dashboard</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/css/dashboard.css">
    
    <!-- React & ReactDOM CDN -->
    <script crossorigin src="https://unpkg.com/react@18/umd/react.development.js"></script>
    <script crossorigin src="https://unpkg.com/react-dom@18/umd/react-dom.development.js"></script>
    <!-- Babel CDN for JSX -->
    <script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>
    <div id="app"></div>

    <script type="text/babel">
        const { useState, useEffect, useRef } = React;

        const useApi = (url, options = {}) => {
            const [data, setData] = useState(null);
            const [loading, setLoading] = useState(true);

            const fetchData = () => {
                if (!url) {
                    setLoading(false);
                    return;
                }
                setLoading(true);
                fetch(url, options)
                    .then(res => res.json())
                    .then(resData => {
                        setData(resData);
                        setLoading(false);
                    })
                    .catch(err => {
                        console.error(err);
                        setLoading(false);
                    });
            };

            useEffect(() => {
                fetchData();
            }, [url]);

            return { data, loading, setData, refetch: fetchData };
        };

        @include('components.settings')
        @include('components.chat')
        @include('components.users')

        const App = () => {
            const getInitialView = () => {
                if (window.location.pathname.includes('/settings')) return 'settings';
                if (window.location.pathname.includes('/users')) return 'users';
                return 'chat';
            };
            const [view, setView] = useState(getInitialView());
            const [activeRole, setActiveRole] = useState('');
            const [roles, setRoles] = useState([]);
            const [users, setUsers] = useState([]);
            const [loadingRoles, setLoadingRoles] = useState(true);
            const [loadingUsers, setLoadingUsers] = useState(true);

            const fetchRoles = () => {
                setLoadingRoles(true);
                fetch('/api/roles')
                    .then(res => res.json())
                    .then(data => {
                        setRoles(data);
                        setLoadingRoles(false);
                    })
                    .catch(() => setLoadingRoles(false));
            };

            const fetchUsers = () => {
                setLoadingUsers(true);
                fetch('/api/users')
                    .then(res => res.json())
                    .then(data => {
                        setUsers(data);
                        setLoadingUsers(false);
                    })
                    .catch(() => setLoadingUsers(false));
            };

            useEffect(() => {
                fetchRoles();
                fetchUsers();

                const handlePopState = () => {
                    setView(getInitialView());
                };
                window.addEventListener('popstate', handlePopState);
                return () => window.removeEventListener('popstate', handlePopState);
            }, []);

            useEffect(() => {
                if (roles.length > 0 && !activeRole) {
                    const superAdmin = roles.find(r => r.name === 'Super Admin');
                    setActiveRole(superAdmin ? superAdmin.id : roles[0].id);
                }
            }, [roles]);

            const currentRoleObj = roles.find(r => r.id == activeRole);
            const userPermissions = currentRoleObj ? (currentRoleObj.permissions || []) : [];

            useEffect(() => {
                if (roles.length > 0 && activeRole) {
                    if (view === 'settings' && !userPermissions.includes('settings')) {
                        navigateTo('chat', '/dashboard/inbox');
                    }
                    if (view === 'users' && !userPermissions.includes('users')) {
                        navigateTo('chat', '/dashboard/inbox');
                    }
                }
            }, [view, activeRole, roles]);

            useEffect(() => {
                lucide.createIcons();
            }, [view, activeRole, roles, users]);

            const navigateTo = (newView, url) => {
                window.history.pushState({}, '', url);
                setView(newView);
            };

            return (
                <div className="app-container">
                    <div className="sidebar">
                        <div className="logo">
                            <i data-lucide="message-square" style=@{{width: 24, height: 24}}></i>
                            OmniChat
                        </div>
                        
                        <div style=@{{padding: '1rem', marginBottom: '1rem', borderBottom: '1px solid rgba(255,255,255,0.05)'}}>
                            <div style=@{{fontSize: '0.75rem', color: 'var(--text-muted)', marginBottom: '0.5rem'}}>ACTIVE ROLE</div>
                            <select 
                                className="form-control" 
                                value={activeRole} 
                                onChange={e => {
                                    setActiveRole(e.target.value);
                                }}
                                style=@{{padding: '0.5rem', fontSize: '0.875rem'}}
                            >
                                {roles.map(r => (
                                    <option key={r.id} value={r.id}>{r.name}</option>
                                ))}
                            </select>
                        </div>

                        {userPermissions.includes('inbox') && (
                            <div className={`nav-item ${view === 'chat' ? 'active' : ''}`} onClick={() => navigateTo('chat', '/dashboard/inbox')}>
                                <i data-lucide="inbox" style=@{{width: 20, height: 20}}></i>
                                Unified Inbox
                            </div>
                        )}
                        {userPermissions.includes('settings') && (
                            <div className={`nav-item ${view === 'settings' ? 'active' : ''}`} onClick={() => navigateTo('settings', '/dashboard/settings')}>
                                <i data-lucide="settings" style=@{{width: 20, height: 20}}></i>
                                Settings & Master Data
                            </div>
                        )}
                        {userPermissions.includes('users') && (
                            <div className={`nav-item ${view === 'users' ? 'active' : ''}`} onClick={() => navigateTo('users', '/dashboard/users')}>
                                <i data-lucide="users" style=@{{width: 20, height: 20}}></i>
                                User & Role Management
                            </div>
                        )}
                        
                        <div style=@{{ marginTop: 'auto', paddingTop: '2rem' }}>
                            <form method="POST" action="/logout">
                                <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                                <button type="submit" className="nav-item" style=@{{background: 'transparent', border: 'none', width: '100%', textAlign: 'left', color: 'var(--text-muted)', cursor: 'pointer', fontFamily: 'inherit'}}>
                                    <i data-lucide="log-out" style=@{{width: 20, height: 20}}></i>
                                    Logout
                                </button>
                            </form>
                        </div>
                    </div>
                    {view === 'chat' && <ChatView />}
                    {view === 'settings' && <SettingsView />}
                    {view === 'users' && <UserRoleView roles={roles} fetchRoles={fetchRoles} users={users} fetchUsers={fetchUsers} />}
                </div>
            );
        };

        const root = ReactDOM.createRoot(document.getElementById('app'));
        root.render(<App />);
    </script>
</body>
</html>
