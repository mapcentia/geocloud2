import React from 'react';
import { connect } from 'react-redux';
import { compose } from 'redux';
import { createStructuredSelector } from 'reselect';
import { injectIntl } from 'react-intl';
import SwipeableViews from 'react-swipeable-views';

import AppBar from '@material-ui/core/AppBar';
import Tabs from '@material-ui/core/Tabs';
import Tab from '@material-ui/core/Tab';
import Grid from '@material-ui/core/Grid';

import injectReducer from 'utils/injectReducer';
import injectSaga from 'utils/injectSaga';

import SchemasPanel from 'components/SchemasPanel';
import ConfigurationsPanel from 'components/ConfigurationsPanel';
import SubusersPanel from 'components/SubusersPanel';

import { makeSelectUser } from 'containers/App/selectors';
import reducer from './reducer';
import saga from './saga';

export class DashboardPage extends React.PureComponent {

    state = {
        activeTab: 0,
    };

    componentWillMount() {
        if (window.location.hash === `#configuration`) {
            this.setState({
                activeTab: 1
            });
        }
    }

    handleChangeActiveTab = (event, activeTab) => {
        this.setState({ activeTab });
    };

    handleChangeIndex = (index) => {
        this.setState({ activeTab: index });
    };

    render() {
        if (this.props.user.subuser) {
            return (<Grid container spacing={24}>
                <Grid item md={12}>
                    <SchemasPanel/>
                </Grid>
            </Grid>);
        } else {
            return (<Grid container spacing={24}>
                <Grid item md={6}>
                    <AppBar position="static" color="default" style={{backgroundColor: `white`, boxShadow: `none`}}>
                        <Tabs
                            value={this.state.activeTab}
                            onChange={this.handleChangeActiveTab}
                            indicatorColor="primary"
                            textColor="primary">
                            <Tab label={this.props.intl.formatMessage({id: `Schemas`})} />
                            <Tab label={this.props.intl.formatMessage({id: `Configurations`})}/>
                        </Tabs>
                    </AppBar>
                    <SwipeableViews
                        index={this.state.activeTab}
                        onChangeIndex={this.handleChangeIndex}>
                        <div style={{paddingTop: `16px`, paddingLeft: `4px`, paddingRight: `4px`}}>
                            <SchemasPanel/>
                        </div>
                        <div style={{paddingTop: `16px`, paddingLeft: `4px`, paddingRight: `4px`}}>
                            <ConfigurationsPanel/>
                        </div>
                    </SwipeableViews>
                </Grid>
                <Grid item md={6}>
                    <SubusersPanel/>
                </Grid>
            </Grid>);
        }
    }
}

const mapStateToProps = createStructuredSelector({
    user: makeSelectUser()
});

const withConnect = connect(mapStateToProps);

const withReducer = injectReducer({ key: 'home', reducer });
const withSaga = injectSaga({ key: 'home', saga });

export default compose(withReducer, withSaga, withConnect)(injectIntl(DashboardPage));
