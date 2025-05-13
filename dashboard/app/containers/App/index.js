import React from 'react';
import { Switch } from 'react-router-dom';
import injectSaga from 'utils/injectSaga';
import { compose } from 'redux';
import { connect } from 'react-redux';
import { createStructuredSelector } from 'reselect';
import { getGC2ConfigurationRequest } from 'containers/App/actions';
import { withRouter, Route } from "react-router";

import Footer from 'components/Footer/Loadable';
import SigninPage from 'containers/SigninPage/Loadable';
import SignupPage from 'containers/SignupPage/Loadable';
import DashboardPage from 'containers/DashboardPage/Loadable';
import PublishedConfigurationsPage from 'containers/PublishedConfigurationsPage/Loadable';
import AccountPage from 'containers/AccountPage/Loadable';
import SubuserPage from 'containers/SubuserPage/Loadable';
import NotFoundPage from 'containers/NotFoundPage/Loadable';
import ConfigurationPage from 'containers/ConfigurationPage/Loadable';
import AppLoadingOverlay from 'components/AppLoadingOverlay';

import PublicLayoutRoute from 'containers/PublicLayoutRoute';
import ProtectedLayoutRoute from 'containers/ProtectedLayoutRoute';

import { makeSelectGC2Configuration, makeSelectGC2ConfigurationLoading } from 'containers/App/selectors';
import saga from 'containers/App/saga';

class App extends React.Component {
    constructor(props) {
        super(props);
    }

    componentWillMount() {
        this.props.getGC2Configuration();
    }

    render() {
        let appBaseURL = (process.env.WEBPACK_PUBLIC_PATH ? process.env.WEBPACK_PUBLIC_PATH : `/`);
        return (<div>
            {this.props.gc2ConfigurationsLoading && this.props.gc2Configurations === false ? (<AppLoadingOverlay messageId="loadingConfiguration"/>) : (
            <div>
                <Switch>
                    <ProtectedLayoutRoute exact path={appBaseURL} component={DashboardPage} />
                    <Route exact path={appBaseURL + "configuration/published/:id"} component={PublishedConfigurationsPage} />
                    <ProtectedLayoutRoute exact path={appBaseURL + "account"} component={AccountPage} />
                    <ProtectedLayoutRoute exact path={appBaseURL + "configuration/add"} component={ConfigurationPage} />
                    <ProtectedLayoutRoute exact path={appBaseURL + "configuration/edit/:id"} component={ConfigurationPage} />
                    <ProtectedLayoutRoute exact path={appBaseURL + "subuser/add"} component={SubuserPage} />
                    <ProtectedLayoutRoute exact path={appBaseURL + "subuser/edit/:id"} component={SubuserPage} />
                    <PublicLayoutRoute exact path={appBaseURL + "sign-in"} component={SigninPage} />
                    <PublicLayoutRoute exact path={appBaseURL + "sign-up"} component={SignupPage} />
                    <PublicLayoutRoute path="" component={NotFoundPage} />
                </Switch>
                <Footer />
            </div>
            )}
        </div>);
    }
}

export function mapDispatchToProps(dispatch) {
    return {
        getGC2Configuration: () => dispatch(getGC2ConfigurationRequest())
    };
}

const mapStateToProps = createStructuredSelector({
    gc2Configurations: makeSelectGC2Configuration(),
    gc2ConfigurationsLoading: makeSelectGC2ConfigurationLoading(),
});

const withConnect = connect(mapStateToProps, mapDispatchToProps);

const withSaga = injectSaga({ key: 'app', saga });

export default withRouter(compose(withSaga, withConnect)(App));
