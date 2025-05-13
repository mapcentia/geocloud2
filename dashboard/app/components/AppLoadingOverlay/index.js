import React from 'react';
import styled from 'styled-components';
import { FormattedMessage } from 'react-intl';
import CircularProgress from '@material-ui/core/CircularProgress';
import Typography from '@material-ui/core/Typography';

const LoadingWrapper = styled.div`
    position: absolute;
    text-align: center;
    width: 100%;
    height: 100%;
    background-color: rgba(255, 255, 255, 1);
    z-index: 1000;
`;

const InnerWrapper = styled.div`
    position: absolute;
    background-color: white;
    top: 40%;
    left: calc(50% - 150px);
    text-align: center;
    width: 300px;
`;

const TextWrapper = styled.div` padding-top: 20px; `;

const AppLoadingOverlay = (props) => {
    let messageId = (props.messageId ? props.messageId : `checkingAuthorizationStatus`);
    return (<LoadingWrapper>
        <InnerWrapper>
            <CircularProgress/>
            <TextWrapper>
                <Typography variant="subtitle1" gutterBottom>
                    <FormattedMessage id={messageId} />
                </Typography>
            </TextWrapper>
        </InnerWrapper>
    </LoadingWrapper>);
};

export default AppLoadingOverlay;
