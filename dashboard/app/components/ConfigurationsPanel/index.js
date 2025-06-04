import React from 'react';
import { connect } from 'react-redux';
import { compose } from 'redux';
import { createStructuredSelector } from 'reselect';
import { FormattedMessage } from 'react-intl';
import {injectIntl} from 'react-intl';

import Grid from '@material-ui/core/Grid';
import Button from '@material-ui/core/Button';
import TextField from '@material-ui/core/TextField';
import SearchIcon from '@material-ui/icons/Search';

import ConfigurationListItem from 'components/ConfigurationListItem';
import { makeSelectUser, makeSelectConfigurations } from 'containers/App/selectors';
import { getConfigurationsRequest, deleteConfigurationRequest } from 'containers/App/actions';

import StyledButtonLink from 'components/StyledButtonLink';

export class ConfigurationsPanel extends React.PureComponent {
    constructor(props) {
        super(props);

        this.state = {
            configurationsFilter: ``
        };

        this.handleDelete = this.handleDelete.bind(this);
    }

    componentWillMount() {
        this.props.dispatch(getConfigurationsRequest({ userId: this.props.user.screenName}));
    }

    handleDelete(key, name) {
        if (confirm(this.props.intl.formatMessage({id: `Delete`}) + ` ${name}?`)) {
            this.props.dispatch(deleteConfigurationRequest({
                userId: this.props.user.screenName,
                configurationId: key
            }));
        }
    }

    render() {
        let appBaseURL = (process.env.WEBPACK_PUBLIC_PATH ? process.env.WEBPACK_PUBLIC_PATH : `/`);

        let configurationsFilter = false;
        let configurationsComponents = [];
        if (this.props.configurations) {
            configurationsFilter = (<Grid container spacing={8} alignItems="flex-end">
                <Grid item>
                    <SearchIcon />
                </Grid>
                <Grid item>
                    <TextField
                        fullWidth
                        placeholder={this.props.intl.formatMessage({id: `Filter`})}
                        value={this.state.configurationsFilter}
                        onChange={(event) => { this.setState({ configurationsFilter: event.target.value }) }} />
                </Grid>
            </Grid>);

            this.props.configurations.map((item, index) => {
                let parsedData = JSON.parse(item.value);
                if (this.state.configurationsFilter === `` || (parsedData.name.indexOf(this.state.configurationsFilter) > -1 || parsedData.name.indexOf(this.state.configurationsFilter) > -1)) {
                    configurationsComponents.push(<ConfigurationListItem
                        key={`configuration_card_${index}`}
                        ownerScreenName={this.props.user.screenName}
                        onDelete={this.handleDelete.bind(this)}
                        data={item}/>);
                }
            });
        }

        if (configurationsComponents.length === 0) {
            if (this.state.configurationsFilter === ``) {
                configurationsComponents = (<p>
                    <FormattedMessage id="No configurations yet"/>
                </p>);
            } else {
                configurationsComponents = (<p>
                    <FormattedMessage id="No configurations found"/>
                </p>);
            }
        }

        return (<div>
            <div>
                <Grid container spacing={8} direction="row" justify="space-between" alignItems="flex-start">
                    <Grid item>
                        <StyledButtonLink to={appBaseURL + "configuration/add"}>
                            <Button
                                variant="contained"
                                size="small"
                                color="primary"
                                style={{marginLeft: `10px`}}>
                                <FormattedMessage id="Add" />
                            </Button>
                        </StyledButtonLink>
                    </Grid>
                    <Grid item>{configurationsFilter}</Grid>
                </Grid>
            </div>
            <div style={{paddingTop: `10px`}}>{configurationsComponents}</div>
        </div>);
    }
}

const mapStateToProps = createStructuredSelector({
    user: makeSelectUser(),
    configurations: makeSelectConfigurations()
});

const withConnect = connect(mapStateToProps);

export default compose(withConnect)(injectIntl(ConfigurationsPanel));
