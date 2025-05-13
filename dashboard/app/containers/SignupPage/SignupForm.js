import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { FormattedMessage } from 'react-intl';
import {injectIntl} from 'react-intl';
import validator from 'email-validator';

import FormControl from '@material-ui/core/FormControl';
import TextField from '@material-ui/core/TextField';
import Button from '@material-ui/core/Button';
import FormGroup from '@material-ui/core/FormGroup';
import FormControlLabel from '@material-ui/core/FormControlLabel';
import Checkbox from '@material-ui/core/Checkbox';

import { passwordIsStrongEnough } from 'utils/shared';

class SignupForm extends React.Component {
    constructor(props) {
        super(props);
        this.state = {
            name: ``,
            email: ``,
            password1: ``,
            password2: ``,
            agreement: false,
        };
    }

    handleChange = () => {
        this.setState({ agreement: !this.state.agreement });
    };

    render() {
        let nameIsValid = (this.state.name.length > 1);
        let emailIsValid = validator.validate(this.state.email);
        let password1IsValid = (passwordIsStrongEnough(this.state.password1));
        let password2IsValid = (passwordIsStrongEnough(this.state.password2));
        let passwordsMatch = (this.state.password1 === this.state.password2);

        let formIsValid = nameIsValid && emailIsValid && password1IsValid && password2IsValid && passwordsMatch && this.state.agreement;

        return (<form>
            <div style={{ paddingBottom: `20px` }}>
                <FormControl margin="normal" fullWidth>
                    <TextField
                        id="name"
                        name="name"
                        required
                        error={this.state.name.length > 0 ? !nameIsValid : false}
                        label={this.props.intl.formatMessage({id: "Name"})}
                        disabled={this.props.disabled}
                        autoFocus
                        value={this.state.name}
                        onChange={(event) => { this.setState({ name: event.target.value }) }}/>
                </FormControl>

                <FormControl margin="normal" fullWidth>
                    <TextField
                        id="email"
                        name="email"
                        required
                        error={this.state.email.length > 0 ? !emailIsValid : false}
                        label={this.props.intl.formatMessage({id: "Email address"})}
                        disabled={this.props.disabled}
                        value={this.state.email}
                        onChange={(event) => { this.setState({ email: event.target.value }) }}/>
                </FormControl>

                <FormControl margin="normal" fullWidth>
                    <TextField
                        id="password1"
                        name="password1"
                        type="password"
                        required
                        error={this.state.password1.length > 0 ? !password1IsValid : false}
                        label={this.props.intl.formatMessage({id: "Password"})}
                        helperText={this.props.intl.formatMessage({id: "Minimum 8 characters long, at least one capital letter and one digit"})}
                        disabled={this.props.disabled}
                        value={this.state.password1}
                        onChange={(event) => { this.setState({ password1: event.target.value }) }}/>
                </FormControl>

                <FormControl margin="normal" fullWidth>
                    <TextField
                        id="password2"
                        name="password2"
                        type="password"
                        required
                        error={this.state.password2.length > 0 ? (!password2IsValid || !passwordsMatch) : false}
                        label={this.props.intl.formatMessage({id: "Retype password"})}
                        helperText={this.state.password2.length > 0 && !passwordsMatch ? this.props.intl.formatMessage({id: "Passwords do not match"}) : false}
                        disabled={this.props.disabled}
                        value={this.state.password2}
                        onChange={(event) => { this.setState({ password2: event.target.value }) }}/>
                </FormControl>

                <FormGroup row>
                    <FormControlLabel
                    control={
                        <Checkbox
                            disabled={this.props.disabled}
                            checked={this.state.agreement}
                            onChange={this.handleChange.bind(this)}
                            value="checkedA"/>
                    }
                    label={this.props.intl.formatMessage({id: "I have read the User agreement and Privacy policy"})}
                    />
                </FormGroup>
            </div>

            <Button
                type="button"
                onClick={() => { this.props.onSubmit({
                    data: {
                        name: this.state.name,
                        email: this.state.email,
                        password: this.state.password1
                    }
                })}}
                fullWidth
                variant="contained"
                disabled={!formIsValid || this.props.disabled}
                color="primary">
                <FormattedMessage id="Register"/>
            </Button>
        </form>);
    }
}

SignupForm.propTypes = {
    onSubmit: PropTypes.func.isRequired,
    disabled: PropTypes.bool.isRequired
};

export default injectIntl(SignupForm);
