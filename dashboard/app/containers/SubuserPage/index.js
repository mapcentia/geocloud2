import React from 'react';
import { connect } from 'react-redux';
import { compose } from 'redux';
import { FormattedMessage } from 'react-intl';
import { createStructuredSelector } from 'reselect';
import {injectIntl} from 'react-intl';
import validator from 'email-validator';
import styled from 'styled-components';

import FormControl from '@material-ui/core/FormControl';
import Select from '@material-ui/core/Select';
import Typography from '@material-ui/core/Typography';
import MenuItem from '@material-ui/core/MenuItem';
import Grid from '@material-ui/core/Grid';
import Button from '@material-ui/core/Button';
import TextField from '@material-ui/core/TextField';

import StyledButtonLink from 'components/StyledButtonLink';
import { makeSelectUser, makeSelectSubusers } from 'containers/App/selectors';
import { getSubusersRequest, createUserRequest, updateUserRequest, createUpdateUserReset } from 'containers/App/actions';

import { makeSelectCreateUserSuccess, makeSelectCreateUserSuccessUserName, makeSelectCreateUserError, makeSelectCreateUserErrorCode,
    makeSelectUpdateUserSuccess, makeSelectUpdateUserSuccessUserName, makeSelectUpdateUserError, makeSelectUpdateUserErrorCode } from 'containers/App/selectors';

import SnackbarContent from 'components/SnackbarContent';
import { passwordIsStrongEnough } from 'utils/shared';

const TextFieldWrapper = styled.div`
    padding-bottom: 10px;
`;

const OverlayContainer = styled.div`
    position: absolute;
    width: 100%;
    height: 100%;
    background-color: rgba(255, 255, 255, 0.7);
    z-index: 1000;
`;

const OverlayInner = styled.div`
    position: absolute;
    top: 10%;
    text-align: center;
    width: 100%;
`;

const NULL_VALUE = `null`;

export class SubuserPage extends React.PureComponent {
    constructor(props) {
        super(props);

        this.state = {
            addingSubuser: true,
            screenName: ``,
            email: ``,
            password1: ``,
            password2: ``,
            createSchema: false,
            usergroup: NULL_VALUE
        }
    }

    componentWillMount() {
        this.props.dispatch(createUpdateUserReset());
        if (!this.props.subusers) {
            this.props.dispatch(getSubusersRequest({screenName: this.props.user.screenName}));
        }

        this.setupUser(this.props);
    }

    componentWillReceiveProps(nextProps) {
        this.setupUser(nextProps);
    }

    setupUser(props) {
        if (this.props.match && this.props.match.params && this.props.match.params.id) {
            if (props.subusers) {
                props.subusers.map(item => {
                    if (item.screenName === this.props.match.params.id) {
                        this.setState({
                            addingSubuser: false,
                            screenName: item.screenName,
                            email: item.email,
                            password1: ``,
                            password2: ``,
                            createSchema: false,
                            usergroup: item.usergroup
                        });
                    }
                });
            }
        }
    }

    handleSave() {
        if (this.state.addingSubuser) {
            let data = {
                name: this.state.screenName,
                email: this.state.email,
                password: this.state.password1,
                
            };

            if (this.state.usergroup && this.state.usergroup !== NULL_VALUE) {
                data.usergroup = this.state.usergroup;
            }

            this.props.dispatch(createUserRequest({data}));
        } else {
            this.props.dispatch(updateUserRequest(this.props.match.params.id, {
                password: this.state.password1,
                usergroup: this.state.usergroup,
            }));
        }
    }

    render() {
        let appBaseURL = (process.env.WEBPACK_PUBLIC_PATH ? process.env.WEBPACK_PUBLIC_PATH : `/`);

        let menuItems = [];
        if (this.props.subusers) {
            this.props.subusers.map((item, index) => {
                if (this.state.screenName !== item.screenName) {
                    menuItems.push(<MenuItem key={`option_${index}`} value={item.screenName}>{item.screenName}</MenuItem>);
                }
            });
        };

        let formIsValid = false;
        let password1IsValid = (passwordIsStrongEnough(this.state.password1));
        let password2IsValid = (passwordIsStrongEnough(this.state.password2));
        let passwordsMatch = (this.state.password1 === this.state.password2);
        let emailIsValid = false;

        if (this.state.addingSubuser) {
            emailIsValid = validator.validate(this.state.email);
            formIsValid = emailIsValid && password1IsValid && password2IsValid && passwordsMatch;
        } else {
            formIsValid = (!this.state.password1 || (password1IsValid && password2IsValid && passwordsMatch));
        }

        let overlayContent = false;
        let errorMessage = false;
        if (this.props.createSuccess && this.props.createUsername) {
            overlayContent = (<Typography variant="h6" color="inherit">
                <FormattedMessage id="User was created" />
            </Typography>);
        } else if (this.props.updateSuccess) {
            overlayContent = (<Typography variant="h6" color="inherit">
                <FormattedMessage id="User was updated" />
            </Typography>);
        } else if (this.props.createError) {
            errorMessage = (<FormattedMessage id={`errors.${this.props.createErrorCode}`} />);
        } else if (this.props.updateError) {
            errorMessage = (<FormattedMessage id={`errors.${this.props.updateErrorCode}`} />)
        }
        
        let overlay = false;
        if (overlayContent) {
            overlay = (<OverlayContainer>
                <OverlayInner>
                    {overlayContent}
                    <StyledButtonLink to={appBaseURL}>
                        <Button variant="contained" color="primary">
                            <FormattedMessage id="Dashboard" />
                        </Button>
                    </StyledButtonLink>
                </OverlayInner>
            </OverlayContainer>);
        }

        let password1Field = (<TextFieldWrapper>
            <TextField
                fullWidth
                type="password"
                label={this.props.intl.formatMessage({id: `Password`})}
                value={this.state.password1}
                required
                error={this.state.password1.length > 0 ? !password1IsValid : false}
                helperText={this.props.intl.formatMessage({id: "Minimum 8 characters long, at least one capital letter and one digit"})}
                onChange={() => { this.setState({ password1: event.target.value}) }}/>
        </TextFieldWrapper>);

        let password2Field = (<TextFieldWrapper>
            <TextField
                fullWidth
                type="password"
                label={this.props.intl.formatMessage({id: `Retype password`})}
                value={this.state.password2}
                required
                error={this.state.password2.length > 0 ? (!password2IsValid || !passwordsMatch) : false}
                helperText={this.state.password2.length > 0 && !passwordsMatch ? this.props.intl.formatMessage({id: "Passwords do not match"}) : false}
                onChange={() => { this.setState({ password2: event.target.value}) }}/>
        </TextFieldWrapper>);

        return (<div style={{position: `relative`}}>
            {overlay}
            <Grid container spacing={24}>
                <Grid item md={12}>
                    <Typography variant="h6" color="inherit">
                        {this.state.addingSubuser ? (<FormattedMessage id="Create subuser" />) : (<FormattedMessage id="Change subuser settings" />)}
                    </Typography>
                    <Typography variant="body1" color="inherit" style={{paddingTop: `10px`}}>
                    {this.state.addingSubuser ? (<FormattedMessage id="containers.SubuserPage.descriptionCreate"/>) : (<FormattedMessage id="containers.SubuserPage.descriptionEdit"/>)}
                    </Typography>
                </Grid>
            </Grid>
            {this.state.addingSubuser ? (<Grid container spacing={24}>
                <Grid item sm={12} md={6}>
                    <TextFieldWrapper>
                        <TextField
                            fullWidth
                            label={this.props.intl.formatMessage({id: `Name`})}
                            value={this.state.screenName}
                            helperText={this.props.intl.formatMessage({id: "Name will be used as identifier"})}
                            onChange={() => { this.setState({ screenName: event.target.value}) }}/>
                    </TextFieldWrapper>
                    <TextFieldWrapper>
                        <TextField
                            fullWidth
                            label={this.props.intl.formatMessage({id: `Email address`})}
                            value={this.state.email}
                            error={this.state.email.length > 0 ? !emailIsValid : false}
                            onChange={() => { this.setState({ email: event.target.value}) }}/>
                    </TextFieldWrapper>
                </Grid>
                <Grid item sm={12} md={6}>
                    {password1Field}
                    {password2Field}
                </Grid>
            </Grid>) : (<Grid container spacing={24}>
                <Grid item sm={12} md={6}>{password1Field}</Grid>
                <Grid item sm={12} md={6}>{password2Field}</Grid>
            </Grid>)}
            <Grid container spacing={24}>
                <Grid item md={12}>
                    <Typography variant="body1" color="inherit">
                        {this.state.addingSubuser ? (<FormattedMessage id="containers.SubuserPage.descriptionInheritPrivilegesCreate" />) : (<FormattedMessage id="containers.SubuserPage.descriptionInheritPrivilegesEdit" />)}
                    </Typography>
                </Grid>
            </Grid>
            <Grid container spacing={0}>
                <Grid item md={12} style={{textAlign: `right`}}>
                    <FormControl>
                        <Select
                            value={this.state.usergroup}
                            onChange={(event) => { this.setState({usergroup: event.target.value})}}>
                            <MenuItem value={NULL_VALUE}>{this.props.intl.formatMessage({id: `Do not inherit`})}</MenuItem>
                            {menuItems}
                        </Select>
                    </FormControl>
                </Grid>
            </Grid>

            <Grid container spacing={24}>
                <Grid item md={12}>
                    <Button
                        disabled={!formIsValid}
                        variant="contained"
                        size="small"
                        color="primary"
                        onClick={this.handleSave.bind(this)}>
                        {this.state.addingSubuser ? this.props.intl.formatMessage({id: "Save"}) : (this.props.intl.formatMessage({id: "Update"}) + ' ' + this.state.screenName)}
                    </Button>

                    {errorMessage ? (
                    <div style={{width: `100%`}}>
                        <Typography variant="body1" color="inherit" style={{color: `red`, padding: `10px`, textAlign: `center`}}>
                            {errorMessage}
                        </Typography>
                    </div>) : false}
                </Grid>
            </Grid>
        </div>);
    }
}

const mapStateToProps = createStructuredSelector({
    user: makeSelectUser(),
    subusers: makeSelectSubusers(),

    createSuccess: makeSelectCreateUserSuccess(),
    createUsername: makeSelectCreateUserSuccessUserName(),
    createError: makeSelectCreateUserError(),
    createErrorCode: makeSelectCreateUserErrorCode(),

    updateSuccess: makeSelectUpdateUserSuccess(),
    updateUsername: makeSelectUpdateUserSuccessUserName(),
    updateError: makeSelectUpdateUserError(),
    updateErrorCode: makeSelectUpdateUserErrorCode(),
});

const withConnect = connect(mapStateToProps);

export default compose(withConnect)(injectIntl(SubuserPage));
