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

    // Auto sync Telegram messages on mount
    useEffect(() => {
        fetch('/api/settings/telegram/sync', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        })
        .then(() => {
            refetchInbox();
        })
        .catch(err => console.error('Auto-sync failed:', err));
    }, []);

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

    const triggerSummary = () => {
        if (!selectedChat) return;
        setLoadingSummary(true);
        fetch(`/api/conversations/${selectedChat}/summary?force=true`)
            .then(res => res.json())
            .then(data => {
                setAiSummary(data.summary);
                setLoadingSummary(false);
            })
            .catch(err => {
                console.error(err);
                setLoadingSummary(false);
                alert('Failed to generate summary.');
            });
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
            body: JSON.stringify({ conversation_id: selectedChat, message: messageText, sender: simulateRole })
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
                                            <button onClick={() => setAiSuggestion('')} style=@{{flex: 1, background: 'transparent', color: '#ef4444', border: '1px solid rgba(239, 68, 68, 0.5)', padding: '0.4rem', borderRadius: '0.25rem', cursor: 'pointer', fontSize: '0.75rem'}}>Cancel</button>
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
                            <div style=@{{fontSize: '0.8rem', color: 'var(--text-muted)', marginBottom: '0.75rem', textTransform: 'uppercase', letterSpacing: '0.05em', display: 'flex', justifyContent: 'space-between', alignItems: 'center'}}>
                                <span>Conversation Summary</span>
                                <button className="btn" style=@{{padding: '0.2rem 0.5rem', fontSize: '0.7rem', background: 'rgba(255,255,255,0.1)', border: 'none'}} onClick={triggerSummary} disabled={loadingSummary}>
                                    {loadingSummary ? 'Summarizing...' : 'Summarize Chat'}
                                </button>
                            </div>
                            {loadingSummary && <div style=@{{fontSize: '0.85rem', color: 'var(--text-muted)'}}>Generating...</div>}
                            {aiSummary && !loadingSummary && (
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
                                        <span style=@{{color: 'white', fontSize: '0.85rem'}}>{conversation ? conversation.messages.length : 0}</span>
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
