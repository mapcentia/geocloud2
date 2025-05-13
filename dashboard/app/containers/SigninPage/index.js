import React from 'react';
import { FormattedMessage } from 'react-intl';
import { connect } from 'react-redux';
import styled from 'styled-components';
import { createStructuredSelector } from 'reselect';

import Typography from '@material-ui/core/Typography';
import Button from '@material-ui/core/Button';
import Divider from '@material-ui/core/Divider';

import { makeSelectSigningIn, makeSelectSigningInError } from 'containers/App/selectors';
import { signInRequest, getDatabasesRequest, getDatabasesReset } from 'containers/App/actions';
import SigninForm from './SigninForm';
import PublicFormsWrapper from 'components/PublicFormsWrapper';
import StyledLink from 'components/StyledLink';
import { makeDatabaseError, makeSelectGC2Configuration } from '../App/selectors';

const ErrorWrapper = styled.div`
    padding-top: 10px;
`;

class Signin extends React.Component {
    constructor(props) {
        super(props);
    }

    render() {
        const prefix = (process.env.WEBPACK_PUBLIC_PATH ? process.env.WEBPACK_PUBLIC_PATH : `/`);
        const logo = this.props.gc2Configuration?.gc2Options?.loginLogo ? this.props.gc2Configuration.gc2Options.loginLogo : prefix + "assets/img/MapCentia_500.png";
        return (
            <PublicFormsWrapper logo={logo}>
                <SigninForm
                    disabled={!!this.props.signingIn}
                    onGetDatabases={(userName) => { this.props.dispatch(getDatabasesRequest(userName))}}
                    onReset={() => { this.props.dispatch(getDatabasesReset())}}
                    onSubmit={(data) => { this.props.dispatch(signInRequest(data)); }}/>
                {this.props.signingInError ? (
                    <ErrorWrapper>
                        <Typography variant="body1" gutterBottom color="error">
                            <FormattedMessage id="Invalid username or password" />
                        </Typography>
                    </ErrorWrapper>
                ) : false}
              {this.props.databaseError ? (
                <ErrorWrapper>
                  <Typography variant="body1" gutterBottom color="error">
                    <FormattedMessage id="Database error" />
                  </Typography>
                </ErrorWrapper>
              ) : false}

                {!this.props.gc2Configuration?.gc2Options?.disableDatabaseCreation ? (
                  <div>
                      <Divider style={{ marginTop: `20px`, marginBottom: `20px` }} />
                      <StyledLink to={prefix + "sign-up"}>
                        <Button type="submit" fullWidth variant="contained" color="secondary">
                            <FormattedMessage id="Register" />
                        </Button>
                    </StyledLink>
                  </div>) : ""
                }
            </PublicFormsWrapper>
        );
    }
}

const mapStateToProps = createStructuredSelector({
    signingIn: makeSelectSigningIn(),
    signingInError: makeSelectSigningInError(),
    databaseError: makeDatabaseError(),
    gc2Configuration: makeSelectGC2Configuration(),
});

export default connect(mapStateToProps)(Signin);
