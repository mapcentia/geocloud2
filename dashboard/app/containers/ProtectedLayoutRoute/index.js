import React, {Component} from 'react';
import {connect} from 'react-redux';
import {createStructuredSelector} from 'reselect';
import {Redirect} from 'react-router';
import {Route} from 'react-router-dom';
import {withRouter} from "react-router";
import {FormattedMessage} from 'react-intl';

import Card from '@material-ui/core/Card';
import CardContent from '@material-ui/core/CardContent';
import AppBar from '@material-ui/core/AppBar';
import Badge from '@material-ui/core/Badge';
import Button from '@material-ui/core/Button';
import Grid from '@material-ui/core/Grid';
import Toolbar from '@material-ui/core/Toolbar';
import Typography from '@material-ui/core/Typography';
import AccountCircleIcon from '@material-ui/icons/AccountCircle';
import HelpIcon from '@material-ui/icons/Help';

import {signOut} from 'containers/App/actions';

import StyledButtonLink from 'components/StyledButtonLink';
import StyledExternalLink from 'components/StyledExternalLink';
import LoadingOverlay from 'components/LoadingOverlay';
import AppLoadingOverlay from 'components/AppLoadingOverlay';
import {makeSelectIsAuthenticating, makeSelectIsAuthenticated, makeSelectUser} from 'containers/App/selectors';
import HelpModal from 'components/HelpModal';

import MainContentWrapper from 'components/MainContentWrapper';

class ProtectedLayout extends React.Component {
    constructor(props) {
        super(props);
    }

    render() {
        let prefix = (process.env.WEBPACK_PUBLIC_PATH ? process.env.WEBPACK_PUBLIC_PATH : `/`);
        const {children} = this.props;
        if (this.props.isAuthenticating === false) {
            if (this.props.isAuthenticated) {
                let userButton = (<StyledButtonLink to={prefix + "account"}>
                    <Button color="inherit">
                        <AccountCircleIcon style={{marginRight: `6px`}}/> {this.props.user.screenName}
                    </Button>
                </StyledButtonLink>);

                let helpButton = (
                    <HelpModal/>
                );

                if (this.props.user.passwordExpired) {
                    userButton = (<Badge color="secondary" variant="dot">
                        {userButton}
                    </Badge>);
                }

                return (<div>
                    <LoadingOverlay/>
                    <div>
                        <AppBar position="static">
                            <Toolbar>
                                <Grid container direction="row" justify="space-between" alignItems="flex-start">
                                    <Grid item>
                                        <StyledButtonLink to={prefix}>
                                            <Typography variant="h6" color="inherit">
                                                <FormattedMessage id="Geocloud Dashboard"/>
                                            </Typography>
                                        </StyledButtonLink>
                                    </Grid>
                                    <Grid item>
                                        <div style={{display: `inline-block`, paddingRight: `20px`}}>
                                            {userButton}
                                        </div>
                                        <div style={{display: `inline-block`, paddingRight: `20px`}}>
                                            {helpButton}
                                        </div>
                                        <div style={{display: `inline-block`}}>
                                            <Button color="inherit" onClick={this.props.onSignOut}>
                                                <FormattedMessage id="Sign out"/>
                                            </Button>
                                        </div>
                                    </Grid>
                                </Grid>
                            </Toolbar>
                        </AppBar>
                    </div>
                    <MainContentWrapper>
                        <Card>
                            <CardContent>
                                {children}
                            </CardContent>
                        </Card>
                    </MainContentWrapper>
                </div>);
            } else {
                return (<Redirect to={prefix + "sign-in"}/>);
            }
        } else {
            return (<AppLoadingOverlay/>);
        }
    }
}

class ProtectedLayoutRoute extends React.Component {
    constructor(props) {
        super(props);
    }

    render() {
        const {component: Component, ...rest} = this.props;
        return (<Route {...rest} render={matchProps => (
            <ProtectedLayout {...this.props}>
                <Component {...matchProps} />
            </ProtectedLayout>
        )}/>);
    }
}

const mapStateToProps = createStructuredSelector({
    isAuthenticating: makeSelectIsAuthenticating(),
    isAuthenticated: makeSelectIsAuthenticated(),
    user: makeSelectUser()
});

export function mapDispatchToProps(dispatch) {
    return {
        onSignOut: () => dispatch(signOut()),
    };
}

export default withRouter(connect(mapStateToProps, mapDispatchToProps)(ProtectedLayoutRoute));