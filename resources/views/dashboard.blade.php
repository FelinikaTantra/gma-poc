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

        const SettingsView = () => {
            const [activeTab, setActiveTab] = useState('master');
            
            // Knowledge Base state
            const { data: kbData, refetch: refetchKb } = useApi('/api/knowledge-base');
            const [kbForm, setKbForm] = useState({ category: 'Company Profile', title: '', content: '' });

            // Channels & AI Settings state
            const { data: settingsData, refetch: refetchSettings } = useApi('/api/settings');

            useEffect(() => {
                lucide.createIcons();
            }, [activeTab, kbData, settingsData]);

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
                    body: JSON.stringify({ full_control: val })
                }).then(() => refetchSettings());
            };

            const updateChannel = (id, field, value) => {
                // For simplicity, we just send all current values with the updated field
                // In a real app we'd manage form state per channel
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
                                            <input type="password" className="form-control" style=@{{padding: '0.5rem', fontSize: '0.875rem'}} defaultValue="123456789:ABCDEFGHIJKLMNOPQRSTUVWXYZ" />
                                        </div>
                                        <div>
                                            <label style=@{{fontSize: '0.75rem', color: '#94a3b8'}}>Bot Username</label>
                                            <input type="text" className="form-control" style=@{{padding: '0.5rem', fontSize: '0.875rem'}} defaultValue="@omnichat_bot" />
                                        </div>
                                        <div>
                                            <label style=@{{fontSize: '0.75rem', color: '#94a3b8'}}>Webhook URL</label>
                                            <input type="text" className="form-control" style=@{{padding: '0.5rem', fontSize: '0.875rem'}} defaultValue="https://gma.com/api/webhook/telegram" readOnly />
                                        </div>
                                    </div>
                                    <div style=@{{marginTop: '1rem', display: 'flex', gap: '0.5rem'}}>
                                        <button className="btn">Save</button>
                                        <button className="btn" style=@{{background: 'rgba(255,255,255,0.1)'}}>Test Connection</button>
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
                                <div className="card-title">AI Automation</div>
                                <div style=@{{display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '1rem'}}>
                                    <div>
                                        <div style=@{{fontWeight: 600, color: 'white'}}>AI Full Control</div>
                                        <div style=@{{fontSize: '0.875rem', color: 'var(--text-muted)'}}>Allow AI to automatically reply to customers without admin intervention.</div>
                                    </div>
                                    <label className="toggle-switch">
                                        <input type="checkbox" checked={settingsData.ai_setting.full_control} onChange={e => toggleAi(e.target.checked)} />
                                        <span className="slider"></span>
                                    </label>
                                </div>
                                {settingsData.ai_setting.full_control && (
                                    <div style=@{{padding: '1rem', background: 'rgba(16, 185, 129, 0.1)', color: '#10b981', borderRadius: '0.5rem', marginTop: '1rem'}}>
                                        <i data-lucide="check-circle" style=@{{width: 16, height: 16, marginRight: '0.5rem'}}></i>
                                        AI is currently handling replies automatically.
                                    </div>
                                )}
                            </div>
                        )}
                    </div>
                </div>
            );
        };

        const ChatView = () => {
            const [selectedChat, setSelectedChat] = useState(null);
            const [input, setInput] = useState('');
            const [showAiSuggest, setShowAiSuggest] = useState(false);
            const [aiSuggestion, setAiSuggestion] = useState('');
            const [loadingSuggest, setLoadingSuggest] = useState(false);
            const [chatFilter, setChatFilter] = useState('All');
            const [aiSummary, setAiSummary] = useState('');
            const [loadingSummary, setLoadingSummary] = useState(false);
            const [simulateRole, setSimulateRole] = useState('admin');
            const [rightPanelTab, setRightPanelTab] = useState('ai');
            const messagesEndRef = useRef(null);

            const { data: inbox, refetch: refetchInbox } = useApi('/api/conversations');
            const { data: conversation, refetch: refetchConversation } = useApi(selectedChat ? `/api/conversations/${selectedChat}/messages` : null);

            // Poll for new messages every 5 seconds
            useEffect(() => {
                const interval = setInterval(() => {
                    refetchInbox();
                    if (selectedChat) {
                        refetchConversation();
                    }
                }, 5000);
                return () => clearInterval(interval);
            }, [selectedChat]);

            useEffect(() => {
                lucide.createIcons();
                if (messagesEndRef.current) {
                    messagesEndRef.current.scrollIntoView({ behavior: 'smooth' });
                }
            }, [conversation, inbox, rightPanelTab]);

            const selectChat = (id) => {
                if (selectedChat === id) return;
                setSelectedChat(id);
                setShowAiSuggest(false);
                setAiSuggestion('');
                setAiSummary('');
                setLoadingSummary(true);
                
                fetch(`/api/conversations/${id}/summary`)
                    .then(res => res.json())
                    .then(data => {
                        setAiSummary(data.summary);
                        setLoadingSummary(false);
                    })
                    .catch(() => setLoadingSummary(false));

                // Auto-fetch suggestion for 1-Klik Send
                setLoadingSuggest(true);
                fetch(`/api/conversations/${id}/suggest`)
                    .then(res => res.json())
                    .then(data => {
                        setAiSuggestion(data.suggestion);
                        setLoadingSuggest(false);
                    })
                    .catch(() => setLoadingSuggest(false));
            };

            const submitFeedback = (msgId, feedback) => {
                fetch(`/api/messages/${msgId}/feedback`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ feedback })
                }).then(() => {
                    alert(`Feedback '${feedback}' saved!`);
                });
            };

            const fetchSuggestion = () => {
                if (!selectedChat) return;
                setLoadingSuggest(true);
                setShowAiSuggest(false);
                fetch(`/api/conversations/${selectedChat}/suggest`)
                    .then(res => res.json())
                    .then(data => {
                        setAiSuggestion(data.suggestion);
                        setShowAiSuggest(true);
                        setLoadingSuggest(false);
                    })
                    .catch(() => setLoadingSuggest(false));
            };

            const filteredInbox = inbox ? inbox.filter(c => {
                if (chatFilter === 'Waiting Admin') return c.status === 'waiting_admin' || c.status === 'pending';
                if (chatFilter === 'Waiting Customer') return c.status === 'waiting_customer';
                if (chatFilter === 'Closed') return c.status === 'closed';
                return true;
            }) : [];

            const handleSend = () => {
                if (!input.trim() || !selectedChat) return;
                const messageText = input;
                setInput('');
                setShowAiSuggest(false);
                
                // Using PRD standardized endpoint format
                fetch(`/api/messages/send`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ conversation_id: selectedChat, message: input, sender: simulateRole })
                }).then(() => {
                    refetchConversation();
                    refetchInbox();
                });
            };

            const useSuggestion = () => {
                setInput(aiSuggestion);
                setShowAiSuggest(false);
            };

            return (
                <div className="main-content">
                    <div className="page-header">Unified Inbox</div>
                    <div className="chat-layout" style=@{{display: 'flex'}}>
                        {/* 1. Kiri: Customer List */}
                        <div className="customer-list" style=@{{display: 'flex', flexDirection: 'column', width: '300px', flexShrink: 0, borderRight: '1px solid var(--border-color)'}}>
                            <div style=@{{padding: '1rem', borderBottom: '1px solid var(--border-color)', display: 'flex', gap: '0.5rem', overflowX: 'auto', flexShrink: 0}}>
                                {['All', 'Waiting Admin', 'Waiting Customer', 'Closed'].map(f => (
                                    <button 
                                        key={f}
                                        className="btn-filter"
                                        onClick={() => setChatFilter(f)}
                                        style=@{{
                                            padding: '0.25rem 0.75rem', 
                                            borderRadius: '1rem', 
                                            fontSize: '0.75rem',
                                            cursor: 'pointer',
                                            whiteSpace: 'nowrap',
                                            background: chatFilter === f ? 'var(--accent)' : 'transparent',
                                            color: chatFilter === f ? 'white' : 'var(--text-muted)',
                                            border: `1px solid ${chatFilter === f ? 'var(--accent)' : 'var(--border-color)'}`
                                        }}
                                    >
                                        {f}
                                    </button>
                                ))}
                            </div>
                            <div style=@{{overflowY: 'auto', flex: 1}}>
                                {(!filteredInbox || filteredInbox.length === 0) && <div style=@{{padding: '1.5rem', color: 'var(--text-muted)', fontSize: '0.875rem'}}>No conversations match this filter.</div>}
                                {filteredInbox && filteredInbox.map(c => (
                                    <div key={c.id} className={`customer-item ${selectedChat === c.id ? 'active' : ''}`} onClick={() => selectChat(c.id)}>
                                        <div className="customer-name" style=@{{display: 'flex', justifyContent: 'space-between'}}>
                                            <span>
                                                {c.customer_name} 
                                                <span className="customer-channel">{c.channel_name}</span>
                                            </span>
                                            {c.unread_count > 0 && (
                                                <span style=@{{background: '#ef4444', color: 'white', padding: '0.1rem 0.4rem', borderRadius: '1rem', fontSize: '0.6rem'}}>
                                                    {c.unread_count}
                                                </span>
                                            )}
                                        </div>
                                        <div style=@{{display: 'flex', justifyContent: 'space-between', alignItems: 'center'}}>
                                            <span className="customer-msg" style=@{{maxWidth: '150px'}}>{c.last_message}</span>
                                            <span style=@{{fontSize: '0.7rem', color: 'var(--text-muted)'}}>{new Date(c.last_activity).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</span>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>

                        {/* 2. Tengah: Chat Area */}
                        <div className="chat-area" style=@{{flex: 1, borderRight: selectedChat ? '1px solid var(--border-color)' : 'none'}}>
                            {!selectedChat ? (
                                <div style=@{{flex: 1, display: 'flex', alignItems: 'center', justifyContent: 'center', color: 'var(--text-muted)'}}>
                                    Select a conversation to start chatting
                                </div>
                            ) : (
                                <>
                                    <div className="chat-history">
                                        {conversation && conversation.messages.map(m => (
                                            <div key={m.id} className={`message-bubble message-${m.sender_type}`}>
                                                <div>{m.message}</div>
                                                <div className="message-meta" style=@{{display: 'flex', justifyContent: 'space-between', alignItems: 'center'}}>
                                                    <div>
                                                        {m.sender_type === 'ai' && <i data-lucide="bot" style=@{{width: 12, height: 12, marginRight: 4, display: 'inline-block', verticalAlign: 'middle'}}></i>}
                                                        {new Date(m.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}
                                                    </div>
                                                    {m.sender_type === 'ai' && (
                                                        <div style=@{{display: 'flex', gap: '0.25rem'}}>
                                                            <button style=@{{background: 'none', border: 'none', cursor: 'pointer', fontSize: '0.8rem'}} onClick={() => submitFeedback(m.id, 'good')} title="Good Answer">👍</button>
                                                            <button style=@{{background: 'none', border: 'none', cursor: 'pointer', fontSize: '0.8rem'}} onClick={() => submitFeedback(m.id, 'bad')} title="Bad Answer">👎</button>
                                                        </div>
                                                    )}
                                                </div>
                                            </div>
                                        ))}
                                        <div ref={messagesEndRef} />
                                    </div>

                                    <div className="chat-input-container" style=@{{display: 'flex', flexDirection: 'column', gap: '0.5rem'}}>
                                        {/* 1-KLIK SEND AI SUGGESTION BLOCK */}
                                        {aiSuggestion && (
                                            <div style=@{{background: 'rgba(59, 130, 246, 0.1)', border: '1px solid rgba(59, 130, 246, 0.3)', padding: '0.75rem', borderRadius: '0.5rem', marginBottom: '0.5rem'}}>
                                                <div style=@{{fontSize: '0.75rem', color: '#93c5fd', marginBottom: '0.5rem', fontWeight: 600, display: 'flex', alignItems: 'center'}}>
                                                    <i data-lucide="bot" style=@{{width: 14, height: 14, marginRight: 4}}></i> AI Suggested Reply (1-Klik Send)
                                                </div>
                                                <div style=@{{color: 'white', fontSize: '0.85rem', marginBottom: '0.75rem', whiteSpace: 'pre-line'}}>
                                                    {aiSuggestion}
                                                </div>
                                                <div style=@{{display: 'flex', gap: '0.5rem'}}>
                                                    <button onClick={() => { setInput(aiSuggestion); setAiSuggestion(''); }} style=@{{flex: 1, background: 'transparent', color: '#93c5fd', border: '1px solid rgba(59, 130, 246, 0.5)', padding: '0.4rem', borderRadius: '0.25rem', cursor: 'pointer', fontSize: '0.75rem'}}>Edit</button>
                                                    <button onClick={() => { 
                                                        fetch(`/api/messages/send`, {
                                                            method: 'POST',
                                                            headers: { 'Content-Type': 'application/json' },
                                                            body: JSON.stringify({ conversation_id: selectedChat, message: aiSuggestion, sender: 'admin' })
                                                        }).then(() => {
                                                            setAiSuggestion('');
                                                            refetchConversation();
                                                            refetchInbox();
                                                        });
                                                    }} style=@{{flex: 1, background: '#3b82f6', color: 'white', border: 'none', padding: '0.4rem', borderRadius: '0.25rem', cursor: 'pointer', fontSize: '0.75rem', fontWeight: 600}}>Send (1 Klik)</button>
                                                </div>
                                            </div>
                                        )}
                                        <div style=@{{display: 'flex', justifyContent: 'space-between', alignItems: 'center'}}>
                                            <div style=@{{display: 'flex', gap: '0.5rem', overflowX: 'auto', paddingBottom: '0.25rem'}}>
                                                {/* Example Quick Replies since we don't have a fetch yet */}
                                                <button onClick={() => setInput('Halo Kak, ada yang bisa dibantu?')} style=@{{background: 'rgba(255,255,255,0.1)', border: 'none', color: 'var(--text-muted)', padding: '0.25rem 0.5rem', borderRadius: '1rem', fontSize: '0.7rem', cursor: 'pointer', whiteSpace: 'nowrap'}}>+ Halo</button>
                                                <button onClick={() => setInput('Terima kasih sudah menghubungi kami.')} style=@{{background: 'rgba(255,255,255,0.1)', border: 'none', color: 'var(--text-muted)', padding: '0.25rem 0.5rem', borderRadius: '1rem', fontSize: '0.7rem', cursor: 'pointer', whiteSpace: 'nowrap'}}>+ Terima Kasih</button>
                                            </div>
                                            <select 
                                                style=@{{background: 'rgba(0,0,0,0.5)', border: '1px solid rgba(255,255,255,0.1)', color: 'white', padding: '0.25rem 0.5rem', borderRadius: '0.25rem', fontSize: '0.75rem', flexShrink: 0}}
                                                value={simulateRole}
                                                onChange={e => setSimulateRole(e.target.value)}
                                            >
                                                <option value="admin">Send as Admin</option>
                                                <option value="customer">Simulate Customer</option>
                                            </select>
                                        </div>
                                        <div className="input-row">
                                            <textarea 
                                                className="chat-input" 
                                                placeholder={simulateRole === 'admin' ? "Type your reply here..." : "Type as customer..."}
                                                value={input}
                                                onChange={e => setInput(e.target.value)}
                                                onKeyDown={e => {
                                                    if (e.key === 'Enter' && !e.shiftKey) {
                                                        e.preventDefault();
                                                        handleSend();
                                                    }
                                                }}
                                            ></textarea>
                                            <button className="btn-send" onClick={handleSend} style=@{{background: simulateRole === 'customer' ? '#f59e0b' : 'var(--accent)'}}>
                                                <i data-lucide="send" style=@{{width: 20, height: 20}}></i>
                                            </button>
                                        </div>
                                    </div>
                                </>
                            )}
                        </div>

                        {/* 3. Kanan: AI Panel & Profile */}
                        {selectedChat && (
                            <div className="right-panel" style=@{{width: '320px', flexShrink: 0, display: 'flex', flexDirection: 'column', background: 'rgba(0,0,0,0.1)', borderLeft: '1px solid var(--border-color)'}}>
                                <div style=@{{display: 'flex', borderBottom: '1px solid var(--border-color)'}}>
                                    <button 
                                        onClick={() => setRightPanelTab('ai')}
                                        style=@{{flex: 1, padding: '1rem', background: 'transparent', border: 'none', color: rightPanelTab === 'ai' ? 'var(--accent)' : 'var(--text-muted)', borderBottom: rightPanelTab === 'ai' ? '2px solid var(--accent)' : 'none', cursor: 'pointer', fontWeight: 600, display: 'flex', alignItems: 'center', justifyContent: 'center'}}
                                    >
                                        <i data-lucide="bot" style=@{{marginRight: '0.5rem', width: 16, height: 16}}></i> AI Assistant
                                    </button>
                                    <button 
                                        onClick={() => setRightPanelTab('profile')}
                                        style=@{{flex: 1, padding: '1rem', background: 'transparent', border: 'none', color: rightPanelTab === 'profile' ? 'var(--accent)' : 'var(--text-muted)', borderBottom: rightPanelTab === 'profile' ? '2px solid var(--accent)' : 'none', cursor: 'pointer', fontWeight: 600, display: 'flex', alignItems: 'center', justifyContent: 'center'}}
                                    >
                                        <i data-lucide="user" style=@{{marginRight: '0.5rem', width: 16, height: 16}}></i> Profile
                                    </button>
                                </div>
                                
                                <div style=@{{padding: '1.5rem', overflowY: 'auto', flex: 1}}>
                                {rightPanelTab === 'ai' && (
                                    <>
                                        {/* AI Summary Section */}
                                <div style=@{{marginBottom: '2rem'}}>
                                    <div style=@{{fontSize: '0.8rem', color: 'var(--text-muted)', marginBottom: '0.75rem', textTransform: 'uppercase', letterSpacing: '0.05em'}}>Conversation Summary</div>
                                    {loadingSummary && <div style=@{{fontSize: '0.85rem', color: 'var(--text-muted)'}}>Generating...</div>}
                                    {aiSummary && (
                                        <div style=@{{background: 'rgba(59, 130, 246, 0.1)', border: '1px solid rgba(59, 130, 246, 0.2)', padding: '1rem', borderRadius: '0.5rem', color: '#93c5fd', fontSize: '0.875rem', lineHeight: '1.5', whiteSpace: 'pre-line'}}>
                                            {aiSummary}
                                        </div>
                                    )}
                                </div>

                                {/* AI Suggestion Section Moved to Composer */}
                                <div style=@{{color: 'var(--text-muted)', fontSize: '0.85rem', padding: '1.5rem 1rem', border: '1px dashed rgba(255,255,255,0.2)', borderRadius: '0.5rem', textAlign: 'center', marginBottom: '2rem'}}>
                                    AI Suggestions will now appear directly above your chat input box for 1-Click Send!
                                </div>

                                {/* Catalog & Product Compatibility Viewer */}
                                <div>
                                    <div style=@{{fontSize: '0.8rem', color: 'var(--text-muted)', marginBottom: '0.75rem', textTransform: 'uppercase', letterSpacing: '0.05em', display: 'flex', justifyContent: 'space-between', alignItems: 'center'}}>
                                        Product Catalog & Compatibility
                                    </div>
                                    <div style=@{{background: 'rgba(255,255,255,0.05)', borderRadius: '0.5rem', padding: '1rem'}}>
                                        <input type="text" placeholder="Search product or vehicle (e.g., Vario 125)..." style=@{{width: '100%', background: 'rgba(0,0,0,0.5)', border: '1px solid rgba(255,255,255,0.1)', color: 'white', padding: '0.5rem', borderRadius: '0.25rem', fontSize: '0.8rem', marginBottom: '1rem'}} />
                                        <div style=@{{fontSize: '0.75rem', color: 'var(--text-muted)', textAlign: 'center', padding: '1rem 0'}}>
                                            Type to search synced catalog...
                                        </div>
                                    </div>
                                </div>
                                </>)}
                                
                                {rightPanelTab === 'profile' && (
                                    <div style=@{{display: 'flex', flexDirection: 'column', gap: '1.5rem'}}>
                                        <div style=@{{textAlign: 'center'}}>
                                            <div style=@{{width: 64, height: 64, borderRadius: '50%', background: 'var(--accent)', margin: '0 auto 1rem', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '1.5rem', fontWeight: 'bold'}}>
                                                {inbox.find(c => c.id === selectedChat)?.customer_name?.charAt(0).toUpperCase()}
                                            </div>
                                            <div style=@{{fontSize: '1.1rem', fontWeight: 600, color: 'white'}}>{inbox.find(c => c.id === selectedChat)?.customer_name}</div>
                                            <div style=@{{fontSize: '0.8rem', color: 'var(--text-muted)'}}>@{inbox.find(c => c.id === selectedChat)?.customer_username || 'unknown'}</div>
                                        </div>
                                        <div style=@{{background: 'rgba(255,255,255,0.05)', borderRadius: '0.5rem', padding: '1rem'}}>
                                            <div style=@{{display: 'flex', justifyContent: 'space-between', marginBottom: '0.5rem'}}>
                                                <span style=@{{color: 'var(--text-muted)', fontSize: '0.85rem'}}>Channel</span>
                                                <span style=@{{color: 'white', fontSize: '0.85rem'}}>{inbox.find(c => c.id === selectedChat)?.channel_name}</span>
                                            </div>
                                            <div style=@{{display: 'flex', justifyContent: 'space-between', marginBottom: '0.5rem'}}>
                                                <span style=@{{color: 'var(--text-muted)', fontSize: '0.85rem'}}>Total Chats</span>
                                                <span style=@{{color: 'white', fontSize: '0.85rem'}}>{conversation ? conversation.length : 0}</span>
                                            </div>
                                            <div style=@{{display: 'flex', justifyContent: 'space-between', marginBottom: '0.5rem'}}>
                                                <span style=@{{color: 'var(--text-muted)', fontSize: '0.85rem'}}>Status</span>
                                                <span style=@{{color: 'white', fontSize: '0.85rem', textTransform: 'capitalize'}}>{inbox.find(c => c.id === selectedChat)?.status?.replace('_', ' ')}</span>
                                            </div>
                                        </div>

                                        {/* Internal Notes */}
                                        <div>
                                            <div style=@{{fontSize: '0.8rem', color: 'var(--text-muted)', marginBottom: '0.75rem', textTransform: 'uppercase', letterSpacing: '0.05em'}}>Internal Notes</div>
                                            <textarea 
                                                style=@{{width: '100%', background: 'rgba(0,0,0,0.5)', border: '1px solid rgba(255,255,255,0.1)', color: 'white', padding: '0.75rem', borderRadius: '0.5rem', fontSize: '0.85rem', marginBottom: '0.5rem', resize: 'vertical'}}
                                                placeholder="Add a note (not visible to customer)..."
                                                onKeyDown={e => {
                                                    if (e.key === 'Enter' && !e.shiftKey) {
                                                        e.preventDefault();
                                                        const val = e.target.value;
                                                        if (!val.trim()) return;
                                                        fetch(`/api/conversations/${selectedChat}/notes`, {
                                                            method: 'POST',
                                                            headers: { 'Content-Type': 'application/json' },
                                                            body: JSON.stringify({ note: val })
                                                        }).then(() => {
                                                            e.target.value = '';
                                                            alert('Note saved! (Refresh to view)');
                                                        });
                                                    }
                                                }}
                                            ></textarea>
                                        </div>
                                    </div>
                                )}
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            );
        };

        const App = () => {
            const [view, setView] = useState('chat');
            const [activeRole, setActiveRole] = useState('super_admin');

            useEffect(() => {
                lucide.createIcons();
            }, [view, activeRole]);

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
                                    if (e.target.value === 'admin' && view === 'settings') {
                                        setView('chat');
                                    }
                                }}
                                style=@{{padding: '0.5rem', fontSize: '0.875rem'}}
                            >
                                <option value="super_admin">Super Admin</option>
                                <option value="admin">Admin / CS</option>
                            </select>
                        </div>

                        <div className={`nav-item ${view === 'chat' ? 'active' : ''}`} onClick={() => setView('chat')}>
                            <i data-lucide="inbox" style=@{{width: 20, height: 20}}></i>
                            Unified Inbox
                        </div>
                        {activeRole === 'super_admin' && (
                            <div className={`nav-item ${view === 'settings' ? 'active' : ''}`} onClick={() => setView('settings')}>
                                <i data-lucide="settings" style=@{{width: 20, height: 20}}></i>
                                Settings & Master Data
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
                    {view === 'chat' ? <ChatView /> : <SettingsView />}
                </div>
            );
        };

        const root = ReactDOM.createRoot(document.getElementById('app'));
        root.render(<App />);
    </script>
</body>
</html>
