import React from 'react';
import codeFlow from '../../utils/codeFlow'

function OpenId() {

    function handleLogin() {
        codeFlow.signIn()
    }

    codeFlow.redirectHandle().then(isSignedIn => {
        if (isSignedIn) {
            fetch('http://localhost:8080/api/v2/session/nonce').then(res => res.json()).then(data => {
                const nonce = data.nonce
                const token = JSON.parse(localStorage.getItem('gc2_tokens'))['idToken']
                fetch('http://localhost:8080/api/v2/session/token', {
                    method: 'POST',
                    headers: {"Content-Type": "application/json;charset=UTF-8"},
                    body: JSON.stringify({nonce, token}),
                }).then(res => res.json()).then(data => {
                    window.location.href = '/dashboard/'
                })
            })

        } else {
            codeFlow.signIn()
        }
    }).catch(err => {
        // alert(err)
        // location.reload()
    })

    return (
        <>
            <div>
                <a href='#' onClick={handleLogin}>Log in med Open Id</a>
            </div>
        </>
    )
}

export default OpenId;