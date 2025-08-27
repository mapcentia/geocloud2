import React from 'react';
import {connect} from 'react-redux';
import styled from 'styled-components';
import {createStructuredSelector} from 'reselect';
import {makeSelectSigningIn, makeSelectSigningInError} from 'containers/App/selectors';
import {makeDatabaseError, makeSelectGC2Configuration} from '../App/selectors';
import OpenId from './OpenId';

class Signin extends React.Component {
    constructor(props) {
        super(props);
    }

    render() {
        return (<OpenId/>);
    }
}

const mapStateToProps = createStructuredSelector({
    signingIn: makeSelectSigningIn(),
    signingInError: makeSelectSigningInError(),
    databaseError: makeDatabaseError(),
    gc2Configuration: makeSelectGC2Configuration(),
});

export default connect(mapStateToProps)(Signin);
