import {CodeFlow} from "@mapcentia/gc2-js-client";
import {getGC2ConfigurationCall} from "../api";

// Fallback defaults to preserve existing behavior if remote config is missing
const DEFAULTS = {
    redirectUri: 'http://localhost:8080',
    clientId: 'gc2-cli',
    host: 'http://localhost:8080',
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
const codeFlow = {
    signIn: () => ensureInstance().then(cf => cf.signIn()),
    redirectHandle: () => ensureInstance().then(cf => cf.redirectHandle()),
    signOut: () => ensureInstance().then(cf => cf.signOut()),
    clear: () => ensureInstance().then(cf => cf.clear()),
};

export default codeFlow;