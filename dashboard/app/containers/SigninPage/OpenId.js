import React, {Component} from 'react';
import {jwtDecode} from 'jwt-decode'
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
            nonce: null
        }
        this.handleLogin = this.handleLogin.bind(this)
        this.handleResetDb = this.handleResetDb.bind(this)
        this.onStorage = this.onStorage.bind(this)
        this.handleDbSelectChange = this.handleDbSelectChange.bind(this)
        this.handleConfirmSelection = this.handleConfirmSelection.bind(this)
        this.handleCancelSelection = this.handleCancelSelection.bind(this)
        this.handleSuperuserToggle = this.handleSuperuserToggle.bind(this)
        this.proceed = this.proceed.bind(this)
        this._isMounted = false
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
            info: 'Database and superuser selection have been reset. You will be asked to choose on next sign-in.'
        })
    }

    onStorage() {
        this.setState({savedDb: localStorage.getItem('gc2_selected_db') || ''})
    }

    async getDatabases() {
        return fetch('http://localhost:8080/api/v2/database/search?userIdentifier=*').then(res => res.json()).then(data =>
            data.databases.map(item => item.screenname)
        )
    }

    componentDidMount() {
        this._isMounted = true
        window.addEventListener('storage', this.onStorage)

        codeFlow.redirectHandle().then(async isSignedIn => {
            if (!this._isMounted) return
            if (isSignedIn) {
                const token = JSON.parse(localStorage.getItem('gc2_tokens'))['idToken']
                const nonce = localStorage.getItem('gc2_nonce')
                const {database} = jwtDecode(token)
                const {superuser} = jwtDecode(token)
                if (!database) {
                    alert(`No database set in token. Please contact the administrator.`)
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
                            nonce
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
                            nonce
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
                        nonce
                    })
                    return
                }

                this.proceed(token, nonce, selectedDb, false)
            } else {
                // If nonce is not set, get it from the server, so it can be used in the sign-in request
                if (!localStorage.getItem('gc2_nonce')) {
                    fetch('http://localhost:8080/api/v2/session/nonce').then(res => res.json()).then(data => {
                        const nonce = data.nonce
                        localStorage.setItem('gc2_nonce', nonce)
                        //  codeFlow.signIn()
                    })
                } else {
                    //  codeFlow.signIn()
                }
            }
        }).catch(err => {
            // alert(err)
            // location.reload()
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
        if (!token || !selectedDb) return
        fetch('http://localhost:8080/api/v2/session/token', {
            method: 'POST',
            headers: {"Content-Type": "application/json;charset=UTF-8"},
            body: JSON.stringify({nonce, token, database: selectedDb, superuser}),
        }).then(res => res.json()).then(data => {
            if (!this._isMounted) return
            if (!data.success) {
                alert(`Error: ${data.message}`)
                codeFlow.clear()
                return
            }
            codeFlow.clear()
            //window.location.href = '/dashboard/'
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
        const {selectedOption, token, nonce, superuserLogin} = this.state
        if (!selectedOption) return
        localStorage.setItem('gc2_selected_db', selectedOption)
        localStorage.setItem('gc2_selected_superuser', superuserLogin ? '1' : '0')
        if (this._isMounted) this.setState({savedDb: selectedOption, selecting: false})
        this.proceed(token, nonce, selectedOption, !!superuserLogin)
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

    render() {
        const {savedDb, info, superuserLogin} = this.state
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
                <div style={titleStyle}>Sign in</div>
                <div style={descStyle}>Use your OpenID provider to sign in. If your account allows multiple databases,
                    you'll be asked to choose one during sign-in.
                </div>
                {savedDb ? (
                    <div style={badgeStyle}>Current selection: {savedDb} {superuserLogin ? '(super)': ''}</div>
                ) : (
                    <div style={badgeStyle}>No database selected yet</div>
                )}
                {this.state.selecting ? (
                    <div>
                        <div style={{fontSize: 16, margin: '16px 0'}}>Choose a database</div>
                        <div>
                            {this.state.allowedDatabases.map(d => (
                                <label key={d} style={{display: 'block', marginBottom: 8}}>
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
                            ))}
                        </div>
                        {this.state.selectedOption && this.state.databasesWithSuperuser.includes(this.state.selectedOption) && (
                            <label style={{display: 'flex', alignItems: 'center', gap: 8, margin: '12px 0'}}>
                                <input
                                    type="checkbox"
                                    checked={!!this.state.superuserLogin}
                                    onChange={this.handleSuperuserToggle}
                                />
                                <span>Sign in as superuser for {this.state.selectedOption}</span>
                            </label>
                        )}
                        <div style={btnRowStyle}>
                            <button style={primaryBtn} onClick={this.handleConfirmSelection}>Continue</button>
                            <button style={secondaryBtn} onClick={this.handleCancelSelection}>Cancel</button>
                        </div>
                    </div>
                ) : (
                    <div style={btnRowStyle}>
                        <button style={primaryBtn} onClick={this.handleLogin}>Sign in with OpenID</button>
                        <button style={secondaryBtn} onClick={this.handleResetDb}>Reset database selection</button>
                    </div>
                )}
                {info && <div style={infoStyle}>{info}</div>}
            </div>
        )
    }
}

export default OpenId;