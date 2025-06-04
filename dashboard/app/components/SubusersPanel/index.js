import React from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import { compose } from 'redux';
import { createStructuredSelector } from 'reselect';
import { FormattedMessage } from 'react-intl';
import {injectIntl} from 'react-intl';

import Card from '@material-ui/core/Card';
import CardHeader from '@material-ui/core/CardHeader';
import Avatar from '@material-ui/core/Avatar';
import IconButton from '@material-ui/core/IconButton';
import Typography from '@material-ui/core/Typography';
import Grid from '@material-ui/core/Grid';
import Button from '@material-ui/core/Button';
import TextField from '@material-ui/core/TextField';
import indigo from '@material-ui/core/colors/indigo';
import DeleteIcon from '@material-ui/icons/Delete';
import EditIcon from '@material-ui/icons/Edit';
import SearchIcon from '@material-ui/icons/Search';

import { makeSelectUser, makeSelectSubusers } from 'containers/App/selectors';
import { getSubusersRequest, deleteUserRequest } from 'containers/App/actions';

import StyledButtonLink from 'components/StyledButtonLink';

export class SubusersPanel extends React.PureComponent {
    constructor(props) {
        super(props);

        this.state = {
            subusersFilter: ``
        };

        this.deleteHandler = this.deleteHandler.bind(this);
    }

    componentWillMount() {
        this.props.dispatch(getSubusersRequest({screenName: this.props.user.screenName}));
    }

    deleteHandler(screenName) {
        if (confirm(this.props.intl.formatMessage({id: `Delete`}) + '?')) {
            this.props.dispatch(deleteUserRequest({screenName}));
        }
    }

    render() {
        let appBaseURL = (process.env.WEBPACK_PUBLIC_PATH ? process.env.WEBPACK_PUBLIC_PATH : `/`);

        let subuserFilter = false;
        let subuserComponents = [];
        if (this.props.subusers) {
            subuserFilter = (<Grid container spacing={8} alignItems="flex-end">
                <Grid item>
                    <SearchIcon />
                </Grid>
                <Grid item>
                    <TextField
                        fullWidth
                        value={this.state.subusersFilter}
                        onChange={(event) => { this.setState({ subusersFilter: event.target.value }) }} />
                </Grid>
            </Grid>);

            this.props.subusers.map((item, index) => {
                if (this.state.subusersFilter === `` || (item.screenName.indexOf(this.state.subusersFilter) > -1 || item.email.indexOf(this.state.subusersFilter) > -1)) {
                    subuserComponents.push(<Card key={`subuser_card_${index}`} style={{marginBottom: `10px`}}>
                        <CardHeader
                            avatar={<Avatar aria-label="Recipe" style={{backgroundColor: indigo[500]}}>{item.screenName[0].toUpperCase()}</Avatar>}
                            action={<div>
                                <StyledButtonLink to={appBaseURL + `subuser/edit/${item.screenName}`}>
                                    <IconButton color="primary">
                                        <EditIcon />
                                    </IconButton>
                                </StyledButtonLink>
                                <IconButton color="secondary" onClick={() => {this.deleteHandler(item.screenName)}}>
                                    <DeleteIcon />
                                </IconButton>
                            </div>}
                            title={item.screenName}
                            subheader={item.email}
                            style={{marginBottom: `10px`, paddingBottom: `0px`}}/>
                    </Card>);
                }
            });
        }

        if (subuserComponents.length === 0) {
            if (this.state.subusersFilter === ``) {
                subuserComponents = (<p>
                    <FormattedMessage id="No subusers yet"/>
                </p>);
            } else {
                subuserComponents = (<p>
                    <FormattedMessage id="No subusers found"/>
                </p>);
            }
        }

        return (<div>
            <div>
                <Grid container spacing={8} alignItems="flex-end">
                    <Grid item>
                        <Typography variant="h6" color="inherit">
                            <FormattedMessage id="Subusers"/>
                            <StyledButtonLink to={appBaseURL + "subuser/add"}>
                                <Button
                                    variant="contained"
                                    size="small"
                                    color="primary"
                                    style={{marginLeft: `10px`}}>
                                    <FormattedMessage id="Add" />
                                </Button>
                            </StyledButtonLink>
                        </Typography>
                    </Grid>
                    <Grid item>{subuserFilter}</Grid>
                </Grid>
            </div>
            <div style={{paddingTop: `10px`}}>{subuserComponents}</div>
        </div>);
    }
}

const mapStateToProps = createStructuredSelector({
    user: makeSelectUser(),
    subusers: makeSelectSubusers()
});

const withConnect = connect(mapStateToProps);

export default compose(withConnect)(injectIntl(SubusersPanel));
