import React, { Component } from 'react';
import {jwtDecode} from 'jwt-decode'
import codeFlow from '../../utils/codeFlow'

class OpenId extends Component {
    constructor(props) {
        super(props)
        this.state = {
            savedDb: typeof window !== 'undefined' ? (localStorage.getItem('gc2_selected_db') || '') : '',
            info: ''
        }
        this.handleLogin = this.handleLogin.bind(this)
        this.handleResetDb = this.handleResetDb.bind(this)
        this.onStorage = this.onStorage.bind(this)
        this._isMounted = false
    }

    handleLogin(e) {
        if (e) e.preventDefault()
        codeFlow.signIn()
    }

    handleResetDb(e) {
        if (e) e.preventDefault()
        localStorage.removeItem('gc2_selected_db')
        this.setState({ savedDb: '', info: 'Database selection has been reset. You will be asked to choose on next sign-in.' })
    }

    onStorage() {
        this.setState({ savedDb: localStorage.getItem('gc2_selected_db') || '' })
    }

    componentDidMount() {
        this._isMounted = true
        window.addEventListener('storage', this.onStorage)

        codeFlow.redirectHandle().then(isSignedIn => {
            if (!this._isMounted) return
            if (isSignedIn) {
                const token = JSON.parse(localStorage.getItem('gc2_tokens'))['idToken']
                const nonce = localStorage.getItem('gc2_nonce')
                const {database} = jwtDecode(token)
                if (!database) {
                    alert(`No database set in token. Please contact the administrator.`)
                    codeFlow.clear()
                    return
                }
                const allowedDatabases = database.split(',').map(d => d.trim()).filter(Boolean)

                // Try to use a previously saved selection
                const savedDbLocal = localStorage.getItem('gc2_selected_db')
                let selectedDb
                if (savedDbLocal && allowedDatabases.includes(savedDbLocal)) {
                    selectedDb = savedDbLocal
                } else {
                    // If more than one database is allowed, ask the user to pick one
                    selectedDb = allowedDatabases[0]
                    if (allowedDatabases.length > 1) {
                        const message = `Multiple databases are available. Please pick one by entering its number:\n\n${allowedDatabases.map((d, i) => `${i + 1}. ${d}`).join('\n')}`
                        const input = window.prompt(message, '1')
                        if (input === null) {
                            // User canceled selection
                            localStorage.removeItem('gc2_selected_db')
                            codeFlow.clear()
                            return
                        }
                        const idx = parseInt(input, 10)
                        if (Number.isNaN(idx) || idx < 1 || idx > allowedDatabases.length) {
                            alert('Invalid selection. Please try signing in again and choose a valid option.')
                            localStorage.removeItem('gc2_selected_db')
                            codeFlow.clear()
                            return
                        }
                        selectedDb = allowedDatabases[idx - 1]
                    }
                    // Persist the choice for future sign-ins/refreshes
                    if (selectedDb) {
                        localStorage.setItem('gc2_selected_db', selectedDb)
                        if (this._isMounted) this.setState({ savedDb: selectedDb })
                    }
                }

                fetch('http://localhost:8080/api/v2/session/token', {
                    method: 'POST',
                    headers: {"Content-Type": "application/json;charset=UTF-8"},
                    body: JSON.stringify({nonce, token, database: selectedDb}),
                }).then(res => res.json()).then(data => {
                    if (!this._isMounted) return
                    if (!data.success) {
                        alert(`Error: ${data.message}`)
                        codeFlow.clear()
                        return
                    }
                    codeFlow.clear()
                    window.location.href = '/dashboard/'
                })
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

    render() {
        const { savedDb, info } = this.state
        const containerStyle = {
            maxWidth: 420,
            margin: '60px auto',
            padding: 24,
            borderRadius: 12,
            border: '1px solid #e5e7eb',
            background: '#ffffff',
            boxShadow: '0 4px 14px rgba(0,0,0,0.06)'
        }
        const titleStyle = { fontSize: 22, marginBottom: 12 }
        const descStyle = { color: '#4b5563', marginBottom: 20, lineHeight: 1.5 }
        const badgeStyle = { display: 'inline-block', padding: '4px 10px', background: '#f3f4f6', borderRadius: 999, fontSize: 12, color: '#374151', marginBottom: 16 }
        const btnRowStyle = { display: 'flex', gap: 12 }
        const primaryBtn = { padding: '10px 14px', background: '#2563eb', color: '#fff', border: 0, borderRadius: 8, cursor: 'pointer' }
        const secondaryBtn = { padding: '10px 14px', background: '#f3f4f6', color: '#111827', border: 0, borderRadius: 8, cursor: 'pointer' }
        const infoStyle = { color: '#065f46', background: '#ecfdf5', padding: '8px 10px', borderRadius: 8, marginTop: 14, fontSize: 13 }

        return (
            <div style={containerStyle}>
                <div style={titleStyle}>Sign in</div>
                <div style={descStyle}>Use your OpenID provider to sign in. If your account allows multiple databases, you'll be asked to choose one during sign-in.</div>
                {savedDb ? (
                    <div style={badgeStyle}>Current selection: {savedDb}</div>
                ) : (
                    <div style={badgeStyle}>No database selected yet</div>
                )}
                <div style={btnRowStyle}>
                    <button style={primaryBtn} onClick={this.handleLogin}>Sign in with OpenID</button>
                    <button style={secondaryBtn} onClick={this.handleResetDb}>Reset database selection</button>
                </div>
                {info && <div style={infoStyle}>{info}</div>}
            </div>
        )
    }
}

export default OpenId;