import React from 'react';
import { FormattedMessage } from 'react-intl';
import { connect } from 'react-redux';
import styled from 'styled-components';
import { createStructuredSelector } from 'reselect';

import Typography from '@material-ui/core/Typography';
import Button from '@material-ui/core/Button';
import Divider from '@material-ui/core/Divider';

import { makeSelectCreateUser, makeSelectCreateUserSuccess, makeSelectCreateUserSuccessUserName, makeSelectCreateUserError, makeSelectCreateUserErrorCode } from 'containers/App/selectors';
import { createUserReset, createUserRequest } from 'containers/App/actions';
import SignupForm from './SignupForm';
import PublicFormsWrapper from 'components/PublicFormsWrapper';
import StyledLink from 'components/StyledLink';
import { makeSelectGC2Configuration } from '../App/selectors';

const ErrorWrapper = styled.div`
    padding-top: 10px;
`;

const SuccessWrapper = styled.div`
    padding-top: 20px;
    padding-bottom: 20px;
`;

class SignupPage extends React.Component {
    constructor(props) {
        super(props);
    }

    componentWillMount() {
        this.props.dispatch(createUserReset());
    }

    render() {
        const appBaseURL = (process.env.WEBPACK_PUBLIC_PATH ? process.env.WEBPACK_PUBLIC_PATH : `/`);
        const logo = this.props?.gc2Configuration?.gc2Options?.loginLogo ? this.props.gc2Configuration.gc2Options.loginLogo : appBaseURL + "assets/img/MapCentia_500.png";
        return (
            <PublicFormsWrapper logo={logo}>
                {this.props.success && this.props.username ? (
                    <SuccessWrapper>
                        <Typography variant="h6" gutterBottom>
                            <FormattedMessage id="User was created, you can now Sign in" />
                        </Typography>
                        <Typography variant="h6" gutterBottom>
                            <FormattedMessage id="Username" />: <strong>{this.props.username}</strong>
                        </Typography>
                    </SuccessWrapper>
                ) : (
                  !this.props.gc2Configuration?.gc2Options?.disableDatabaseCreation ? (
                    <SignupForm disabled={this.props.loading ? true : false} onSubmit={data => {this.props.dispatch(createUserRequest(data))}}/>
                      ) : <div><FormattedMessage id="Database creation is disabled" /></div>

                )}

                {this.props.error ? (
                    <ErrorWrapper>
                        <Typography variant="body1" gutterBottom color="error">
                            <FormattedMessage id={`errors.${this.props.errorCode}`} />
                        </Typography>
                    </ErrorWrapper>
                ) : false}
                {this.props.success ? false : (<Divider style={{ marginTop: `20px`, marginBottom: `20px` }}/>)}
                <StyledLink to={appBaseURL + "sign-in"}>
                    <Button type="button" fullWidth variant="contained" color="secondary">
                        <FormattedMessage id="Sign in" />
                    </Button>
                </StyledLink>
            </PublicFormsWrapper>
        );
    }
}

const mapStateToProps = createStructuredSelector({
    loading: makeSelectCreateUser(),
    success: makeSelectCreateUserSuccess(),
    username: makeSelectCreateUserSuccessUserName(),
    error: makeSelectCreateUserError(),
    errorCode: makeSelectCreateUserErrorCode(),
    gc2Configuration: makeSelectGC2Configuration(),
});

export default connect(mapStateToProps)(SignupPage);
