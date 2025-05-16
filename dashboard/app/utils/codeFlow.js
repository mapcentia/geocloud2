import {CodeFlow} from "@mapcentia/gc2-js-client";

const codeFlow = new CodeFlow({
    redirectUri: 'http://localhost:8080/dashboard/sign-in',
    clientId: '81ed11e6-4e76-4c22-bb55-739238ddbe5f',
    host: 'http://127.0.0.1:8080',
    tokenUri: 'https://login.microsoftonline.com/9fc91e5b-27ae-4660-b4fb-2c590c2e40fd/oauth2/v2.0/token',
    authUri: 'https://login.microsoftonline.com/9fc91e5b-27ae-4660-b4fb-2c590c2e40fd/oauth2/v2.0/authorize',
    scope: 'openid'
})

export default codeFlow;