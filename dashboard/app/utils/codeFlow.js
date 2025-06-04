import {CodeFlow} from "@mapcentia/gc2-js-client";

const codeFlow = new CodeFlow({
    redirectUri: 'http://localhost:8080/dashboard/sign-in',
    // clientId: '81ed11e6-4e76-4c22-bb55-739238ddbe5f',
    clientId: 'geofa',
    host: 'http://127.0.0.1:8080',
    // tokenUri: 'https://login.microsoftonline.com/9fc91e5b-27ae-4660-b4fb-2c590c2e40fd/oauth2/v2.0/token',
    tokenUri: 'http://localhost:8089/realms/master/protocol/openid-connect/token',
    // authUri: 'https://login.microsoftonline.com/9fc91e5b-27ae-4660-b4fb-2c590c2e40fd/oauth2/v2.0/authorize',
    authUri: 'http://localhost:8089/realms/master/protocol/openid-connect/auth',
    scope: 'openid'
    // scope: 'basic'
})

export default codeFlow;