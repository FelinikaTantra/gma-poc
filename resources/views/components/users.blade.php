const UserRoleView = ({ roles, fetchRoles, users, fetchUsers }) => {
    const [userForm, setUserForm] = React.useState({ id: null, name: '', email: '', password: '', role_id: '' });
    const [isEditing, setIsEditing] = React.useState(false);
    const [newRoleName, setNewRoleName] = React.useState('');
    const [matrix, setMatrix] = React.useState([]);

    React.useEffect(() => {
        if (roles.length > 0) {
            setMatrix(roles.map(r => ({ id: r.id, name: r.name, permissions: r.permissions || [] })));
        }
    }, [roles]);

    React.useEffect(() => {
        lucide.createIcons();
    }, [roles, users, matrix]);

    const handleCheckboxChange = (roleId, permissionKey) => {
        setMatrix(prev => prev.map(r => {
            if (r.id === roleId) {
                const alreadyHas = r.permissions.includes(permissionKey);
                const newPerms = alreadyHas 
                    ? r.permissions.filter(p => p !== permissionKey)
                    : [...r.permissions, permissionKey];
                return { ...r, permissions: newPerms };
            }
            return r;
        }));
    };

    const saveMatrix = () => {
        fetch('/api/roles/matrix', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ matrix })
        })
        .then(res => res.json())
        .then(data => {
            alert('Access control matrix saved successfully!');
            fetchRoles();
        })
        .catch(err => {
            console.error(err);
            alert('Failed to save matrix.');
        });
    };

    const saveUser = (e) => {
        e.preventDefault();
        const method = isEditing ? 'PUT' : 'POST';
        const url = isEditing ? `/api/users/${userForm.id}` : '/api/users';
        
        fetch(url, {
            method: method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(userForm)
        })
        .then(res => {
            if (!res.ok) throw new Error('Request failed');
            return res.json();
        })
        .then(() => {
            setUserForm({ id: null, name: '', email: '', password: '', role_id: '' });
            setIsEditing(false);
            fetchUsers();
            alert(isEditing ? 'User updated successfully!' : 'User created successfully!');
        })
        .catch(() => alert('Failed to save user. Check email uniqueness or input validation.'));
    };

    const editUser = (user) => {
        setUserForm({
            id: user.id,
            name: user.name,
            email: user.email,
            password: '',
            role_id: user.role_id || ''
        });
        setIsEditing(true);
    };

    const deleteUser = (id) => {
        if (!confirm('Are you sure you want to delete this user?')) return;
        fetch(`/api/users/${id}`, {
            method: 'DELETE'
        })
        .then(res => {
            if (!res.ok) {
                return res.json().then(data => { throw new Error(data.message || 'Failed') });
            }
            fetchUsers();
            alert('User deleted successfully!');
        })
        .catch(err => alert(err.message));
    };

    const createRole = (e) => {
        e.preventDefault();
        if (!newRoleName.trim()) return;
        fetch('/api/roles', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name: newRoleName })
        })
        .then(res => res.json())
        .then(() => {
            setNewRoleName('');
            fetchRoles();
            alert('Role created successfully!');
        });
    };

    return (
        <div className="main-content">
            <div className="page-header">User & Role Management</div>
            <div className="settings-container" style=@{{display: 'flex', flexDirection: 'column', gap: '2rem'}}>
                {/* 1. Access Control Matrix Card */}
                <div className="card">
                    <div className="card-title" style=@{{display: 'flex', justifyContent: 'space-between', alignItems: 'center'}}>
                        <span>Access Control Matrix</span>
                        <button className="btn" style=@{{padding: '0.4rem 1rem', fontSize: '0.85rem'}} onClick={saveMatrix}>Save Access Matrix</button>
                    </div>
                    <div style=@{{overflowX: 'auto'}}>
                        <table style=@{{width: '100%', borderCollapse: 'collapse', textAlign: 'left', fontSize: '0.9rem'}}>
                            <thead>
                                <tr style=@{{borderBottom: '1px solid var(--border-color)', color: 'var(--text-muted)'}}>
                                    <th style=@{{padding: '0.75rem 1rem'}}>Role Name</th>
                                    <th style=@{{padding: '0.75rem 1rem', textAlign: 'center'}}>Unified Inbox (`inbox`)</th>
                                    <th style=@{{padding: '0.75rem 1rem', textAlign: 'center'}}>Settings (`settings`)</th>
                                    <th style=@{{padding: '0.75rem 1rem', textAlign: 'center'}}>User Management (`users`)</th>
                                </tr>
                            </thead>
                            <tbody>
                                {matrix.map(r => (
                                    <tr key={r.id} style=@{{borderBottom: '1px solid rgba(255,255,255,0.05)', verticalAlign: 'middle'}}>
                                        <td style=@{{padding: '0.75rem 1rem', fontWeight: 600, color: 'white'}}>{r.name}</td>
                                        <td style=@{{padding: '0.75rem 1rem', textAlign: 'center'}}>
                                            <input 
                                                type="checkbox" 
                                                checked={r.permissions.includes('inbox')}
                                                onChange={() => handleCheckboxChange(r.id, 'inbox')}
                                                style=@{{width: '18px', height: '18px', cursor: 'pointer'}}
                                            />
                                        </td>
                                        <td style=@{{padding: '0.75rem 1rem', textAlign: 'center'}}>
                                            <input 
                                                type="checkbox" 
                                                checked={r.permissions.includes('settings')}
                                                onChange={() => handleCheckboxChange(r.id, 'settings')}
                                                style=@{{width: '18px', height: '18px', cursor: 'pointer'}}
                                            />
                                        </td>
                                        <td style=@{{padding: '0.75rem 1rem', textAlign: 'center'}}>
                                            <input 
                                                type="checkbox" 
                                                checked={r.permissions.includes('users')}
                                                onChange={() => handleCheckboxChange(r.id, 'users')}
                                                style=@{{width: '18px', height: '18px', cursor: 'pointer'}}
                                            />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    
                    {/* Add Role Mini-form */}
                    <form onSubmit={createRole} style=@{{display: 'flex', gap: '1rem', marginTop: '1.5rem', alignItems: 'center', borderTop: '1px dashed var(--border-color)', paddingTop: '1.25rem'}}>
                        <input 
                            type="text" 
                            className="form-control" 
                            style=@{{maxWidth: '250px', padding: '0.5rem'}} 
                            placeholder="New Role Name (e.g. Supervisor)" 
                            value={newRoleName}
                            onChange={e => setNewRoleName(e.target.value)}
                        />
                        <button type="submit" className="btn" style=@{{padding: '0.5rem 1.25rem'}}>Add Role</button>
                    </form>
                </div>

                {/* 2. Users Management Grid */}
                <div style=@{{display: 'grid', gridTemplateColumns: '1.8fr 1.2fr', gap: '1.5rem'}}>
                    {/* Users List */}
                    <div className="card">
                        <div className="card-title">Users List</div>
                        <div style=@{{overflowX: 'auto'}}>
                            <table style=@{{width: '100%', borderCollapse: 'collapse', textAlign: 'left', fontSize: '0.875rem'}}>
                                <thead>
                                    <tr style=@{{borderBottom: '1px solid var(--border-color)', color: 'var(--text-muted)'}}>
                                        <th style=@{{padding: '0.5rem'}}>Name</th>
                                        <th style=@{{padding: '0.5rem'}}>Email</th>
                                        <th style=@{{padding: '0.5rem'}}>Role</th>
                                        <th style=@{{padding: '0.5rem', textAlign: 'right'}}>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {users.map(u => (
                                        <tr key={u.id} style=@{{borderBottom: '1px solid rgba(255,255,255,0.05)'}}>
                                            <td style=@{{padding: '0.75rem 0.5rem', color: 'white', fontWeight: 500}}>{u.name}</td>
                                            <td style=@{{padding: '0.75rem 0.5rem', color: 'var(--text-muted)'}}>{u.email}</td>
                                            <td style=@{{padding: '0.75rem 0.5rem'}}>
                                                <span style=@{{background: 'rgba(255,255,255,0.1)', padding: '2px 8px', borderRadius: '4px', fontSize: '0.75rem'}}>
                                                    {u.role ? u.role.name : 'No Role'}
                                                </span>
                                            </td>
                                            <td style=@{{padding: '0.75rem 0.5rem', textAlign: 'right', display: 'flex', gap: '0.5rem', justifyContent: 'flex-end'}}>
                                                <button onClick={() => editUser(u)} style=@{{background: 'none', border: 'none', color: 'var(--accent)', cursor: 'pointer', fontSize: '0.8rem'}}>Edit</button>
                                                <button onClick={() => deleteUser(u.id)} style=@{{background: 'none', border: 'none', color: 'var(--danger)', cursor: 'pointer', fontSize: '0.8rem'}}>Delete</button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {/* User Form */}
                    <div className="card">
                        <div className="card-title">{isEditing ? 'Edit User' : 'Create New User'}</div>
                        <form onSubmit={saveUser} style=@{{display: 'flex', flexDirection: 'column', gap: '1rem'}}>
                            <div className="form-group" style=@{{marginBottom: '0px'}}>
                                <label>Full Name</label>
                                <input type="text" className="form-control" style=@{{padding: '0.5rem'}} required value={userForm.name} onChange={e => setUserForm({...userForm, name: e.target.value})} />
                            </div>
                            <div className="form-group" style=@{{marginBottom: '0px'}}>
                                <label>Email Address</label>
                                <input type="email" className="form-control" style=@{{padding: '0.5rem'}} required value={userForm.email} onChange={e => setUserForm({...userForm, email: e.target.value})} />
                            </div>
                            <div className="form-group" style=@{{marginBottom: '0px'}}>
                                <label>Password {isEditing && <span style=@{{fontSize: '0.75rem', color: 'var(--text-muted)'}}>(leave blank to keep current)</span>}</label>
                                <input type="password" className="form-control" style=@{{padding: '0.5rem'}} required={!isEditing} value={userForm.password} onChange={e => setUserForm({...userForm, password: e.target.value})} />
                            </div>
                            <div className="form-group" style=@{{marginBottom: '0px'}}>
                                <label>Role</label>
                                <select className="form-control" style=@{{padding: '0.5rem'}} required value={userForm.role_id} onChange={e => setUserForm({...userForm, role_id: e.target.value})}>
                                    <option value="">Select Role</option>
                                    {roles.map(r => (
                                        <option key={r.id} value={r.id}>{r.name}</option>
                                    ))}
                                </select>
                            </div>
                            <div style=@{{display: 'flex', gap: '0.5rem', marginTop: '0.5rem'}}>
                                <button type="submit" className="btn" style=@{{flex: 1}}>{isEditing ? 'Save Changes' : 'Create User'}</button>
                                {isEditing && (
                                    <button type="button" className="btn" style=@{{background: 'rgba(255,255,255,0.1)', color: 'white'}} onClick={() => {
                                        setUserForm({ id: null, name: '', email: '', password: '', role_id: '' });
                                        setIsEditing(false);
                                    }}>Cancel</button>
                                )}
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    );
};
