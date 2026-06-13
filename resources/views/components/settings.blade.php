const SettingsView = () => {
    const [activeTab, setActiveTab] = useState('master');
    
    // Knowledge Base state
    const { data: kbData, refetch: refetchKb } = useApi('/api/knowledge-base');
    const [kbForm, setKbForm] = useState({ category: 'Company Profile', title: '', content: '' });

    // Products state
    const [masterSubTab, setMasterSubTab] = useState('kb');
    const { data: productsData, refetch: refetchProducts } = useApi('/api/products');
    const [productForm, setProductForm] = useState({
        id: null,
        name: '',
        price: '',
        stock: '',
        external_product_id: '',
        compatibilities: [{ brand: '', model: '', year: '' }]
    });

    const handleCompatibilityChange = (index, field, value) => {
        const newCompatibilities = [...productForm.compatibilities];
        newCompatibilities[index][field] = value;
        setProductForm({ ...productForm, compatibilities: newCompatibilities });
    };

    const addCompatibilityRow = () => {
        setProductForm({
            ...productForm,
            compatibilities: [...productForm.compatibilities, { brand: '', model: '', year: '' }]
        });
    };

    const removeCompatibilityRow = (index) => {
        const newCompatibilities = productForm.compatibilities.filter((_, i) => i !== index);
        setProductForm({ ...productForm, compatibilities: newCompatibilities });
    };

    const saveProduct = () => {
        if (!productForm.name || !productForm.price || !productForm.stock) {
            alert('Name, Price, and Stock are required.');
            return;
        }
        const method = productForm.id ? 'PUT' : 'POST';
        const url = productForm.id ? `/api/products/${productForm.id}` : '/api/products';
        
        fetch(url, {
            method: method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(productForm)
        })
        .then(res => res.json())
        .then(() => {
            setProductForm({
                id: null,
                name: '',
                price: '',
                stock: '',
                external_product_id: '',
                compatibilities: [{ brand: '', model: '', year: '' }]
            });
            refetchProducts();
            alert('Product saved successfully!');
        })
        .catch(err => {
            console.error(err);
            alert('Failed to save product.');
        });
    };

    const editProduct = (prod) => {
        setProductForm({
            id: prod.id,
            name: prod.name,
            price: prod.price,
            stock: prod.stock,
            external_product_id: prod.external_product_id || '',
            compatibilities: prod.product_compatibilities && prod.product_compatibilities.length > 0
                ? prod.product_compatibilities.map(c => ({ brand: c.vehicle_brand || '', model: c.vehicle_model || '', year: c.vehicle_year || '' }))
                : [{ brand: '', model: '', year: '' }]
        });
    };

    const deleteProduct = (id) => {
        if (!confirm('Are you sure you want to delete this product?')) return;
        fetch(`/api/products/${id}`, {
            method: 'DELETE'
        })
        .then(() => {
            refetchProducts();
            alert('Product deleted successfully!');
        })
        .catch(err => {
            console.error(err);
            alert('Failed to delete product.');
        });
    };

    // Channels & AI Settings state
    const { data: settingsData, refetch: refetchSettings } = useApi('/api/settings');

    // Telegram state
    const [botToken, setBotToken] = useState('');
    const [botUsername, setBotUsername] = useState('');

    // OpenAI state
    const [openAiToken, setOpenAiToken] = useState('');

    useEffect(() => {
        lucide.createIcons();
    }, [activeTab, kbData, settingsData]);

    useEffect(() => {
        if (settingsData && settingsData.channels) {
            const tg = settingsData.channels.find(c => c.name === 'Telegram' || c.type === 'telegram');
            if (tg) {
                const config = tg.config_json || {};
                setBotToken(config.bot_token || '');
                setBotUsername(config.username || '');
            }
        }
        if (settingsData && settingsData.ai_setting) {
            setOpenAiToken(settingsData.ai_setting.openai_token || '');
        }
    }, [settingsData]);

    const saveKb = () => {
        fetch('/api/knowledge-base', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(kbForm)
        }).then(() => {
            setKbForm({ category: 'Company Profile', title: '', content: '' });
            refetchKb();
        });
    };

    const toggleAi = (val) => {
        fetch('/api/settings/ai-toggle', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ full_control: val, openai_token: openAiToken })
        }).then(() => refetchSettings());
    };

    const saveAiSettings = () => {
        fetch('/api/settings/ai-toggle', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                full_control: settingsData.ai_setting.full_control,
                openai_token: openAiToken
            })
        })
        .then(res => res.json())
        .then(() => {
            alert('AI Settings saved successfully!');
            refetchSettings();
        })
        .catch(err => {
            console.error(err);
            alert('Failed to save AI Settings.');
        });
    };

    const [testingConnection, setTestingConnection] = useState(false);
    const [testingOpenAi, setTestingOpenAi] = useState(false);
    const [syncingMessages, setSyncingMessages] = useState(false);

    const saveTelegramChannel = () => {
        if (!settingsData) return;
        const tg = settingsData.channels.find(c => c.name === 'Telegram' || c.type === 'telegram');
        if (!tg) return;

        fetch(`/api/settings/channel/${tg.id}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                status: 'Connected',
                config_json: {
                    bot_token: botToken,
                    username: botUsername
                }
            })
        })
        .then(res => res.json())
        .then(() => {
            alert('Telegram bot configuration saved successfully!');
            refetchSettings();
        })
        .catch(err => {
            console.error(err);
            alert('Failed to save Telegram bot configuration.');
        });
    };

    const testTelegramConnection = () => {
        if (!botToken) {
            alert('Please enter a Bot Token first.');
            return;
        }
        setTestingConnection(true);
        fetch('/api/settings/telegram/test', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ bot_token: botToken })
        })
        .then(res => res.json())
        .then(data => {
            setTestingConnection(false);
            if (data.success) {
                alert(`Success: Connected to ${data.bot.first_name} (${data.bot.username})`);
                if (!botUsername) {
                    setBotUsername(data.bot.username);
                }
            } else {
                alert(`Connection failed: ${data.message}`);
            }
        })
        .catch(err => {
            setTestingConnection(false);
            console.error(err);
            alert('Connection failed: Network error.');
        });
    };

    const testOpenAiConnection = () => {
        if (!openAiToken) {
            alert('Please enter an OpenAI Token first.');
            return;
        }
        setTestingOpenAi(true);
        fetch('/api/settings/openai/test', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ openai_token: openAiToken })
        })
        .then(res => res.json())
        .then(data => {
            setTestingOpenAi(false);
            if (data.success) {
                alert(`Success: ${data.message}`);
            } else {
                alert(`Connection failed: ${data.message}`);
            }
        })
        .catch(err => {
            setTestingOpenAi(false);
            console.error(err);
            alert('Connection failed: Network error.');
        });
    };

    const syncTelegramMessages = () => {
        setSyncingMessages(true);
        fetch('/api/settings/telegram/sync', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        })
        .then(res => res.json())
        .then(data => {
            setSyncingMessages(false);
            if (data.success) {
                alert(data.message);
            } else {
                alert(`Sync failed: ${data.message}`);
            }
        })
        .catch(err => {
            setSyncingMessages(false);
            console.error(err);
            alert('Sync failed: Network error.');
        });
    };

    return (
        <div className="main-content">
            <div className="page-header">Settings & Master Data</div>
            <div className="settings-container">
                <div className="tabs">
                    <div className={`tab ${activeTab === 'master' ? 'active' : ''}`} onClick={() => setActiveTab('master')}>Master Data</div>
                    <div className={`tab ${activeTab === 'channels' ? 'active' : ''}`} onClick={() => setActiveTab('channels')}>Channel Integration</div>
                    <div className={`tab ${activeTab === 'ai' ? 'active' : ''}`} onClick={() => setActiveTab('ai')}>AI Settings</div>
                </div>

                {activeTab === 'master' && (
                    <div>
                        <div style=@{{display: 'flex', gap: '0.5rem', marginBottom: '1.5rem', background: 'rgba(255,255,255,0.02)', padding: '0.35rem', borderRadius: '0.5rem', width: 'fit-content'}}>
                            <button className="btn" style=@{{padding: '0.35rem 1rem', fontSize: '0.85rem', background: masterSubTab === 'kb' ? 'var(--accent)' : 'transparent', border: masterSubTab === 'kb' ? 'none' : '1px solid rgba(255,255,255,0.1)'}} onClick={() => setMasterSubTab('kb')}>Knowledge Base</button>
                            <button className="btn" style=@{{padding: '0.35rem 1rem', fontSize: '0.85rem', background: masterSubTab === 'products' ? 'var(--accent)' : 'transparent', border: masterSubTab === 'products' ? 'none' : '1px solid rgba(255,255,255,0.1)'}} onClick={() => setMasterSubTab('products')}>Products Catalog</button>
                        </div>

                        {masterSubTab === 'products' ? (
                            <div>
                                <div className="card">
                                    <div className="card-title">{productForm.id ? 'Edit Product' : 'Add Product'}</div>
                                    <div className="form-group">
                                        <label>Product Name</label>
                                        <input type="text" className="form-control" placeholder="e.g. Oli Motul Scooter" value={productForm.name} onChange={e => setProductForm({...productForm, name: e.target.value})} />
                                    </div>
                                    <div style=@{{display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '1rem', marginBottom: '1rem'}}>
                                        <div className="form-group" style=@{{marginBottom: 0}}>
                                            <label>Price (Rp)</label>
                                            <input type="number" className="form-control" placeholder="e.g. 80000" value={productForm.price} onChange={e => setProductForm({...productForm, price: e.target.value})} />
                                        </div>
                                        <div className="form-group" style=@{{marginBottom: 0}}>
                                            <label>Stock</label>
                                            <input type="number" className="form-control" placeholder="e.g. 50" value={productForm.stock} onChange={e => setProductForm({...productForm, stock: e.target.value})} />
                                        </div>
                                    </div>
                                    <div className="form-group">
                                        <label>External Product ID (Optional)</label>
                                        <input type="text" className="form-control" placeholder="e.g. EX-101" value={productForm.external_product_id} onChange={e => setProductForm({...productForm, external_product_id: e.target.value})} />
                                    </div>
                                    
                                    <div className="form-group" style=@{{marginTop: '1.5rem'}}>
                                        <div style=@{{display: 'flex', justifyContent: 'space-between', alignItems: 'center', fontWeight: '600', marginBottom: '0.5rem'}}>
                                            <span>Vehicle Compatibilities</span>
                                            <button className="btn" style=@{{padding: '0.25rem 0.5rem', fontSize: '0.75rem', background: 'rgba(255,255,255,0.1)', border: 'none'}} onClick={addCompatibilityRow}>+ Add Row</button>
                                        </div>
                                        {productForm.compatibilities.map((comp, idx) => (
                                            <div key={idx} style=@{{display: 'grid', gridTemplateColumns: '1fr 1fr 1fr auto', gap: '0.5rem', marginTop: '0.5rem', alignItems: 'center'}}>
                                                <input type="text" className="form-control" style=@{{padding: '0.35rem', fontSize: '0.85rem'}} placeholder="Brand (e.g. Honda)" value={comp.brand} onChange={e => handleCompatibilityChange(idx, 'brand', e.target.value)} />
                                                <input type="text" className="form-control" style=@{{padding: '0.35rem', fontSize: '0.85rem'}} placeholder="Model (e.g. Vario)" value={comp.model} onChange={e => handleCompatibilityChange(idx, 'model', e.target.value)} />
                                                <input type="text" className="form-control" style=@{{padding: '0.35rem', fontSize: '0.85rem'}} placeholder="Year (e.g. 2020)" value={comp.year} onChange={e => handleCompatibilityChange(idx, 'year', e.target.value)} />
                                                <button className="btn" style=@{{padding: '0.35rem 0.5rem', background: 'rgba(239, 68, 68, 0.1)', color: '#ef4444', border: 'none'}} onClick={() => removeCompatibilityRow(idx)}>✕</button>
                                            </div>
                                        ))}
                                    </div>
                                    <div style=@{{display: 'flex', gap: '0.5rem', marginTop: '1.5rem'}}>
                                        <button className="btn" onClick={saveProduct}>{productForm.id ? 'Update Product' : 'Save Product'}</button>
                                        {productForm.id && (
                                            <button className="btn" style=@{{background: 'rgba(255,255,255,0.1)', border: '1px solid rgba(255,255,255,0.1)'}} onClick={() => setProductForm({ id: null, name: '', price: '', stock: '', external_product_id: '', compatibilities: [{ brand: '', model: '', year: '' }] })}>Cancel</button>
                                        )}
                                    </div>
                                </div>
                                <div className="card">
                                    <div className="card-title">Product Catalog List</div>
                                    {productsData && productsData.map(prod => (
                                        <div key={prod.id} style=@{{background: 'rgba(0,0,0,0.2)', padding: '1rem', borderRadius: '0.5rem', marginBottom: '1rem', display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start'}}>
                                            <div style=@{{flex: 1}}>
                                                <div style=@{{fontWeight: '600', color: 'white', fontSize: '1rem'}}>{prod.name}</div>
                                                <div style=@{{fontSize: '0.85rem', color: 'var(--text-muted)', marginTop: '0.25rem'}}>
                                                    Price: Rp{Number(prod.price).toLocaleString('id-ID')} | Stock: {prod.stock} {prod.external_product_id && `| Ext ID: ${prod.external_product_id}`}
                                                </div>
                                                {prod.product_compatibilities && prod.product_compatibilities.length > 0 && (
                                                    <div style=@{{fontSize: '0.8rem', color: 'var(--accent)', marginTop: '0.5rem'}}>
                                                        Compatibilities: {prod.product_compatibilities.map(c => `${c.vehicle_brand} ${c.vehicle_model} (${c.vehicle_year || 'All'})`).join(', ')}
                                                    </div>
                                                )}
                                                <div style=@{{marginTop: '0.5rem'}}>
                                                    {prod.vector ? (
                                                        <span style=@{{fontSize: '0.75rem', padding: '0.2rem 0.5rem', background: 'rgba(16, 185, 129, 0.15)', color: '#10b981', borderRadius: '0.25rem', display: 'inline-flex', alignItems: 'center'}}>
                                                            ✓ Vector Synced
                                                        </span>
                                                    ) : (
                                                        <span style=@{{fontSize: '0.75rem', padding: '0.2rem 0.5rem', background: 'rgba(239, 68, 68, 0.15)', color: '#ef4444', borderRadius: '0.25rem', display: 'inline-flex', alignItems: 'center'}}>
                                                            ✗ No Vector
                                                        </span>
                                                    )}
                                                </div>
                                            </div>
                                            <div style=@{{display: 'flex', gap: '0.5rem'}}>
                                                <button className="btn" style=@{{padding: '0.25rem 0.5rem', fontSize: '0.8rem', background: 'rgba(255,255,255,0.1)', border: 'none'}} onClick={() => editProduct(prod)}>Edit</button>
                                                <button className="btn" style=@{{padding: '0.25rem 0.5rem', fontSize: '0.8rem', background: 'rgba(239, 68, 68, 0.1)', color: '#ef4444', border: 'none'}} onClick={() => deleteProduct(prod.id)}>Delete</button>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        ) : (
                            <div>
                                <div className="card">
                                    <div className="card-title">Knowledge Base</div>
                                    <div className="form-group">
                                        <label>Category</label>
                                        <select className="form-control" value={kbForm.category} onChange={e => setKbForm({...kbForm, category: e.target.value})}>
                                            <option>Company Profile</option>
                                            <option>FAQ</option>
                                            <option>Product Catalog</option>
                                            <option>SOP Customer Service</option>
                                        </select>
                                    </div>
                                    <div className="form-group">
                                        <label>Title</label>
                                        <input type="text" className="form-control" placeholder="e.g. Return Policy" value={kbForm.title} onChange={e => setKbForm({...kbForm, title: e.target.value})} />
                                    </div>
                                    <div className="form-group">
                                        <label>Content</label>
                                        <textarea className="form-control" rows="5" placeholder="Detailed information..." value={kbForm.content} onChange={e => setKbForm({...kbForm, content: e.target.value})}></textarea>
                                    </div>
                                    <button className="btn" onClick={saveKb}>Save Data</button>
                                </div>
                                <div className="card">
                                    <div className="card-title">Saved Data</div>
                                    {kbData && kbData.map(kb => (
                                        <div key={kb.id} style=@{{background: 'rgba(0,0,0,0.2)', padding: '1rem', borderRadius: '0.5rem', marginBottom: '1rem'}}>
                                            <div style=@{{color: 'var(--accent)', fontSize: '0.8rem', marginBottom: '0.25rem'}}>{kb.category}</div>
                                            <div style=@{{fontWeight: '600', marginBottom: '0.5rem'}}>{kb.title}</div>
                                            <div style=@{{fontSize: '0.875rem', color: 'var(--text-muted)'}}>{kb.content}</div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>
                )}

                {activeTab === 'channels' && (
                    <div className="card">
                        <div className="card-title">Channel Integration</div>
                        
                        {/* Telegram (Active) */}
                        <div style=@{{paddingBottom: '1.5rem', borderBottom: '1px solid rgba(255,255,255,0.05)', marginBottom: '1.5rem'}}>
                            <div style=@{{display: 'flex', gap: '1rem', alignItems: 'center', marginBottom: '1rem'}}>
                                <div style=@{{flex: 1}}>
                                    <div style=@{{fontWeight: 600, color: 'white', display: 'flex', alignItems: 'center'}}><i data-lucide="send" style=@{{width: 16, height: 16, marginRight: 8, color: '#3b82f6'}}></i> Telegram</div>
                                    <div style=@{{fontSize: '0.8rem', color: '#10b981'}}>Status: Connected</div>
                                </div>
                                <button className="btn" style=@{{background: 'rgba(239, 68, 68, 0.1)', color: '#ef4444'}}>Disconnect</button>
                            </div>
                            <div style=@{{display: 'grid', gridTemplateColumns: '1fr', gap: '1rem'}}>
                                <div>
                                    <label style=@{{fontSize: '0.75rem', color: '#94a3b8'}}>Bot Token</label>
                                    <input type="password" className="form-control" style=@{{padding: '0.5rem', fontSize: '0.875rem'}} value={botToken} onChange={e => setBotToken(e.target.value)} />
                                </div>
                                <div>
                                    <label style=@{{fontSize: '0.75rem', color: '#94a3b8'}}>Bot Username</label>
                                    <input type="text" className="form-control" style=@{{padding: '0.5rem', fontSize: '0.875rem'}} value={botUsername} onChange={e => setBotUsername(e.target.value)} />
                                </div>
                                <div>
                                    <label style=@{{fontSize: '0.75rem', color: '#94a3b8'}}>Webhook URL</label>
                                    <input type="text" className="form-control" style=@{{padding: '0.5rem', fontSize: '0.875rem'}} value={window.location.origin + '/api/telegram/webhook'} readOnly />
                                </div>
                            </div>
                            <div style=@{{marginTop: '1rem', display: 'flex', gap: '0.5rem'}}>
                                <button className="btn" onClick={saveTelegramChannel}>Save</button>
                                <button className="btn" style=@{{background: 'rgba(255,255,255,0.1)'}} onClick={testTelegramConnection} disabled={testingConnection}>{testingConnection ? 'Testing...' : 'Test Connection'}</button>
                                <button className="btn" style=@{{background: 'rgba(59, 130, 246, 0.1)', color: '#3b82f6'}} onClick={syncTelegramMessages} disabled={syncingMessages}>{syncingMessages ? 'Syncing...' : 'Sync Messages'}</button>
                            </div>
                        </div>

                        {/* WhatsApp (Coming Soon) */}
                        <div style=@{{paddingBottom: '1.5rem', borderBottom: '1px solid rgba(255,255,255,0.05)', marginBottom: '1.5rem', opacity: 0.5}}>
                            <div style=@{{display: 'flex', gap: '1rem', alignItems: 'center'}}>
                                <div style=@{{flex: 1}}>
                                    <div style=@{{fontWeight: 600, color: 'white', display: 'flex', alignItems: 'center'}}><i data-lucide="phone" style=@{{width: 16, height: 16, marginRight: 8, color: '#10b981'}}></i> WhatsApp</div>
                                    <div style=@{{fontSize: '0.8rem', color: 'var(--text-muted)'}}>Coming in Phase 2</div>
                                </div>
                                <button className="btn" disabled>Connect</button>
                            </div>
                        </div>

                        {/* Shopee (Coming Soon) */}
                        <div style=@{{paddingBottom: '1.5rem', borderBottom: '1px solid rgba(255,255,255,0.05)', marginBottom: '1.5rem', opacity: 0.5}}>
                            <div style=@{{display: 'flex', gap: '1rem', alignItems: 'center'}}>
                                <div style=@{{flex: 1}}>
                                    <div style=@{{fontWeight: 600, color: 'white', display: 'flex', alignItems: 'center'}}><i data-lucide="shopping-bag" style=@{{width: 16, height: 16, marginRight: 8, color: '#f97316'}}></i> Shopee</div>
                                    <div style=@{{fontSize: '0.8rem', color: 'var(--text-muted)'}}>Coming in Phase 2</div>
                                </div>
                                <button className="btn" disabled>Connect</button>
                            </div>
                        </div>
                        
                        {/* Tokopedia & TikTok Shop */}
                        <div style=@{{display: 'flex', gap: '1rem', color: 'var(--text-muted)', fontSize: '0.85rem'}}>
                            + Tokopedia, TikTok Shop (Upcoming)
                        </div>
                    </div>
                )}

                {activeTab === 'ai' && settingsData && (
                    <div className="card">
                        <div className="card-title">AI Settings</div>
                        <div style=@{{display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '1.5rem', borderBottom: '1px solid rgba(255,255,255,0.05)', paddingBottom: '1.5rem'}}>
                            <div>
                                <div style=@{{fontWeight: 600, color: 'white'}}>AI Full Control</div>
                                <div style=@{{fontSize: '0.875rem', color: 'var(--text-muted)'}}>Allow AI to automatically reply to customers without admin intervention.</div>
                            </div>
                            <label className="toggle-switch">
                                <input type="checkbox" checked={settingsData.ai_setting.full_control} onChange={e => {
                                    // Toggle full control immediately and preserve the current token value
                                    fetch('/api/settings/ai-toggle', {
                                        method: 'PUT',
                                        headers: { 'Content-Type': 'application/json' },
                                        body: JSON.stringify({ full_control: e.target.checked, openai_token: openAiToken })
                                    }).then(() => refetchSettings());
                                }} />
                                <span className="slider"></span>
                            </label>
                        </div>
                        <div style=@{{marginBottom: '1.5rem'}}>
                            <label style=@{{fontSize: '0.75rem', color: '#94a3b8'}}>OpenAI API Token</label>
                            <input 
                                type="password" 
                                className="form-control" 
                                style=@{{padding: '0.5rem', fontSize: '0.875rem', marginTop: '0.25rem'}} 
                                value={openAiToken} 
                                onChange={e => setOpenAiToken(e.target.value)} 
                                placeholder="sk-proj-..."
                            />
                        </div>
                        <div style=@{{display: 'flex', gap: '0.5rem', marginTop: '1rem'}}>
                            <button className="btn" onClick={saveAiSettings}>Save AI Settings</button>
                            <button className="btn" style=@{{background: 'rgba(255,255,255,0.1)'}} onClick={testOpenAiConnection} disabled={testingOpenAi}>{testingOpenAi ? 'Testing...' : 'Test Token'}</button>
                        </div>
                        {settingsData.ai_setting.full_control && (
                            <div style=@{{padding: '1rem', background: 'rgba(16, 185, 129, 0.1)', color: '#10b981', borderRadius: '0.5rem', marginTop: '1.5rem'}}>
                                <i data-lucide="check-circle" style=@{{width: 16, height: 16, marginRight: '0.5rem', verticalAlign: 'middle'}}></i>
                                AI is currently handling replies automatically.
                            </div>
                        )}
                    </div>
                )}
            </div>
        </div>
    );
};
