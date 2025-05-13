import React from 'react';
import {FormattedMessage} from 'react-intl';
import {injectIntl} from 'react-intl';
import {connect} from 'react-redux';
import {compose} from 'redux';
import {CopyToClipboard} from 'react-copy-to-clipboard';
import {createStructuredSelector} from 'reselect';

import Button from '@material-ui/core/Button';
import TextField from '@material-ui/core/TextField';
import ExpansionPanel from '@material-ui/core/ExpansionPanel';
import ExpansionPanelSummary from '@material-ui/core/ExpansionPanelSummary';
import ExpansionPanelDetails from '@material-ui/core/ExpansionPanelDetails';
import Tooltip from '@material-ui/core/Tooltip';
import Typography from '@material-ui/core/Typography';
import LockIcon from '@material-ui/icons/Lock';
import LaunchIcon from '@material-ui/icons/Launch';
import ExpandMoreIcon from '@material-ui/icons/ExpandMore';
import LinkIcon from '@material-ui/icons/Link';

import StyledButtonLink from 'components/StyledButtonLink';
import StyledExternalLink from 'components/StyledExternalLink';

import config from 'config';
import {makeSelectUser, makeSelectGC2Configuration} from 'containers/App/selectors';

class PublishedConfigurationsPage extends React.Component {
    constructor(props) {
        super(props);
    }

    render() {
        let appBaseURL = (process.env.WEBPACK_PUBLIC_PATH ? process.env.WEBPACK_PUBLIC_PATH : `/`);

        const port = ['', '80', '443'].includes(location.port) ? '' : `:${location.port}`;
        const uri = config.apiUrl + `configuration/${this.props.ownerScreenName}/${this.props.data.key}.json`;
        const url = `${location.protocol}//${location.hostname}${port}${uri}`;
        const parsedData = JSON.parse(this.props.data.value);

        let databaseName = ``;
        if (this.props.user.parentDb) {
            databaseName = this.props.user.parentDb;
        } else {
            databaseName = this.props.user.screenName;
        }

        return (<ExpansionPanel defaultExpanded={!!this.props.expanded}>
            <ExpansionPanelSummary expandIcon={<ExpandMoreIcon/>}>
                <Typography><LinkIcon/> {parsedData.name} {parsedData.published ? false : (<Tooltip placement="top"
                                                                                                    title={this.props.intl.formatMessage({id: `Configuration is not published`})}>
                    <LockIcon/>
                </Tooltip>)}</Typography>
            </ExpansionPanelSummary>
            <ExpansionPanelDetails>
                <div style={{width: `100%`}}>
                    <div>
                        <TextField
                            id="configuration-link"
                            label="Link"
                            fullWidth={true}
                            value={url}
                            margin="normal"
                            style={{marginTop: `0px`}}/>
                    </div>
                    <div style={{textAlign: `right`}}>
                        {this.props.gc2Configuration ? (<StyledExternalLink
                            href={`${this.props.gc2Configuration.gc2Options.vidiUrl}/app/${databaseName}/?config=${uri}`}
                            target="_blank" style={{marginRight: `10px`}}>
                            <Button color="primary" variant="contained" size="small">
                                <LaunchIcon/> Vidi
                            </Button>
                        </StyledExternalLink>) : false}

                        <CopyToClipboard text={url}>
                            <Button variant="contained" size="small" style={{marginRight: `10px`}}>
                                <FormattedMessage id="Copy link"/>
                            </Button>
                        </CopyToClipboard>

                        {this.props.readOnly === false ? (
                            <StyledButtonLink to={appBaseURL + `configuration/edit/${this.props.data.key}`}
                                              style={{marginRight: `10px`}}>
                                <Button color="primary" variant="contained" size="small">
                                    <FormattedMessage id="Edit"/>
                                </Button>
                            </StyledButtonLink>) : false}

                        {this.props.onDelete ? (
                            <Button color="secondary" variant="contained" size="small" onClick={() => {
                                this.props.onDelete(this.props.data.key, parsedData.name)
                            }}>
                                <FormattedMessage id="Delete"/>
                            </Button>) : false}
                    </div>
                </div>
            </ExpansionPanelDetails>
        </ExpansionPanel>);
    }
}

PublishedConfigurationsPage.defaultProps = {
    expanded: false,
    readOnly: false
};

const mapStateToProps = createStructuredSelector({
    gc2Configuration: makeSelectGC2Configuration(),
    user: makeSelectUser(),
});

const withConnect = connect(mapStateToProps);

export default compose(withConnect)(injectIntl(PublishedConfigurationsPage));
