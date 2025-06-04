import React from 'react';
import styled from 'styled-components';
import CircularProgress from '@material-ui/core/CircularProgress';
import { connect } from 'react-redux';
import { createStructuredSelector } from 'reselect';

import { makeSelectIsRequesting } from 'containers/App/selectors';

const LoadingWrapper = styled.div`
    position: absolute;
    text-align: center;
    width: 100%;
    height: 100%;
    background-color: rgba(255, 255, 255, 0.5);
    z-index: 1000;
`;

const InternalWraper = styled.div`
    top: 50%;
    position: absolute;
    left: 50%;
`;

class LoadingOverlay extends React.Component {
    constructor(props) {
        super(props);
    }

    render() {
        return this.props.loading ? (<LoadingWrapper>
            <InternalWraper>
                <CircularProgress/>
            </InternalWraper>
        </LoadingWrapper>) : false;
    }
}

const mapStateToProps = createStructuredSelector({
    loading: makeSelectIsRequesting()
});

export default connect(mapStateToProps)(LoadingOverlay);