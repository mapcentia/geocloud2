import React, {Component} from 'react';
import {jwtDecode} from 'jwt-decode'
import {FormattedMessage} from 'react-intl'
import codeFlow from '../../utils/codeFlow'

class OpenId extends Component {
    constructor(props) {
        super(props)
        this.state = {
            savedDb: typeof window !== 'undefined' ? (localStorage.getItem('gc2_selected_db') || '') : '',
            info: '',
            selecting: false,
            allowedDatabases: [],
            databasesWithSuperuser: [],
            selectedOption: '',
            superuserLogin: typeof window !== 'undefined' ? (localStorage.getItem('gc2_selected_superuser') || '') : '',
            token: null,
            nonce: null,
            processing: false,
            searchTerm: '',
            superuserSelections: {}
        }
        this.handleLogin = this.handleLogin.bind(this)
        this.handleResetDb = this.handleResetDb.bind(this)
        this.onStorage = this.onStorage.bind(this)
        this.handleDbSelectChange = this.handleDbSelectChange.bind(this)
        this.handleConfirmSelection = this.handleConfirmSelection.bind(this)
        this.handleCancelSelection = this.handleCancelSelection.bind(this)
        this.handleSuperuserToggle = this.handleSuperuserToggle.bind(this)
        this.handleSearchChange = this.handleSearchChange.bind(this)
        this.handleDatabaseSuperuserToggle = this.handleDatabaseSuperuserToggle.bind(this)
        this.proceed = this.proceed.bind(this)
        this._isMounted = false
        this._redirectHandleStarted = false
    }

    handleLogin(e) {
        if (e) e.preventDefault()
        codeFlow.signIn()
    }

    handleResetDb(e) {
        if (e) e.preventDefault()
        localStorage.removeItem('gc2_selected_db')
        localStorage.removeItem('gc2_selected_superuser')
        this.setState({
            savedDb: '',
            info: 'reset' // Will be handled in render
        })
    }

    onStorage() {
        this.setState({savedDb: localStorage.getItem('gc2_selected_db') || ''})
    }

    async getDatabases() {
        return fetch('/api/v2/database/search?userIdentifier=*').then(res => res.json()).then(data =>
            data.databases.map(item => item.screenname)
        )
    }

    componentDidMount() {
        this._isMounted = true
        window.addEventListener('storage', this.onStorage)

        if (this.state.processing || this._redirectHandleStarted) {
            return
        }
        this._redirectHandleStarted = true
        this.setState({processing: true})

        codeFlow.redirectHandle().then(async isSignedIn => {
            if (!this._isMounted) return
            if (isSignedIn) {
                const token = JSON.parse(localStorage.getItem('gc2_tokens'))['accessToken']
                const nonce = localStorage.getItem('gc2_nonce')
                const {database, superuser} = jwtDecode(token)
                if (!database) {
                    alert(`No database set in token. Please contact the administrator.`)
                    if (this._isMounted) {
                        this.setState({processing: false})
                    }
                    codeFlow.clear()
                    return
                }
                let allowedDatabases
                let databasesWithSuperuser

                if (database === '*') {
                    allowedDatabases = await this.getDatabases()
                } else {
                    allowedDatabases = database.split(',').map(d => d.trim()).filter(Boolean)
                }

                // Sort databases alphabetically
                allowedDatabases.sort((a, b) => a.localeCompare(b))

                if (superuser === '*') {
                    databasesWithSuperuser = allowedDatabases
                } else {
                    databasesWithSuperuser = (superuser ? superuser.split(',') : []).map(d => d.trim()).filter(Boolean)
                }

                const overlap = allowedDatabases.filter(d => databasesWithSuperuser.includes(d))

                // Try to use a previously saved selection
                const savedDbLocal = localStorage.getItem('gc2_selected_db')
                let selectedDb
                if (savedDbLocal && allowedDatabases.includes(savedDbLocal)) {
                    selectedDb = savedDbLocal
                    // If selected saved DB supports superuser, ask for role selection
                    if (overlap.includes(selectedDb)) {
                        const savedSuperuser = localStorage.getItem('gc2_selected_superuser')
                        if (savedSuperuser !== null) {
                            this.proceed(token, nonce, selectedDb, savedSuperuser === '1' || savedSuperuser === 'true')
                            return
                        }
                        this.setState({
                            selecting: true,
                            allowedDatabases: [selectedDb],
                            databasesWithSuperuser,
                            selectedOption: selectedDb,
                            superuserLogin: false,
                            token,
                            nonce,
                            processing: false
                        })
                        return
                    }
                } else {
                    // If more than one database is allowed, ask the user to pick one
                    selectedDb = allowedDatabases[0]
                    if (allowedDatabases.length > 1) {
                        // Show in-page selection UI instead of window.prompt
                        this.setState({
                            selecting: true,
                            allowedDatabases,
                            databasesWithSuperuser,
                            selectedOption: allowedDatabases[0],
                            superuserLogin: false,
                            token,
                            nonce,
                            processing: false
                        })
                        return
                    }
                    // Persist the choice for future sign-ins/refreshes (only database)
                    if (selectedDb) {
                        localStorage.setItem('gc2_selected_db', selectedDb)
                        if (this._isMounted) this.setState({savedDb: selectedDb})
                    }
                }

                // If only a single DB and it supports superuser, ask for role selection
                if (selectedDb && overlap.includes(selectedDb)) {
                    const savedSuperuser = localStorage.getItem('gc2_selected_superuser')
                    if (savedSuperuser !== null) {
                        this.proceed(token, nonce, selectedDb, savedSuperuser === '1' || savedSuperuser === 'true')
                        return
                    }
                    this.setState({
                        selecting: true,
                        allowedDatabases: [selectedDb],
                        databasesWithSuperuser,
                        selectedOption: selectedDb,
                        superuserLogin: false,
                        token,
                        nonce,
                        processing: false
                    })
                    return
                }
                this.proceed(token, nonce, selectedDb, false)
            } else {
                if (this._isMounted) this.setState({processing: false})
                // If nonce is not set, get it from the server, so it can be used in the sign-in request
                if (!localStorage.getItem('gc2_nonce')) {
                    fetch('/api/v2/session/nonce').then(res => res.json()).then(data => {
                        const nonce = data.nonce
                        localStorage.setItem('gc2_nonce', nonce)
                        //  codeFlow.signIn()
                    })
                } else {
                    //  codeFlow.signIn()
                }
            }
        }).catch(err => {
            console.error(err.message)
        })
    }

    componentWillUnmount() {
        this._isMounted = false
        window.removeEventListener('storage', this.onStorage)
    }

    proceed(token, nonce, selectedDb, superuser = false) {
        console.log(token)
        console.log(nonce)
        console.log(selectedDb)
        if (!token || !selectedDb) {
            if (this._isMounted) this.setState({processing: false})
            {
                return
            }
        }
        if (this._isMounted) this.setState({processing: true})
        fetch('/api/v2/session/token', {
            method: 'POST',
            headers: {"Content-Type": "application/json;charset=UTF-8"},
            body: JSON.stringify({nonce, token, database: selectedDb, superuser}),
        }).then(res => res.json()).then(data => {
            if (!this._isMounted) return
            if (!data.success) {
                alert(`Error: ${data.message}`)
                this.setState({processing: false})
                codeFlow.clear()
                return
            }
            codeFlow.clear()
            window.location.href = '/dashboard/'
        })
    }

    handleDbSelectChange(e) {
        const selectedOption = e.target.value
        this.setState(prev => ({
            selectedOption,
            // Keep superuserLogin only if selected DB supports it; otherwise force false
            superuserLogin: prev.databasesWithSuperuser.includes(selectedOption) ? prev.superuserLogin : false
        }))
    }

    handleConfirmSelection(e) {
        if (e) e.preventDefault()
        const {selectedOption, token, nonce, superuserSelections} = this.state
        if (!selectedOption) return
        const isSuperuser = !!superuserSelections[selectedOption]
        localStorage.setItem('gc2_selected_db', selectedOption)
        localStorage.setItem('gc2_selected_superuser', isSuperuser ? '1' : '0')
        if (this._isMounted) this.setState({savedDb: selectedOption, selecting: false})
        this.proceed(token, nonce, selectedOption, isSuperuser)
    }

    handleCancelSelection(e) {
        if (e) e.preventDefault()
        localStorage.removeItem('gc2_selected_db')
        this.setState({selecting: false})
        codeFlow.clear()
    }

    handleSuperuserToggle(e) {
        this.setState({ superuserLogin: !!e.target.checked })
    }

    handleSearchChange(e) {
        this.setState({ searchTerm: e.target.value })
    }

    handleDatabaseSuperuserToggle(database, checked) {
        this.setState(prev => ({
            superuserSelections: {
                ...prev.superuserSelections,
                [database]: checked
            }
        }))
    }

    render() {
        const {savedDb, info, superuserLogin, processing} = this.state
        const containerStyle = {
            maxWidth: 420,
            margin: '60px auto',
            padding: 24,
            borderRadius: 12,
            border: '1px solid #e5e7eb',
            background: '#ffffff',
            boxShadow: '0 4px 14px rgba(0,0,0,0.06)'
        }
        const titleStyle = {fontSize: 22, marginBottom: 12}
        const descStyle = {color: '#4b5563', marginBottom: 20, lineHeight: 1.5}
        const badgeStyle = {
            display: 'inline-block',
            padding: '4px 10px',
            background: '#f3f4f6',
            borderRadius: 999,
            fontSize: 12,
            color: '#374151',
            marginBottom: 16
        }
        const btnRowStyle = {display: 'flex', gap: 12}
        const primaryBtn = {
            padding: '10px 14px',
            background: '#2563eb',
            color: '#fff',
            border: 0,
            borderRadius: 8,
            cursor: 'pointer'
        }
        const secondaryBtn = {
            padding: '10px 14px',
            background: '#f3f4f6',
            color: '#111827',
            border: 0,
            borderRadius: 8,
            cursor: 'pointer'
        }
        const infoStyle = {
            color: '#065f46',
            background: '#ecfdf5',
            padding: '8px 10px',
            borderRadius: 8,
            marginTop: 14,
            fontSize: 13
        }

        return (
            <div style={containerStyle}>
                <div style={titleStyle}><FormattedMessage id="Sign in" /></div>
                {processing ? (
                    <div style={descStyle}><FormattedMessage id="Processing login, please wait..." /></div>
                ) : (
                    <>
                        <div style={descStyle}>
                            <FormattedMessage id="Start the login process" />
                        </div>
                        {savedDb ? (
                            <div style={badgeStyle}>
                                <FormattedMessage
                                    id="Current selection"
                                    values={{db: savedDb, superuser: superuserLogin === '1' ? '(super)' : ''}}
                                />
                            </div>
                        ) : (
                            <div style={badgeStyle}><FormattedMessage id="No database selected yet" /></div>
                        )}
                        {this.state.selecting ? (
                            <div>
                                <div style={{fontSize: 16, margin: '16px 0'}}><FormattedMessage id="Select a database" /></div>
                                <div style={{marginBottom: 12}}>
                                    <input
                                        type="text"
                                        placeholder="Search for a database..."
                                        value={this.state.searchTerm}
                                        onChange={this.handleSearchChange}
                                        style={{
                                            width: '100%',
                                            padding: '10px 12px',
                                            border: '1px solid #d1d5db',
                                            borderRadius: 8,
                                            fontSize: 14,
                                            boxSizing: 'border-box'
                                        }}
                                    />
                                </div>
                                <div style={{
                                    maxHeight: 200,
                                    overflowY: 'auto',
                                    border: '1px solid #e5e7eb',
                                    borderRadius: 8,
                                    marginBottom: 12
                                }}>
                                    {this.state.allowedDatabases
                                        .filter(d => d.toLowerCase().includes(this.state.searchTerm.toLowerCase()))
                                        .map(d => (
                                            <div key={d} style={{
                                                display: 'flex',
                                                alignItems: 'center',
                                                justifyContent: 'space-between',
                                                padding: '10px 12px',
                                                background: this.state.selectedOption === d ? '#eff6ff' : 'transparent',
                                                borderBottom: '1px solid #f3f4f6',
                                                transition: 'background 0.15s'
                                            }}
                                                 onMouseEnter={(e) => {
                                                     if (this.state.selectedOption !== d) {
                                                         e.currentTarget.style.background = '#f9fafb'
                                                     }
                                                 }}
                                                 onMouseLeave={(e) => {
                                                     if (this.state.selectedOption !== d) {
                                                         e.currentTarget.style.background = 'transparent'
                                                     }
                                                 }}>
                                                <label style={{cursor: 'pointer', flex: 1, display: 'flex', alignItems: 'center'}}>
                                                    <input
                                                        type="radio"
                                                        name="db"
                                                        value={d}
                                                        checked={this.state.selectedOption === d}
                                                        onChange={this.handleDbSelectChange}
                                                        style={{marginRight: 8}}
                                                    />
                                                    {d}
                                                </label>
                                                {this.state.databasesWithSuperuser.includes(d) && (
                                                    <label style={{display: 'flex', alignItems: 'center', gap: 6, cursor: 'pointer', fontSize: 13, color: '#6b7280'}}
                                                           onClick={(e) => e.stopPropagation()}>
                                                        <input
                                                            type="checkbox"
                                                            checked={!!this.state.superuserSelections[d]}
                                                            onChange={(e) => this.handleDatabaseSuperuserToggle(d, e.target.checked)}
                                                            onClick={(e) => e.stopPropagation()}
                                                        />
                                                        <span><FormattedMessage id="Superuser" /></span>
                                                    </label>
                                                )}
                                            </div>
                                        ))}
                                    {this.state.allowedDatabases.filter(d => d.toLowerCase().includes(this.state.searchTerm.toLowerCase())).length === 0 && (
                                        <div style={{padding: '10px 12px', color: '#6b7280', fontSize: 14}}>
                                            <FormattedMessage id="No databases found" />
                                        </div>
                                    )}
                                </div>
                                <div style={btnRowStyle}>
                                    <button style={primaryBtn} onClick={this.handleConfirmSelection}>
                                        <FormattedMessage id="Continue" />
                                    </button>
                                    <button style={secondaryBtn} onClick={this.handleCancelSelection}>
                                        <FormattedMessage id="Cancel" />
                                    </button>
                                </div>
                            </div>
                        ) : (
                            <div style={btnRowStyle}>
                                <button style={primaryBtn} onClick={this.handleLogin}>
                                    <FormattedMessage id="Login" />
                                </button>
                                <button style={secondaryBtn} onClick={this.handleResetDb}>
                                    <FormattedMessage id="Reset selected database" />
                                </button>
                            </div>
                        )}
                    </>
                )}
                {info === 'reset' && <div style={infoStyle}><FormattedMessage id="Database reset message" /></div>}
            </div>
        )
    }
}

export default OpenId;