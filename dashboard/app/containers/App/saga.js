import { push } from 'react-router-redux';
import { call, put, takeLatest } from 'redux-saga/effects';
import { checkAuthorizationSuccess, checkAuthorizationFailure,
    signInSuccess, signInFailure,
    getDatabasesSuccess, getDatabasesFailure,
    createUserSuccess, createUserFailure,
    updateUserSuccess, updateUserFailure, updateUserPasswordSuccess,
    deleteUserSuccess, deleteUserFailure,
    getSubusersRequest, getSubusersSuccess, getSubusersFailure,
    getSchemasRequest, getSchemasSuccess, getSchemasFailure,
    getConfigurationsSuccess, getConfigurationsFailure,
    createConfigurationSuccess, createConfigurationFailure,
    updateConfigurationSuccess, updateConfigurationFailure,
    deleteConfigurationSuccess, deleteConfigurationFailure, getConfigurationsRequest,
    getGC2ConfigurationSuccess, checkAuthorizationRequest } from 'containers/App/actions';

import { changeLocale } from 'containers/LanguageProvider/actions';

import { CHECK_AUTHORIZATION_REQUEST, CHECK_AUTHORIZATION_SUCCESS,
    SIGN_IN_REQUEST, SIGN_OUT,
    GET_DATABASES_REQUEST,
    CREATE_USER_REQUEST, UPDATE_USER_REQUEST, DELETE_USER_REQUEST,
    GET_CONFIGURATIONS_REQUEST, CREATE_CONFIGURATION_REQUEST, UPDATE_CONFIGURATION_REQUEST, DELETE_CONFIGURATION_REQUEST,
    GET_SUBUSERS_REQUEST, GET_SCHEMAS_REQUEST, GET_GC2_CONFIGURATION_REQUEST } from 'containers/App/constants';

import {checkAuthorizationCall, signInCall, createUserCall, updateUserCall, getDatabasesCall,
    getSubusersCall, getSchemasCall, deleteUserCall, getConfigurationsCall,
    createConfigurationCall, updateConfigurationCall, deleteConfigurationCall, getGC2ConfigurationCall} from 'api';

const appBaseURL = (process.env.WEBPACK_PUBLIC_PATH ? process.env.WEBPACK_PUBLIC_PATH : `/`);

export function* checkAuthorizationGenerator() {
    try {
        const response = yield call(checkAuthorizationCall);
        yield put(checkAuthorizationSuccess(response.data.data));
    } catch (err) {
        yield put(checkAuthorizationFailure());
    }
}

export function* signInGenerator(credentials) {
    try {
        const result = yield call(signInCall, credentials);
        yield put(signInSuccess(result.data.data));

        if (result.data.data.passwordExpired) {
            yield put(push(`${appBaseURL}account`));
        } else {
            yield put(push(appBaseURL));
        }
    } catch (err) {
        yield put(signInFailure());
    }
}

export function* getDatabasesGenerator(data) {
    try {
        const result = yield call(getDatabasesCall, data);
        yield put(getDatabasesSuccess({
            databases: result.data.databases,
            userName: data.payload
        }));
    } catch (err) {
        yield put(getDatabasesFailure());
    }
}

export function* createUserGenerator(data) {
    const response = yield call(createUserCall, data);
    try {
        if (response.status && response.status === 200) {
            yield put(createUserSuccess(response.data.data.screenname));
        } else {
            if (response.data && response.data.errorCode) {
                yield put(createUserFailure(response.data.errorCode));
            } else {
                yield put(createUserFailure());
            }
        }
    } catch(err) {
        console.error(err);
        yield put(createUserFailure());
    }
}

export function* signOutGenerator() {
    yield put(push(`${appBaseURL}sign-in`));
}

export function* updateUserGenerator(action) {
    try {
        const response = yield call(updateUserCall, action);
        if (response.status === 200) {
            yield put(updateUserSuccess());
            if (action.payload.data.onSuccess) action.payload.data.onSuccess();
            if (action.payload.data && action.payload.data.oldPassword && action.payload.data.newPassword) {
                yield put(updateUserPasswordSuccess());
            }
        } else if (response.status === 403) {
            yield put(updateUserFailure(response.data.errorCode));
        } else {
            if (response.data && response.data.errorCode) {
                yield put(updateUserFailure(response.data.errorCode));
            } else {
                yield put(updateUserFailure());
            }
        }
    } catch(err) {
        yield put(updateUserFailure());
    }
}

export function* deleteUserGenerator(action) {
    const response = yield call(deleteUserCall, action);
    try {
        yield put(deleteUserSuccess());
        yield put(getSubusersRequest(action));
    } catch(err) {
        console.error(err);
        yield put(deleteUserFailure());
    }
}

export function* getSubusersGenerator(action) {
    const response = yield call(getSubusersCall, action);
    try {
        yield put(getSubusersSuccess(response.data.data));
    } catch(err) {
        yield put(getSubusersFailure());
    }
}

export function* getSchemasGenerator(action) {
    const response = yield call(getSchemasCall, action);
    try {
        yield put(getSchemasSuccess(response.data.data));
    } catch(err) {
        yield put(getSchemasFailure());
    }
}

export function* getConfigurationsGenerator(action) {
    const response = yield call(getConfigurationsCall, action);
    try {
        yield put(getConfigurationsSuccess(response.data.data));
    } catch(err) {
        yield put(getConfigurationsFailure());
    }
}

export function* forceUserUpdateGenerator(action) {
    if (action.payload.passwordExpired) {
        yield put(push(`${appBaseURL}account`));
    }
}

export function* createConfigurationGenerator(action) {
    const response = yield call(createConfigurationCall, action);
    try {
        yield put(createConfigurationSuccess(response.data.data));
    } catch(err) {
        yield put(createConfigurationFailure());
    }
}

export function* updateConfigurationGenerator(action) {
    const response = yield call(updateConfigurationCall, action);
    try {
        yield put(updateConfigurationSuccess(response.data.data));
    } catch(err) {
        yield put(updateConfigurationFailure());
    }
}

export function* deleteConfigurationGenerator(action) {
    const response = yield call(deleteConfigurationCall, action);
    try {
        yield put(deleteConfigurationSuccess(response.data.data));
        yield put(getConfigurationsRequest(action.payload));
    } catch(err) {
        yield put(deleteConfigurationFailure());
    }
}

export function* getGC2ConfigurationGenerator() {
    const response = yield call(getGC2ConfigurationCall);
    try {
        yield put(getGC2ConfigurationSuccess(response.data));
        yield put(checkAuthorizationRequest({}));
        if (response.data && response.data.gc2Al) {
            if (response.data.gc2Al.indexOf(`da_`) === 0) {
                yield put(changeLocale(`da`));
            } else if (response.data.gc2Al.indexOf(`en_`) === 0) {
                yield put(changeLocale(`en`));
            }
        }
    } catch(err) {
        yield put(getGC2ConfigurationSuccess({}));
        yield put(checkAuthorizationRequest({}));

    }
}

export default function* checkAuthorization() {
    yield takeLatest(CHECK_AUTHORIZATION_REQUEST, checkAuthorizationGenerator);
    yield takeLatest(SIGN_IN_REQUEST, signInGenerator);
    yield takeLatest(GET_DATABASES_REQUEST, getDatabasesGenerator);
    yield takeLatest(SIGN_OUT, signOutGenerator);
    yield takeLatest(CREATE_USER_REQUEST, createUserGenerator);
    yield takeLatest(UPDATE_USER_REQUEST, updateUserGenerator);
    yield takeLatest(DELETE_USER_REQUEST, deleteUserGenerator);
    yield takeLatest(CHECK_AUTHORIZATION_SUCCESS, forceUserUpdateGenerator);
    yield takeLatest(GET_SUBUSERS_REQUEST, getSubusersGenerator);
    yield takeLatest(GET_SCHEMAS_REQUEST, getSchemasGenerator);
    yield takeLatest(GET_CONFIGURATIONS_REQUEST, getConfigurationsGenerator);
    yield takeLatest(CREATE_CONFIGURATION_REQUEST, createConfigurationGenerator);
    yield takeLatest(UPDATE_CONFIGURATION_REQUEST, updateConfigurationGenerator);
    yield takeLatest(DELETE_CONFIGURATION_REQUEST, deleteConfigurationGenerator);
    yield takeLatest(GET_GC2_CONFIGURATION_REQUEST, getGC2ConfigurationGenerator);
}
