import Cookies from 'universal-cookie';
import { CHECK_AUTHORIZATION_REQUEST, CHECK_AUTHORIZATION_SUCCESS, CHECK_AUTHORIZATION_FAILURE } from 'containers/App/constants';
import { SIGN_IN_REQUEST, SIGN_IN_SUCCESS, SIGN_IN_FAILURE, SIGN_OUT,
    GET_DATABASES_RESET, GET_DATABASES_REQUEST, GET_DATABASES_SUCCESS, GET_DATABASES_FAILURE,
    CREATE_USER_RESET, CREATE_USER_REQUEST, CREATE_USER_SUCCESS, CREATE_USER_FAILURE,
    UPDATE_USER_REQUEST, UPDATE_USER_SUCCESS, UPDATE_USER_FAILURE, UPDATE_USER_PASSWORD_SUCCESS,
    GET_SUBUSERS_REQUEST, GET_SUBUSERS_SUCCESS, GET_SUBUSERS_FAILURE,
    GET_SCHEMAS_REQUEST, GET_SCHEMAS_SUCCESS, GET_SCHEMAS_FAILURE,
    DELETE_USER_REQUEST, DELETE_USER_SUCCESS, DELETE_USER_FAILURE,
    GET_CONFIGURATIONS_REQUEST, GET_CONFIGURATIONS_SUCCESS, GET_CONFIGURATIONS_FAILURE,
    GET_CONFIGURATION_REQUEST, GET_CONFIGURATION_SUCCESS, GET_CONFIGURATION_FAILURE,
    CREATE_UPDATE_CONFIGURATION_RESET, CREATE_CONFIGURATION_REQUEST, CREATE_CONFIGURATION_SUCCESS, CREATE_CONFIGURATION_FAILURE,
    UPDATE_CONFIGURATION_REQUEST, UPDATE_CONFIGURATION_SUCCESS, UPDATE_CONFIGURATION_FAILURE,
    DELETE_CONFIGURATION_REQUEST, DELETE_CONFIGURATION_SUCCESS, DELETE_CONFIGURATION_FAILURE,
    CREATE_UPDATE_USER_RESET, GET_GC2_CONFIGURATION_REQUEST, GET_GC2_CONFIGURATION_SUCCESS} from 'containers/App/constants';

const cookies = new Cookies();

const normalizeUser = (userData) => {
    if (!userData.email || !userData.screen_name ||
        `passwordExpired` in userData === false || `subuser` in userData === false) {
        console.error(`User data is invalid`, userData);
        throw new Error(`Unexpected format of the user data`);
    }

    let data = {
        email: userData.email,
        passwordExpired: userData.passwordExpired,
        screenName: userData.screen_name,
        parentDb: userData.parentdb,
        subuser: userData.subuser,
    };

    return data;
}

// Reset state
export function createUpdateUserReset() { return { type: CREATE_UPDATE_USER_RESET }; }

// Create user
export function createUserReset() { return { type: CREATE_USER_RESET }; }
export function createUserRequest(payload) { return { type: CREATE_USER_REQUEST, payload }; }
export function createUserSuccess(payload) { return { type: CREATE_USER_SUCCESS, payload}; }
export function createUserFailure(payload = false) { return { type: CREATE_USER_FAILURE, payload }; }

// Update user
export function updateUserRequest(screenName, data) { return { type: UPDATE_USER_REQUEST, payload: {screenName, data} }; }
export function updateUserSuccess(payload) { return { type: UPDATE_USER_SUCCESS, payload}; }
export function updateUserFailure(payload = false) { return { type: UPDATE_USER_FAILURE, payload }; }
export function updateUserPasswordSuccess() { return { type: UPDATE_USER_PASSWORD_SUCCESS }; }

// Delete user
export function deleteUserRequest(payload) { return { type: DELETE_USER_REQUEST, payload }; }
export function deleteUserSuccess() { return { type: DELETE_USER_SUCCESS }; }
export function deleteUserFailure() { return { type: DELETE_USER_FAILURE }; }

export function getGC2ConfigurationRequest() {
    return { type: GET_GC2_CONFIGURATION_REQUEST };
}

export function getGC2ConfigurationSuccess(payload) {
    return { type: GET_GC2_CONFIGURATION_SUCCESS, payload };
}

export function checkAuthorizationRequest() {
    if (cookies.get('PHPSESSID')) {
        // There is a chance that session is still valid, checking
        return { type: CHECK_AUTHORIZATION_REQUEST };
    } else {
        // No session cookie, so no session for sure
        return { type: CHECK_AUTHORIZATION_FAILURE };
    }
}

// Check authorization status
export function checkAuthorizationSuccess(payload) { return { type: CHECK_AUTHORIZATION_SUCCESS, payload: normalizeUser(payload) }; }
export function checkAuthorizationFailure() { 
    cookies.remove('PHPSESSID');
    return { type: CHECK_AUTHORIZATION_FAILURE };
}
export function signInRequest(payload) { return { type: SIGN_IN_REQUEST, payload }; }

// Get databases for available user
export function getDatabasesReset() { return { type: GET_DATABASES_RESET }; }
export function getDatabasesRequest(payload) { return { type: GET_DATABASES_REQUEST, payload }; }
export function getDatabasesSuccess(payload) { return { type: GET_DATABASES_SUCCESS, payload }; }
export function getDatabasesFailure(payload) { return { type: GET_DATABASES_FAILURE, payload }; }

// Sign in
export function signInSuccess(payload) {
    return { type: SIGN_IN_SUCCESS, payload: normalizeUser(payload) };
}

export function signInFailure() {
    return { type: SIGN_IN_FAILURE };
}

// Sign out
export function signOut() {
    cookies.remove('PHPSESSID', {path: `/`});
    return { type: SIGN_OUT };
}

// Get the list of subusers
export function getSubusersRequest(payload) { return { type: GET_SUBUSERS_REQUEST, payload }; }
export function getSubusersSuccess(payload) { return { type: GET_SUBUSERS_SUCCESS, payload}; }
export function getSubusersFailure() { return { type: GET_SUBUSERS_FAILURE }; }

// Get the list of schemas
export function getSchemasRequest() { return { type: GET_SCHEMAS_REQUEST }; }
export function getSchemasSuccess(payload) { return { type: GET_SCHEMAS_SUCCESS, payload}; }
export function getSchemasFailure() { return { type: GET_SCHEMAS_FAILURE }; }

// Get the list of configurations
export function getConfigurationsRequest(payload) { return { type: GET_CONFIGURATIONS_REQUEST, payload }; }
export function getConfigurationsSuccess(payload) { return { type: GET_CONFIGURATIONS_SUCCESS, payload }; }
export function getConfigurationsFailure() { return { type: GET_CONFIGURATIONS_FAILURE }; }

// Get specific configuration
export function getConfigurationRequest(payload) { return { type: GET_CONFIGURATION_REQUEST, payload }; }
export function getConfigurationSuccess(payload) { return { type: GET_CONFIGURATION_SUCCESS, payload }; }
export function getConfigurationFailure() { return { type: GET_CONFIGURATION_FAILURE }; }

// Resetting create / update form
export function createUpdateConfigurationReset(payload) { return { type: CREATE_UPDATE_CONFIGURATION_RESET, payload }; }

// Create configuration
export function createConfigurationRequest(payload) { return { type: CREATE_CONFIGURATION_REQUEST, payload }; }
export function createConfigurationSuccess(payload) { return { type: CREATE_CONFIGURATION_SUCCESS, payload }; }
export function createConfigurationFailure() { return { type: CREATE_CONFIGURATION_FAILURE }; }

// Update configuration
export function updateConfigurationRequest(payload) { return { type: UPDATE_CONFIGURATION_REQUEST, payload }; }
export function updateConfigurationSuccess(payload) { return { type: UPDATE_CONFIGURATION_SUCCESS, payload }; }
export function updateConfigurationFailure() { return { type: UPDATE_CONFIGURATION_FAILURE }; }

// Delete configuration
export function deleteConfigurationRequest(payload) { return { type: DELETE_CONFIGURATION_REQUEST, payload }; }
export function deleteConfigurationSuccess() { return { type: DELETE_CONFIGURATION_SUCCESS }; }
export function deleteConfigurationFailure() { return { type: DELETE_CONFIGURATION_FAILURE }; }
