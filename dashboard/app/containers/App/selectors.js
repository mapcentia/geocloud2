import { createSelector } from 'reselect';

const selectGlobal = state => state.get('global');

const makeSelectGC2Configuration = () => createSelector(selectGlobal, globalState => globalState.gc2Configuration);
const makeSelectGC2ConfigurationLoading = () => createSelector(selectGlobal, globalState => globalState.gc2ConfigurationLoading);

const makeSelectIsAuthenticating = () => createSelector(selectGlobal, globalState => globalState.isAuthenticating);
const makeSelectIsAuthenticated = () => createSelector(selectGlobal, globalState => globalState.isAuthenticated);
const makeSelectUser = () => createSelector(selectGlobal, globalState => globalState.user);

const makeSelectSigningIn = () => createSelector(selectGlobal, globalState => globalState.signingIn);
const makeSelectSigningInError = () => createSelector(selectGlobal, globalState => globalState.signingInError);

const makeSelectAvailableDatabasesList = () => createSelector(selectGlobal, globalState => globalState.availableDatabasesList);
const makeSelectAvailableDatabasesUserName = () => createSelector(selectGlobal, globalState => globalState.availableDatabasesUserName);
const makeDatabaseError = () => createSelector(selectGlobal, globalState => globalState.databaseError);

const makeSelectCreateUser = () => createSelector(selectGlobal, globalState => globalState.createUser);
const makeSelectCreateUserSuccess = () => createSelector(selectGlobal, globalState => globalState.createUserSuccess);
const makeSelectCreateUserSuccessUserName = () => createSelector(selectGlobal, globalState => globalState.createUserSuccessUserName);
const makeSelectCreateUserError = () => createSelector(selectGlobal, globalState => globalState.createUserError);
const makeSelectCreateUserErrorCode = () => createSelector(selectGlobal, globalState => globalState.createUserErrorCode);

const makeSelectUpdateUserSuccess = () => createSelector(selectGlobal, globalState => globalState.updateUserSuccess);
const makeSelectUpdateUserSuccessUserName = () => createSelector(selectGlobal, globalState => globalState.updateUserSuccessUserName);
const makeSelectUpdateUserError = () => createSelector(selectGlobal, globalState => globalState.updateUserError);
const makeSelectUpdateUserErrorCode = () => createSelector(selectGlobal, globalState => globalState.updateUserErrorCode);

const makeSelectIsRequesting = () => createSelector(selectGlobal, globalState => globalState.isRequesting);
const makeSelectSubusers = () => createSelector(selectGlobal, globalState => globalState.subusers);
const makeSelectSchemas = () => createSelector(selectGlobal, globalState => globalState.schemas);
const makeSelectConfigurations = () => createSelector(selectGlobal, globalState => globalState.configurations);

const makeSelectCreateConfigurationLoading = () => createSelector(selectGlobal, globalState => globalState.createConfiguration);
const makeSelectCreateConfigurationSuccess = () => createSelector(selectGlobal, globalState => globalState.createConfigurationSuccess);
const makeSelectCreateConfigurationError = () => createSelector(selectGlobal, globalState => globalState.createConfigurationError);

const makeSelectUpdateConfigurationLoading = () => createSelector(selectGlobal, globalState => globalState.updateConfiguration);
const makeSelectUpdateConfigurationSuccess = () => createSelector(selectGlobal, globalState => globalState.updateConfigurationSuccess);
const makeSelectUpdateConfigurationError = () => createSelector(selectGlobal, globalState => globalState.updateConfigurationError);

export {
  selectGlobal,

  makeSelectGC2Configuration,
  makeSelectGC2ConfigurationLoading,

  makeSelectIsAuthenticating,
  makeSelectIsAuthenticated,
  makeSelectUser,
  makeSelectSigningIn,
  makeSelectSigningInError,
  makeDatabaseError,
  makeSelectCreateUser,

  makeSelectAvailableDatabasesList,
  makeSelectAvailableDatabasesUserName,

  makeSelectCreateUserSuccess,
  makeSelectCreateUserSuccessUserName,
  makeSelectCreateUserError,
  makeSelectCreateUserErrorCode,

  makeSelectUpdateUserSuccess,
  makeSelectUpdateUserSuccessUserName,
  makeSelectUpdateUserError,
  makeSelectUpdateUserErrorCode,

  makeSelectIsRequesting,
  makeSelectSubusers,
  makeSelectSchemas,
  makeSelectConfigurations,

  makeSelectCreateConfigurationLoading,
  makeSelectCreateConfigurationSuccess,
  makeSelectCreateConfigurationError,

  makeSelectUpdateConfigurationLoading,
  makeSelectUpdateConfigurationSuccess,
  makeSelectUpdateConfigurationError,
};
