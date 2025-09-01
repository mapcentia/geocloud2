import React from 'react';
import codeFlow from '../../utils/codeFlow'

function OpenId() {

    function handleLogin() {
        codeFlow.signIn()
    }

    codeFlow.redirectHandle().then(isSignedIn => {
        if (isSignedIn) {
            const token = JSON.parse(localStorage.getItem('gc2_tokens'))['idToken']
            const nonce = localStorage.getItem('gc2_nonce')
            fetch('http://localhost:8080/api/v2/session/token', {
                method: 'POST',
                headers: {"Content-Type": "application/json;charset=UTF-8"},
                body: JSON.stringify({nonce, token}),
            }).then(res => res.json()).then(data => {
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

    return (
        <>
            <div>
                <a href='#' onClick={handleLogin}>Log in med Open Id</a>
            </div>
        </>
    )
}

export default OpenId;