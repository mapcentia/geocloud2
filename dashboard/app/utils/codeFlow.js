import {CodeFlow} from "@mapcentia/gc2-js-client";
import {getGC2ConfigurationCall} from "../api";

// Fallback defaults to preserve existing behavior if remote config is missing
const DEFAULTS = {
    redirectUri: location.protocol + '//' + location.host,
    clientId: 'gc2-cli',
    host: location.protocol + '//' + location.host ,
    scope: 'openid'
};

// Attempt to extract OIDC settings from a variety of likely shapes
function extractOidcConfig(raw) {
    const oidc = raw.gc2Options.openIdConfig;
    const redirectUri = oidc.redirectUris.gc2 || DEFAULTS.redirectUri;
    const clientId = oidc.clientId || DEFAULTS.clientId;
    const host = oidc.host || DEFAULTS.host;
    const tokenUri = oidc.tokenUri || undefined;
    const authUri = oidc.authUri || undefined;
    const logoutUri = oidc.logoutUri || undefined;
    const scope = oidc.scope || DEFAULTS.scope;
    return {redirectUri, clientId, host, tokenUri, authUri, scope, logoutUri};
}

// Create the CodeFlow instance once configuration is available.
// If fetching/parse fails, fall back to defaults so login still works.
let codeFlowInstancePromise = getGC2ConfigurationCall()
    .then(res => {
        const data = res && res.data ? res.data : {};
        const params = extractOidcConfig(data);
        return new CodeFlow(params);
    })
    .catch(() => new CodeFlow(DEFAULTS));

function ensureInstance() {
    return codeFlowInstancePromise;
}

// Export a thin async façade to keep existing imports working
let redirectHandlePromise = null;
const codeFlow = {
    signIn: () => {
        redirectHandlePromise = null;
        return ensureInstance().then(cf => cf.signIn());
    },
    redirectHandle: () => {
        if (!redirectHandlePromise) {
            redirectHandlePromise = ensureInstance().then(cf => cf.redirectHandle());
            // Reset the promise if it fails or returns false, so subsequent attempts can try again
            redirectHandlePromise.then(res => {
                if (!res) redirectHandlePromise = null;
            }).catch(() => {
                redirectHandlePromise = null;
            });
        }
        return redirectHandlePromise;
    },
    signOut: () => {
        redirectHandlePromise = null;
        return ensureInstance().then(cf => cf.signOut());
    },
    clear: () => {
        redirectHandlePromise = null;
        return ensureInstance().then(cf => cf.clear());
    },
};

export default codeFlow;