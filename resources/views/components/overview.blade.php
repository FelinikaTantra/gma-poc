const OverviewView = () => {
    const { data: stats, loading, refetch } = useApi('/api/analytics');

    useEffect(() => {
        const interval = setInterval(() => {
            refetch();
        }, 5000);
        return () => clearInterval(interval);
    }, []);

    useEffect(() => {
        if (window.lucide) {
            window.lucide.createIcons();
        }
    }, [stats, loading]);

    if (loading && !stats) {
        return (
            <div className="main-content" style=@{{display: 'flex', alignItems: 'center', justifyContent: 'center', height: '100vh', color: 'var(--text-muted)'}}>
                Loading dashboard metrics...
            </div>
        );
    }

    const { 
        total_conversations = 0, 
        total_messages = 0, 
        by_channel = {}, 
        by_status = {},
        avg_lead_time_ai = 0,
        avg_lead_time_manual = 0,
        kpi_lead_time_ai = 15,
        kpi_lead_time_manual = 300
    } = stats || {};

    const platforms = [
        { name: 'Telegram', icon: 'send', color: '#3b82f6', bg: 'rgba(59, 130, 246, 0.1)' },
        { name: 'WhatsApp', icon: 'phone', color: '#10b981', bg: 'rgba(16, 185, 129, 0.1)' },
        { name: 'Shopee', icon: 'shopping-bag', color: '#f97316', bg: 'rgba(249, 115, 22, 0.1)' },
        { name: 'TikTok', icon: 'video', color: '#ec4899', bg: 'rgba(236, 72, 153, 0.1)' },
        { name: 'Tokopedia', icon: 'grid', color: '#22c55e', bg: 'rgba(34, 197, 94, 0.1)' }
    ];

    const statuses = [
        { key: 'waiting_admin', label: 'Waiting Admin', color: '#fbbf24', bg: 'rgba(245, 158, 11, 0.15)' },
        { key: 'waiting_customer', label: 'Waiting Customer', color: '#34d399', bg: 'rgba(16, 185, 129, 0.15)' },
        { key: 'closed', label: 'Closed', color: '#f87171', bg: 'rgba(239, 68, 68, 0.15)' }
    ];

    return (
        <div className="main-content" style=@{{overflowY: 'auto', height: '100vh'}}>
            <div className="page-header" style=@{{display: 'flex', justifyContent: 'space-between', alignItems: 'center'}}>
                <span>Dashboard Overview</span>
                <button className="btn" style=@{{padding: '0.4rem 1rem', fontSize: '0.8rem', display: 'flex', alignItems: 'center', gap: '0.35rem'}} onClick={() => refetch()}>
                    <i data-lucide="refresh-cw" style=@{{width: 14, height: 14}}></i> Refresh
                </button>
            </div>

            <div style=@{{padding: '2rem', display: 'flex', flexDirection: 'column', gap: '2rem', maxWidth: '1200px'}}>
                {/* Metric Cards Row */}
                <div style=@{{display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))', gap: '1.25rem'}}>
                    {/* Card 1: Total Conversations */}
                    <div className="card" style=@{{
                        marginBottom: 0,
                        position: 'relative',
                        overflow: 'hidden',
                        background: 'linear-gradient(135deg, rgba(59, 130, 246, 0.15) 0%, rgba(139, 92, 246, 0.05) 100%)',
                        border: '1px solid rgba(59, 130, 246, 0.2)',
                        padding: '1.25rem'
                    }}>
                        <div style=@{{display: 'flex', justifyContent: 'space-between', alignItems: 'center'}}>
                            <div>
                                <div style=@{{fontSize: '0.8rem', color: 'var(--text-muted)', fontWeight: 500}}>Total Incoming Chats</div>
                                <div style=@{{fontSize: '2.2rem', fontWeight: 700, color: 'white', marginTop: '0.25rem'}}>{total_conversations}</div>
                            </div>
                            <div style=@{{
                                width: 44,
                                height: 44,
                                borderRadius: '0.75rem',
                                background: 'rgba(59, 130, 246, 0.2)',
                                display: 'flex',
                                alignItems: 'center',
                                justify: 'center',
                                color: '#60a5fa'
                            }}>
                                <i data-lucide="message-square" style=@{{width: 22, height: 22}}></i>
                            </div>
                        </div>
                        <div style=@{{fontSize: '0.7rem', color: 'var(--text-muted)', marginTop: '0.75rem'}}>
                            Across all platform integrations
                        </div>
                    </div>

                    {/* Card 2: Total Messages */}
                    <div className="card" style=@{{
                        marginBottom: 0,
                        position: 'relative',
                        overflow: 'hidden',
                        background: 'linear-gradient(135deg, rgba(16, 185, 129, 0.15) 0%, rgba(139, 92, 246, 0.05) 100%)',
                        border: '1px solid rgba(16, 185, 129, 0.2)',
                        padding: '1.25rem'
                    }}>
                        <div style=@{{display: 'flex', justifyContent: 'space-between', alignItems: 'center'}}>
                            <div>
                                <div style=@{{fontSize: '0.8rem', color: 'var(--text-muted)', fontWeight: 500}}>Total Messages</div>
                                <div style=@{{fontSize: '2.2rem', fontWeight: 700, color: 'white', marginTop: '0.25rem'}}>{total_messages}</div>
                            </div>
                            <div style=@{{
                                width: 44,
                                height: 44,
                                borderRadius: '0.75rem',
                                background: 'rgba(16, 185, 129, 0.2)',
                                display: 'flex',
                                alignItems: 'center',
                                justify: 'center',
                                color: '#34d399'
                            }}>
                                <i data-lucide="database" style=@{{width: 22, height: 22}}></i>
                            </div>
                        </div>
                        <div style=@{{fontSize: '0.7rem', color: 'var(--text-muted)', marginTop: '0.75rem'}}>
                            Incoming & outgoing message count
                        </div>
                    </div>

                    {/* Card 3: AI Lead Time */}
                    <div className="card" style=@{{
                        marginBottom: 0,
                        position: 'relative',
                        overflow: 'hidden',
                        background: 'linear-gradient(135deg, rgba(139, 92, 246, 0.15) 0%, rgba(59, 130, 246, 0.05) 100%)',
                        border: '1px solid rgba(139, 92, 246, 0.2)',
                        padding: '1.25rem'
                    }}>
                        <div style=@{{display: 'flex', justifyContent: 'space-between', alignItems: 'center'}}>
                            <div>
                                <div style=@{{fontSize: '0.8rem', color: 'var(--text-muted)', fontWeight: 500}}>Avg AI Lead Time</div>
                                <div style=@{{fontSize: '2.2rem', fontWeight: 700, color: 'white', marginTop: '0.25rem'}}>
                                    {avg_lead_time_ai > 0 ? `${avg_lead_time_ai}s` : 'N/A'}
                                </div>
                            </div>
                            <div style=@{{
                                width: 44,
                                height: 44,
                                borderRadius: '0.75rem',
                                background: 'rgba(139, 92, 246, 0.2)',
                                display: 'flex',
                                alignItems: 'center',
                                justify: 'center',
                                color: '#a78bfa'
                            }}>
                                <i data-lucide="bot" style=@{{width: 22, height: 22}}></i>
                            </div>
                        </div>
                        <div style=@{{display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginTop: '0.75rem', fontSize: '0.7rem'}}>
                            <span style=@{{color: 'var(--text-muted)'}}>Target KPI: {kpi_lead_time_ai}s</span>
                            {avg_lead_time_ai === 0 ? (
                                <span style=@{{color: 'var(--text-muted)'}}>No Data</span>
                            ) : avg_lead_time_ai <= kpi_lead_time_ai ? (
                                <span style=@{{color: '#34d399', fontWeight: 600, display: 'flex', alignItems: 'center', gap: '2px'}}>
                                    <i data-lucide="check" style=@{{width: 10, height: 10}}></i> On Target
                                </span>
                            ) : (
                                <span style=@{{color: '#f87171', fontWeight: 600, display: 'flex', alignItems: 'center', gap: '2px'}}>
                                    <i data-lucide="alert-triangle" style=@{{width: 10, height: 10}}></i> Over KPI
                                </span>
                            )}
                        </div>
                    </div>

                    {/* Card 4: Manual CS Lead Time */}
                    <div className="card" style=@{{
                        marginBottom: 0,
                        position: 'relative',
                        overflow: 'hidden',
                        background: 'linear-gradient(135deg, rgba(245, 158, 11, 0.15) 0%, rgba(139, 92, 246, 0.05) 100%)',
                        border: '1px solid rgba(245, 158, 11, 0.2)',
                        padding: '1.25rem'
                    }}>
                        <div style=@{{display: 'flex', justifyContent: 'space-between', alignItems: 'center'}}>
                            <div>
                                <div style=@{{fontSize: '0.8rem', color: 'var(--text-muted)', fontWeight: 500}}>Avg Manual Lead Time</div>
                                <div style=@{{fontSize: '2.2rem', fontWeight: 700, color: 'white', marginTop: '0.25rem'}}>
                                    {avg_lead_time_manual > 0 ? `${avg_lead_time_manual}s` : 'N/A'}
                                </div>
                            </div>
                            <div style=@{{
                                width: 44,
                                height: 44,
                                borderRadius: '0.75rem',
                                background: 'rgba(245, 158, 11, 0.2)',
                                display: 'flex',
                                alignItems: 'center',
                                justify: 'center',
                                color: '#fbbf24'
                            }}>
                                <i data-lucide="user" style=@{{width: 22, height: 22}}></i>
                            </div>
                        </div>
                        <div style=@{{display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginTop: '0.75rem', fontSize: '0.7rem'}}>
                            <span style=@{{color: 'var(--text-muted)'}}>Target KPI: {kpi_lead_time_manual}s</span>
                            {avg_lead_time_manual === 0 ? (
                                <span style=@{{color: 'var(--text-muted)'}}>No Data</span>
                            ) : avg_lead_time_manual <= kpi_lead_time_manual ? (
                                <span style=@{{color: '#34d399', fontWeight: 600, display: 'flex', alignItems: 'center', gap: '2px'}}>
                                    <i data-lucide="check" style=@{{width: 10, height: 10}}></i> On Target
                                </span>
                            ) : (
                                <span style=@{{color: '#f87171', fontWeight: 600, display: 'flex', alignItems: 'center', gap: '2px'}}>
                                    <i data-lucide="alert-triangle" style=@{{width: 10, height: 10}}></i> Over KPI
                                </span>
                            )}
                        </div>
                    </div>
                </div>

                {/* Platform & Status Breakdown Grid */}
                <div style=@{{display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(450px, 1fr))', gap: '2rem'}}>
                    {/* Platform Breakdown */}
                    <div className="card" style=@{{marginBottom: 0}}>
                        <div className="card-title" style=@{{display: 'flex', justifyContent: 'space-between', alignItems: 'center'}}>
                            <span>Platform Breakdown</span>
                            <span style=@{{fontSize: '0.75rem', color: 'var(--text-muted)', fontWeight: 'normal'}}>Chats per integration</span>
                        </div>
                        <div style=@{{display: 'flex', flexDirection: 'column', gap: '1.25rem'}}>
                            {platforms.map(platform => {
                                const count = by_channel[platform.name] || 0;
                                const percentage = total_conversations > 0 ? (count / total_conversations) * 100 : 0;
                                return (
                                    <div key={platform.name} style=@{{display: 'flex', flexDirection: 'column', gap: '0.5rem'}}>
                                        <div style=@{{display: 'flex', justifyContent: 'space-between', alignItems: 'center'}}>
                                            <div style=@{{display: 'flex', alignItems: 'center', gap: '0.75rem'}}>
                                                <div style=@{{
                                                    width: 36,
                                                    height: 36,
                                                    borderRadius: '0.5rem',
                                                    background: platform.bg,
                                                    display: 'flex',
                                                    alignItems: 'center',
                                                    justify: 'center',
                                                    color: platform.color
                                                }}>
                                                    <i data-lucide={platform.icon} style=@{{width: 18, height: 18}}></i>
                                                </div>
                                                <span style=@{{fontWeight: 600, color: 'white'}}>{platform.name}</span>
                                            </div>
                                            <div style=@{{textAlign: 'right'}}>
                                                <span style=@{{fontWeight: 700, color: 'white', fontSize: '1.1rem'}}>{count}</span>
                                                <span style=@{{fontSize: '0.75rem', color: 'var(--text-muted)', marginLeft: '0.5rem'}}>({percentage.toFixed(0)}%)</span>
                                            </div>
                                        </div>
                                        <div style=@{{width: '100%', height: 6, background: 'rgba(255,255,255,0.05)', borderRadius: 3, overflow: 'hidden'}}>
                                            <div style=@{{width: `${percentage}%`, height: '100%', background: platform.color, borderRadius: 3, transition: 'width 0.5s ease-out-in'}}></div>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>

                    {/* Status Distribution */}
                    <div className="card" style=@{{marginBottom: 0}}>
                        <div className="card-title" style=@{{display: 'flex', justifyContent: 'space-between', alignItems: 'center'}}>
                            <span>Status Distribution</span>
                            <span style=@{{fontSize: '0.75rem', color: 'var(--text-muted)', fontWeight: 'normal'}}>Inbox state tracking</span>
                        </div>
                        <div style=@{{display: 'flex', flexDirection: 'column', gap: '1.5rem', justifyContent: 'center', height: '100%', paddingBottom: '1rem'}}>
                            {statuses.map(st => {
                                const count = by_status[st.key] || 0;
                                const percentage = total_conversations > 0 ? (count / total_conversations) * 100 : 0;
                                return (
                                    <div key={st.key} style=@{{
                                        padding: '1.25rem',
                                        background: 'rgba(255,255,255,0.02)',
                                        borderRadius: '0.75rem',
                                        border: '1px solid var(--border-color)',
                                        display: 'flex',
                                        justifyContent: 'space-between',
                                        alignItems: 'center'
                                    }}>
                                        <div style=@{{display: 'flex', alignItems: 'center', gap: '0.75rem'}}>
                                            <div style=@{{
                                                width: 10,
                                                height: 10,
                                                borderRadius: '50%',
                                                background: st.color,
                                                boxShadow: `0 0 10px ${st.color}`
                                            }}></div>
                                            <span style=@{{fontWeight: 600, color: 'white'}}>{st.label}</span>
                                        </div>
                                        <div style=@{{textAlign: 'right', display: 'flex', alignItems: 'baseline', gap: '0.35rem'}}>
                                            <span style=@{{fontWeight: 700, color: st.color, fontSize: '1.5rem'}}>{count}</span>
                                            <span style=@{{fontSize: '0.8rem', color: 'var(--text-muted)'}}>chats</span>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
};
