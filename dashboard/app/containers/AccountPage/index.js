import React from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import { compose } from 'redux';
import { createStructuredSelector } from 'reselect';
import { FormattedMessage } from 'react-intl';
import {injectIntl} from 'react-intl';

import Badge from '@material-ui/core/Badge';
import Table from '@material-ui/core/Table';
import TableBody from '@material-ui/core/TableBody';
import TableRow from '@material-ui/core/TableRow';
import TableCell from '@material-ui/core/TableCell';
import Typography from '@material-ui/core/Typography';
import Snackbar from '@material-ui/core/Snackbar';
import Grid from '@material-ui/core/Grid';
import Button from '@material-ui/core/Button';
import TextField from '@material-ui/core/TextField';

import { makeSelectUser, makeSelectUpdateUserErrorCode } from 'containers/App/selectors';
import SnackbarContent from 'components/SnackbarContent';
import { updateUserRequest } from 'containers/App/actions';
import { passwordIsStrongEnough } from 'utils/shared';

/* eslint-disable react/prefer-stateless-function */
export class AccountPage extends React.PureComponent {
    constructor(props) {
        super(props);

        this.state = {
            passwordExpired: false,
            passwordWasUpdated: false,
            currentPassword: ``,
            newPassword1: ``,
            newPassword2: ``,
        }
    }

    componentWillMount() {
        if (this.props.user.passwordExpired) {
            this.setState({passwordExpired: true});
        }
    }

    onUpdatePasswordHandler() {
        this.setState({passwordWasUpdated: false}, () => {
            this.props.dispatch(updateUserRequest(this.props.user.screenName, {
                oldPassword: this.state.currentPassword, 
                newPassword: this.state.newPassword1,
                onSuccess: () => {
                    this.setState({
                        passwordWasUpdated: true,
                        currentPassword: ``,
                        newPassword1: ``,
                        newPassword2: ``,
                    });
                }
            }));
        });
    }

    render() {
        let passwordCheckMessageKeys = [];
        if (this.state.newPassword1) {
            if (this.state.newPassword1 !== this.state.newPassword2) {
                passwordCheckMessageKeys.push(`Passwords do not match`);
            }

            if (!passwordIsStrongEnough(this.state.newPassword1)) {
                passwordCheckMessageKeys.push(`errors.WEAK_PASSWORD`);
            }
        }

        let passwordUpdateNotification = false;
        if (this.state.passwordWasUpdated) {
            passwordUpdateNotification = (<div style={{color: `green`, paddingTop: `10px`, textAlign: `center`}}>
                <FormattedMessage id="Password was updated" />
            </div>);
        } else if (this.props.updateUserErrorCode && this.props.updateUserErrorCode.length > 0) {
            passwordUpdateNotification = (<div style={{color: `red`, paddingTop: `10px`, textAlign: `center`}}>
                <FormattedMessage id={`errors.${this.props.updateUserErrorCode}`} />
            </div>);
        }

        return (<div>
            <Grid container spacing={24}>
                <Grid item md={6}>
                    <Typography variant="h4" color="inherit">
                        <FormattedMessage id="General information"/>
                    </Typography>
                    <Table>
                        <TableBody>
                            <TableRow>
                                <TableCell><FormattedMessage id="Name"/></TableCell>
                                <TableCell>{this.props.user.screenName}</TableCell>
                            </TableRow>
                            <TableRow>
                                <TableCell><FormattedMessage id="Email address"/></TableCell>
                                <TableCell>{this.props.user.email}</TableCell>
                            </TableRow>
                            {this.props.user.subuser ? (<TableRow>
                                <TableCell><FormattedMessage id="Parent database"/></TableCell>
                                <TableCell>
                                    <span>{this.props.user.parentDb}</span>
                                </TableCell>
                            </TableRow>) : false}
                        </TableBody>
                    </Table>
                </Grid>
                <Grid item md={6}>
                    <Badge badgeContent={this.props.user.passwordExpired ? `!` : false} color="secondary">
                        <Typography variant="h4" color="inherit">
                            <FormattedMessage id="Update password" />
                        </Typography>
                    </Badge>
                    <form noValidate>
                        <div>
                            <TextField required type="password" label={this.props.intl.formatMessage({id: `Current password`})} fullWidth
                                value={this.state.currentPassword} onChange={(event) => { this.setState({currentPassword: event.target.value})}}/>
                        </div>
                        <div>
                            <TextField required type="password" label={this.props.intl.formatMessage({id: `New password`})} fullWidth
                                value={this.state.newPassword1} onChange={(event) => { this.setState({newPassword1: event.target.value})}}
                                helperText={this.props.intl.formatMessage({id: "Minimum 8 characters long, at least one capital letter and one digit"})}/>
                        </div>
                        <div>
                            <TextField required type="password" label={this.props.intl.formatMessage({id: `Retype new password`})} fullWidth
                                value={this.state.newPassword2} onChange={(event) => { this.setState({newPassword2: event.target.value})}}/>
                        </div>
                        <div style={{color: `red`}}>
                            {passwordCheckMessageKeys.map((item, index) => (<Typography key={`message_${index}`} variant="body1" color="inherit">
                                <FormattedMessage id={item}/>
                            </Typography>))}
                        </div>
                        <div style={{paddingTop: `10px`}}>
                            <Button
                                disabled={!this.state.currentPassword || !this.state.newPassword1 || passwordCheckMessageKeys.length > 0}
                                variant="contained"
                                fullWidth={true}
                                color="primary"
                                onClick={this.onUpdatePasswordHandler.bind(this)}>
                                <FormattedMessage id="Update" />
                            </Button>
                        </div>
                        {passwordUpdateNotification}
                    </form>
                </Grid>
            </Grid>
            <Snackbar anchorOrigin={{ vertical: 'bottom', horizontal: 'right' }} open={this.state.passwordExpired} autoHideDuration={20000} onClose={() => { this.setState({passwordExpired: false})}}>
                <SnackbarContent
                    onClose={() => { this.setState({passwordExpired: false})}}
                    variant="error"
                    message={this.props.intl.formatMessage({id: `Please update your password, the old one has expired`})}/>
            </Snackbar>
        </div>);
    }
}

const mapStateToProps = createStructuredSelector({
    user: makeSelectUser(),
    updateUserErrorCode: makeSelectUpdateUserErrorCode()
});

const withConnect = connect(mapStateToProps);

export default compose(withConnect)(injectIntl(AccountPage));
