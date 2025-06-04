import React, { Component } from 'react';
import { Route } from 'react-router-dom';
import { connect } from 'react-redux';
import { Redirect } from 'react-router';
import { createStructuredSelector } from 'reselect';
import { withRouter } from "react-router";

import Grid from '@material-ui/core/Grid';
import { makeSelectIsAuthenticating, makeSelectIsAuthenticated } from 'containers/App/selectors';

import AppLoadingOverlay from 'components/AppLoadingOverlay';

const REDIRECT_IF_AUTHENTICATED_ROUTES = [`/sign-in`, `/sign-up`];

class PublicLayout extends React.Component {
    constructor(props) {
        super(props);
    }

    render() {
        const { children } = this.props;
        let appBaseURL = (process.env.WEBPACK_PUBLIC_PATH ? process.env.WEBPACK_PUBLIC_PATH : `/`);
        const renderRegular = () => {
            return (
                <Grid container direction="row" justify="center" alignItems="center">
                    <Grid item>
                        <div style={{ paddingTop: `40px`, textAlign: `center` }}>
                            {children}
                        </div>
                    </Grid>
                </Grid>
            );
        };

        if (REDIRECT_IF_AUTHENTICATED_ROUTES.indexOf(this.props.path) === -1) {
            return renderRegular();
        } else {
            if (this.props.isAuthenticating === false) {
                if (this.props.isAuthenticated) {
                    return (<Redirect to={appBaseURL}/>);
                } else {
                    return renderRegular();
                }
            } else {
                return (<AppLoadingOverlay/>);
            }
        }
    }
}

class PublicLayoutRoute extends React.Component {
    constructor(props) {
        super(props);
    }

    render() {
        const { component: Component, ...rest } = this.props;
        return (<Route {...rest} render={matchProps => (
            <PublicLayout {...this.props}>
                <Component {...matchProps} />
            </PublicLayout>
        )} />);
    }
}

const mapStateToProps = createStructuredSelector({
    isAuthenticating: makeSelectIsAuthenticating(),
    isAuthenticated: makeSelectIsAuthenticated(),
});

export default withRouter(connect(mapStateToProps)(PublicLayoutRoute));