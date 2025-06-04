import { fromJS } from 'immutable';

import { SIGN_OUT, CHECK_AUTHORIZATION_REQUEST, CHECK_AUTHORIZATION_SUCCESS, CHECK_AUTHORIZATION_FAILURE,
    SIGN_IN_REQUEST, SIGN_IN_SUCCESS, SIGN_IN_FAILURE,
    GET_DATABASES_RESET, GET_DATABASES_REQUEST, GET_DATABASES_SUCCESS, GET_DATABASES_FAILURE,
    CREATE_USER_RESET, CREATE_USER_REQUEST, CREATE_USER_SUCCESS, CREATE_USER_FAILURE,
    UPDATE_USER_REQUEST, UPDATE_USER_SUCCESS, UPDATE_USER_FAILURE, UPDATE_USER_PASSWORD_SUCCESS,
    GET_SUBUSERS_REQUEST, GET_SUBUSERS_SUCCESS, GET_SUBUSERS_FAILURE,
    GET_SCHEMAS_REQUEST, GET_SCHEMAS_SUCCESS, GET_SCHEMAS_FAILURE,
    GET_CONFIGURATIONS_REQUEST, GET_CONFIGURATIONS_SUCCESS, GET_CONFIGURATIONS_FAILURE,
    CREATE_UPDATE_CONFIGURATION_RESET, CREATE_CONFIGURATION_REQUEST, CREATE_CONFIGURATION_SUCCESS, CREATE_CONFIGURATION_FAILURE,
    UPDATE_CONFIGURATION_REQUEST, UPDATE_CONFIGURATION_SUCCESS, UPDATE_CONFIGURATION_FAILURE,
    GET_GC2_CONFIGURATION_REQUEST, GET_GC2_CONFIGURATION_SUCCESS,
    CREATE_UPDATE_USER_RESET } from 'containers/App/constants';

const initialState = fromJS({
    gc2Configuration: false,
    gc2ConfigurationLoading: false,

    isAuthenticating: true,
    isAuthenticated: false,
    user: false,

    isRequesting: false,

    signingIn: false,
    signingInSuccess: false,
    signingInError: false,

    databaseError: false,

    createUser: false,
    createUserSuccess: false,
    createUserSuccessUserName: false,
    createUserError: false,
    createUserErrorCode: ``,

    updateUser: false,
    updateUserSuccess: false,
    updateUserSuccessUserName: false,
    updateUserError: false,
    updateUserErrorCode: ``,

    createConfiguration: false,
    createConfigurationSuccess: false,
    createConfigurationError: false,

    updateConfiguration: false,
    updateConfigurationSuccess: false,
    updateConfigurationError: false,

    availableDatabasesList: false,
    availableDatabasesUserName: ``,

    subusers: [],
    schemas: [],
    configurations: [],
});

function appReducer(state = initialState, action) {
    switch (action.type) {
        case GET_GC2_CONFIGURATION_REQUEST:
            return Object.assign({}, state, {
                gc2Configuration: false,
                gc2ConfigurationLoading: true,
            });
        case GET_GC2_CONFIGURATION_SUCCESS:
            return Object.assign({}, state, {
                gc2Configuration: action.payload,
                gc2ConfigurationLoading: false,
            });
        case SIGN_OUT:
            return Object.assign({}, state, {
                isAuthenticating: false,
                isAuthenticated: false,
                user: false
            });
        case CHECK_AUTHORIZATION_REQUEST:
            return Object.assign({}, state, {
                isAuthenticating: true,
                isAuthenticated: false,
                user: false
            });
        case CHECK_AUTHORIZATION_SUCCESS:
            return Object.assign({}, state, {
                isAuthenticating: false,
                isAuthenticated: true,
                user: action.payload
            });
        case CHECK_AUTHORIZATION_FAILURE:
            return Object.assign({}, state, {
                isAuthenticating: false,
                isAuthenticated: false,
                user: false
            });
        case SIGN_IN_REQUEST:
            return Object.assign({}, state, {
                isRequesting: true,
                signingIn: true,
                signingInSuccess: false,
                signingInError: false,
            });
        case SIGN_IN_SUCCESS:
            return Object.assign({}, state, {
                isRequesting: false,
                signingIn: false,
                signingInSuccess: true,
                signingInError: false,
                user: action.payload,
                isAuthenticated: true
            });
        case SIGN_IN_FAILURE:
            return Object.assign({}, state, {
                isRequesting: false,
                signingIn: false,
                signingInSuccess: false,
                signingInError: true,
                user: false
            });

        case GET_DATABASES_RESET:
            return Object.assign({}, state, {
                availableDatabasesList: false,
                availableDatabasesUserName: ``,
            });
        case GET_DATABASES_REQUEST:
            return Object.assign({}, state, {
                availableDatabasesList: false,
                availableDatabasesUserName: ``,
            });
        case GET_DATABASES_SUCCESS:
            return Object.assign({}, state, {
                availableDatabasesList: action.payload.databases,
                availableDatabasesUserName: action.payload.userName,
                databaseError: false,
            });
        case GET_DATABASES_FAILURE:
            return Object.assign({}, state, {
                databaseError: true,
            });
        case CREATE_USER_RESET:
            return Object.assign({}, state, {
                isRequesting: false,
                createUser: false,
                createUserSuccess: false,
                createUserSuccessUserName: false,
                createUserError: false,
                createUserErrorCode: ``
            });
        case CREATE_USER_REQUEST:
            return Object.assign({}, state, {
                isRequesting: true,
                createUser: true,
                createUserSuccess: false,
                createUserSuccessUserName: false,
                createUserError: false,
                createUserErrorCode: ``
            });
        case CREATE_USER_SUCCESS:
            return Object.assign({}, state, {
                isRequesting: false,
                createUser: false,
                createUserSuccess: true,
                createUserSuccessUserName: action.payload,
                createUserError: false,
            });
        case CREATE_USER_FAILURE:
            return Object.assign({}, state, {
                isRequesting: false,
                createUser: false,
                createUserSuccess: false,
                createUserSuccessUserName: false,
                createUserError: true,
                createUserErrorCode: (action.payload ? action.payload : false),
            });
        case UPDATE_USER_PASSWORD_SUCCESS:
            return Object.assign({}, state, {
                user: Object.assign({}, state.user, {passwordExpired: false})
            });
        case UPDATE_USER_REQUEST:
            return Object.assign({}, state, {
                isRequesting: true,
            });
        case UPDATE_USER_SUCCESS:
            return Object.assign({}, state, {
                isRequesting: false,
                updateUser: false,
                updateUserSuccess: true,
                updateUserSuccessUserName: action.payload,
                updateUserError: false,
                updateUserErrorCode: ``
            });
        case UPDATE_USER_FAILURE:
            return Object.assign({}, state, {
                isRequesting: false,
                updateUser: false,
                updateUserSuccess: false,
                updateUserSuccessUserName: false,
                updateUserError: true,
                updateUserErrorCode: (action.payload ? action.payload : false),
            });
        case CREATE_UPDATE_USER_RESET:
            return Object.assign({}, state, {
                isRequesting: false,

                createUser: initialState.createUser,
                createUserSuccess: initialState.createUserSuccess,
                createUserSuccessUserName: initialState.createUserSuccessUserName,
                createUserError: initialState.createUserError,
                createUserErrorCode: initialState.createUserErrorCode,

                updateUser: initialState.updateUser,
                updateUserSuccess: initialState.updateUserSuccess,
                updateUserSuccessUserName: initialState.updateUserSuccessUserName,
                updateUserError: initialState.updateUserError,
                updateUserErrorCode: initialState.updateUserErrorCode,
            });
        case GET_SUBUSERS_REQUEST:
            return Object.assign({}, state, {
                isRequesting: true,
            });
        case GET_SUBUSERS_SUCCESS:
            return Object.assign({}, state, {
                isRequesting: false,
                subusers: action.payload
            });
        case GET_SUBUSERS_FAILURE:
            return Object.assign({}, state, {
                isRequesting: false,
            });
        case GET_SCHEMAS_REQUEST:
            return Object.assign({}, state, {
                isRequesting: true,
            });
        case GET_SCHEMAS_SUCCESS:
            return Object.assign({}, state, {
                isRequesting: false,
                schemas: action.payload
            });
        case GET_SCHEMAS_FAILURE:
            return Object.assign({}, state, {
                isRequesting: false,
            });
        case GET_CONFIGURATIONS_REQUEST:
            return Object.assign({}, state, {
                isRequesting: true,
            });
        case GET_CONFIGURATIONS_SUCCESS:
            return Object.assign({}, state, {
                isRequesting: false,
                configurations: action.payload
            });
        case GET_CONFIGURATIONS_FAILURE:
            return Object.assign({}, state, {
                isRequesting: false,
            });

        case CREATE_UPDATE_CONFIGURATION_RESET:
            return Object.assign({}, state, {
                isRequesting: false,
                createConfiguration: false,
                createConfigurationSuccess: false,
                createConfigurationError: false,
                updateConfiguration: false,
                updateConfigurationSuccess: false,
                updateConfigurationError: false,
            });

        case CREATE_CONFIGURATION_REQUEST:
            return Object.assign({}, state, {
                isRequesting: true,
                createConfiguration: true,
                createConfigurationSuccess: false,
                createConfigurationError: false,
            });
        case CREATE_CONFIGURATION_SUCCESS:
            return Object.assign({}, state, {
                isRequesting: false,
                createConfiguration: false,
                createConfigurationSuccess: true,
                createConfigurationError: false,
            });
        case CREATE_CONFIGURATION_FAILURE:
            return Object.assign({}, state, {
                isRequesting: false,
                createConfiguration: false,
                createConfigurationSuccess: false,
                createConfigurationError: true,
            });

        case UPDATE_CONFIGURATION_REQUEST:
            return Object.assign({}, state, {
                isRequesting: true,
                updateConfiguration: true,
                updateConfigurationSuccess: false,
                updateConfigurationError: false,
            });
        case UPDATE_CONFIGURATION_SUCCESS:
            return Object.assign({}, state, {
                isRequesting: false,
                updateConfiguration: false,
                updateConfigurationSuccess: true,
                updateConfigurationError: false,
            });
        case UPDATE_CONFIGURATION_FAILURE:
            return Object.assign({}, state, {
                isRequesting: false,
                updateConfiguration: false,
                updateConfigurationSuccess: false,
                updateConfigurationError: true,
            });

        default:
            return state;
    }
}

export default appReducer;
